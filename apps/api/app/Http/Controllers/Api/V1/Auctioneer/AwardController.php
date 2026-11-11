<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionItemResource;
use DoleLeilao\Auction\Domain\AwardStatus;
use DoleLeilao\Auction\Domain\WinnerMessageTemplate;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\WinnerMessageRecord;
use DoleLeilao\Auction\Infrastructure\WinnerMessages;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductPhotoRecord;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arrematações no painel (RF-E08, RF-G05, RF-G06): status, observações, mensagem ao vencedor
 * (e-mail + WhatsApp, D-25) e exportação CSV por lote.
 */
final class AwardController
{
    use ResolvesAuctioneerUser;

    public function __construct(private readonly WinnerMessages $messages) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'auction' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(AwardStatus::cases(), 'value')).',open'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $awards = $this->query($request)->with(['bidder', 'item.auction', 'item.product.photos', 'messages'])->orderByDesc('awarded_at')->limit(500)->get();

        return response()->json([
            'data' => $awards->map(fn (AwardRecord $a) => $this->present($a))->values(),
            'counts' => AwardRecord::query()
                ->when($request->filled('auction'), fn ($q) => $this->whereAuction($q, $request->string('auction')->value()))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'auctions' => AuctionRecord::query()->whereHas('items', fn ($q) => $q->whereIn('status', ['closed_sold']))
                ->orderByDesc('starts_at')->limit(100)->get(['public_id', 'name', 'starts_at'])
                ->map(fn (AuctionRecord $a) => ['id' => $a->public_id, 'name' => $a->name, 'starts_at' => AuctionItemResource::iso($a->starts_at)]),
            'statuses' => array_map(fn (AwardStatus $s) => ['value' => $s->value, 'label' => $s->label()], AwardStatus::cases()),
        ]);
    }

    public function update(Request $request, string $award): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(AwardStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [], ['notes' => 'as observações']);
        $record = $this->find($award);
        if ($request->has('status')) {
            $record->status = AwardStatus::from($request->string('status')->value());
        }
        if ($request->has('notes')) {
            $notes = $request->string('notes')->trim()->value();
            $record->notes = $notes === '' ? null : $notes;
        }
        $record->save();

        return response()->json($this->present($record->load(['bidder', 'item.auction', 'item.product.photos', 'messages'])));
    }

    /** Texto pronto (modelo da loja preenchido) para a tela de mensagem. */
    public function draft(string $award): JsonResponse
    {
        $record = $this->find($award);

        return response()->json(['body' => $this->messages->draft($record)]);
    }

    /** `channels`: email e/ou whatsapp. A resposta traz o link do WhatsApp com o texto pronto. */
    public function message(Request $request, string $award): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:4000'], 'channels' => ['required', 'array'], 'channels.*' => ['string']], [], ['body' => 'a mensagem']);
        $record = $this->find($award);
        /** @var list<string> $channels */
        $channels = array_values(array_filter((array) $request->input('channels'), 'is_string'));
        $result = $this->messages->send($record, $request->string('body')->value(), $channels, $this->currentUser($request)->id);

        return response()->json([
            'message' => self::message_($result['message']),
            'whatsapp_url' => $result['whatsapp_url'],
        ], 201);
    }

    public function template(Request $request): JsonResponse
    {
        $auctioneerId = $this->currentUser($request)->auctioneer_id;

        return response()->json($this->templateBody($this->messages->template($auctioneerId)));
    }

    /** `template` nulo volta ao texto padrão. */
    public function saveTemplate(Request $request): JsonResponse
    {
        $request->validate(['template' => ['present', 'nullable', 'string', 'max:4000']]);
        $text = $request->input('template');
        $template = $this->messages->saveTemplate($this->currentUser($request)->auctioneer_id, is_string($text) ? $text : null);

        return response()->json($this->templateBody($template));
    }

    /** CSV para planilha (RF-G06): separador ";" e BOM para o Excel em português abrir certo. */
    public function export(Request $request): StreamedResponse
    {
        $request->validate(['auction' => ['nullable', 'string', 'size:26']]);
        $awards = $this->query($request)->with(['bidder', 'item.auction'])->orderBy('awarded_at')->get();
        $name = $request->filled('auction') ? ($awards->first()->item->auction->slug ?? 'lote') : 'todas';

        return response()->streamDownload(function () use ($awards): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Leilão', 'Data', 'Item', 'Posição', 'Arrematante', 'Apelido', 'CPF', 'E-mail', 'Telefone', 'Valor (R$)', 'Status', 'Observações'], ';', '"', '');
            foreach ($awards as $a) {
                fputcsv($out, [
                    $a->item->auction->name,
                    $a->awarded_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                    $a->item->title,
                    $a->item->position,
                    $a->bidder->name,
                    $a->bidder->nickname,
                    $a->bidder->cpf()->formatted(),
                    $a->bidder->email,
                    Phone::fromString($a->bidder->phone)->formatted(),
                    number_format($a->amount / 100, 2, ',', '.'),
                    $a->status->label(),
                    $a->notes ?? '',
                ], ';', '"', '');
            }
            fclose($out);
        }, "arrematacoes-{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<AwardRecord> */
    private function query(Request $request): Builder
    {
        $status = $request->string('status')->value();

        return AwardRecord::query()
            ->when($request->filled('auction'), fn ($q) => $this->whereAuction($q, $request->string('auction')->value()))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', [AwardStatus::AwaitingContact, AwardStatus::Negotiating, AwardStatus::Paid]))
            ->when($status !== '' && $status !== 'open', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->string('q')->value(), '%_').'%';
                $q->where(fn ($w) => $w->whereHas('bidder', fn ($b) => $b->where('name', 'like', $term)->orWhere('nickname', 'like', $term))
                    ->orWhereHas('item', fn ($i) => $i->where('title', 'like', $term)));
            });
    }

    /** @param Builder<AwardRecord> $q */
    private function whereAuction(Builder $q, string $auction): void
    {
        $q->whereHas('item.auction', fn ($a) => $a->where('public_id', $auction));
    }

    private function find(string $publicId): AwardRecord
    {
        return AwardRecord::query()->where('public_id', $publicId)->with(['bidder', 'item.auction'])->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(AwardRecord $a): array
    {
        $photo = $a->item->product?->photos->first(fn (ProductPhotoRecord $p) => $p->status === 'ready');

        return [
            'id' => $a->public_id,
            'amount_cents' => $a->amount,
            'awarded_at' => AuctionItemResource::iso($a->awarded_at),
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'notes' => $a->notes,
            'item' => [
                'id' => $a->item->public_id,
                'title' => $a->item->title,
                'position' => $a->item->position,
                'thumb' => $photo === null ? null : app(MediaStorage::class)->url($photo->variants['thumb']['webp'] ?? null),
            ],
            'auction' => ['id' => $a->item->auction->public_id, 'name' => $a->item->auction->name, 'slug' => $a->item->auction->slug],
            'bidder' => [
                'id' => $a->bidder->public_id,
                'name' => $a->bidder->name,
                'nickname' => $a->bidder->nickname,
                'email' => $a->bidder->email,
                'phone' => Phone::fromString($a->bidder->phone)->formatted(),
                'cpf_masked' => $a->bidder->cpf()->masked(),
            ],
            'messages' => $a->messages->map(fn (WinnerMessageRecord $m) => self::message_($m))->values(),
        ];
    }

    /** @return array{id: string, body: string, channels: list<string>, sent_at: string|null} */
    public static function message_(WinnerMessageRecord $m): array
    {
        return ['id' => $m->public_id, 'body' => $m->body, 'channels' => $m->channels, 'sent_at' => AuctionItemResource::iso($m->sent_at)];
    }

    /** @return array<string, mixed> */
    private function templateBody(WinnerMessageTemplate $template): array
    {
        return [
            'template' => $template->text,
            'is_default' => $template->text === WinnerMessageTemplate::default()->text,
            'fields' => collect(WinnerMessageTemplate::FIELDS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
        ];
    }
}
