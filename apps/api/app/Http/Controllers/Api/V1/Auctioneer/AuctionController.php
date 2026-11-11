<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionResource;
use App\Http\Resources\ProductResource;
use DoleLeilao\Auction\Application\Port\CatalogGateway;
use DoleLeilao\Auction\Domain\Auction;
use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Domain\AuctionStatus;
use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Auction\Infrastructure\AuctionReadModel;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\EloquentAuctionRepository;
use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Catalog\Domain\ProductStatus;
use DoleLeilao\Catalog\Infrastructure\Eloquent\ProductRecord;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Slugify;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Shared\Infrastructure\Scalar;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro dos lotes de leilão no painel (RF-D01, RF-D02, RF-D03, RF-D06). Os itens são produtos
 * do catálogo: valor inicial e incremento vêm do produto e podem ser ajustados até o item abrir.
 * Depois de iniciado, o lote só muda pelos controles do pregão (AuctionControlController).
 */
final class AuctionController
{
    use ResolvesAuctioneerUser;

    /** Produtos que podem entrar num lote. */
    private const array SELECTABLE = [ProductStatus::Available, ProductStatus::Unsold];

    public function __construct(
        private readonly Clock $clock,
        private readonly CatalogGateway $catalog,
        private readonly EventBus $events,
        private readonly PaymentCardRepository $cards,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:draft,scheduled,live,finished,cancelled']]);

        $auctions = AuctionRecord::query()
            ->withCount(['items', 'participations'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->orderByRaw("FIELD(status, 'live', 'scheduled', 'draft', 'finished', 'cancelled')")
            ->orderBy('starts_at')
            ->limit(200)
            ->get();
        AuctionReadModel::attachCovers($auctions);

        return response()->json([
            'data' => AuctionResource::collection($auctions),
            'counts' => AuctionRecord::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'defaults' => AuctionSettings::defaults()->toArray(),
        ]);
    }

    public function show(string $auction): AuctionResource
    {
        return $this->resource($this->find($auction));
    }

    public function store(Request $request): JsonResponse
    {
        $this->validateDetails($request, creating: true);
        $now = $this->clock->now();
        $settings = AuctionSettings::fromArray($this->settingsInput($request));
        $name = $request->string('name')->value();

        $domain = Auction::draft(0, '', $this->currentUser($request)->auctioneer_id, $name, '', $request->string('description')->value(), $this->startsAt($request), $settings, $now);

        $record = DB::transaction(function () use ($domain, $request): AuctionRecord {
            $record = EloquentAuctionRepository::fill(new AuctionRecord, $domain);
            $record->slug = $this->uniqueSlug($domain->name(), null);
            $record->save();
            if ($request->has('items')) {
                $this->syncItems($record, $this->itemsInput($request));
            }

            return $record;
        });

        return $this->resource($record)->response()->setStatusCode(201);
    }

    public function update(Request $request, string $auction): AuctionResource
    {
        $record = $this->find($auction);
        $this->validateDetails($request, creating: false);
        $domain = EloquentAuctionRepository::toDomain($record);
        $domain->assertEditable();

        $settings = AuctionSettings::fromArray($this->settingsInput($request) + $domain->settings()->toArray());
        $domain->update(
            $request->has('name') ? $request->string('name')->value() : $domain->name(),
            $request->has('description') ? $request->string('description')->value() : $domain->description(),
            $request->has('starts_at') ? $this->startsAt($request) : $domain->startsAt(),
            $settings,
            $this->clock->now(),
        );

        DB::transaction(function () use ($record, $domain, $request): void {
            EloquentAuctionRepository::fill($record, $domain);
            // O endereço segue o nome enquanto o lote não está na loja.
            if ($record->status === AuctionStatus::Draft && $record->isDirty('name')) {
                $record->slug = $this->uniqueSlug($domain->name(), $record->id);
            }
            $record->save();
            if ($request->has('items')) {
                $this->syncItems($record, $this->itemsInput($request));
            }
        });
        $this->storeChanged($record);

        return $this->resource($record);
    }

    /** Rascunho, cancelado ou encerrado. Itens, lances, arrematações e chat do lote saem junto (cascade). */
    public function destroy(string $auction, MediaStorage $media): JsonResponse
    {
        $record = $this->find($auction);
        EloquentAuctionRepository::toDomain($record)->assertDeletable();
        $wasPublic = $record->status->isPublic() && $record->visible_in_store;
        $record->delete();
        $media->delete($record->cover_path);
        $media->delete($record->cover_thumb_path);
        if ($wasPublic) {
            $this->storeChanged($record);
        }

        return response()->json(null, 204);
    }

    /** "Visível no site": lote encerrado sai (ou volta) do histórico da loja. */
    public function visibility(Request $request, string $auction): AuctionResource
    {
        $request->validate(['visible' => ['required', 'boolean']]);
        $record = $this->find($auction);
        $domain = EloquentAuctionRepository::toDomain($record);
        $domain->changeStoreVisibility($request->boolean('visible'));
        EloquentAuctionRepository::fill($record, $domain)->save();
        $this->storeChanged($record);

        return $this->resource($record);
    }

    /**
     * Rascunho → agendado: aparece na loja e os produtos ficam "em leilão" (passo 3 dos primeiros
     * passos). Exige o cartão de crédito em Configurações → Pagamento (D-54).
     */
    public function schedule(string $auction): AuctionResource
    {
        $record = $this->find($auction);
        $domain = EloquentAuctionRepository::toDomain($record);
        $domain->schedule($record->items()->count(), $this->cards->findFor($record->auctioneer_id) !== null, $this->clock->now());

        DB::transaction(function () use ($record, $domain): void {
            EloquentAuctionRepository::fill($record, $domain)->save();
            $this->catalog->markInAuction($this->productIds($record));
        });
        foreach ($domain->releaseEvents() as $event) {
            $this->events->publish($event);
        }
        $this->storeChanged($record);

        return $this->resource($record);
    }

    public function unschedule(string $auction): AuctionResource
    {
        return $this->leaveStore($auction, fn (Auction $a) => $a->unschedule());
    }

    public function cancel(string $auction): AuctionResource
    {
        return $this->leaveStore($auction, fn (Auction $a) => $a->cancel());
    }

    /** Produtos que podem entrar no lote: disponíveis ou sem venda, fora de outro lote em andamento. */
    public function selectableProducts(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'auction' => ['nullable', 'string', 'size:26']]);
        $exceptAuction = $request->filled('auction') ? AuctionRecord::query()->where('public_id', $request->string('auction')->value())->value('id') : null;
        $busy = $this->busyProductIds(is_numeric($exceptAuction) ? (int) $exceptAuction : null);

        $products = ProductRecord::query()
            ->with('photos')
            ->whereIn('status', self::SELECTABLE)
            ->whereNotIn('id', $busy)
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.addcslashes($request->string('q')->value(), '%_').'%'))
            ->orderBy('title')
            ->limit(100)
            ->get();

        return response()->json(['data' => ProductResource::collection($products)]);
    }

    /** @param callable(Auction): void $change */
    private function leaveStore(string $auction, callable $change): AuctionResource
    {
        $record = $this->find($auction);
        $domain = EloquentAuctionRepository::toDomain($record);
        $wasPublic = $record->status === AuctionStatus::Scheduled;
        $change($domain);

        DB::transaction(function () use ($record, $domain, $wasPublic): void {
            EloquentAuctionRepository::fill($record, $domain)->save();
            if ($wasPublic) {
                $this->catalog->release($this->productIds($record));
            }
        });
        $this->storeChanged($record);

        return $this->resource($record);
    }

    /**
     * Substitui a lista de itens (ordem = posição). Itens que continuam no lote mantêm o id (e os
     * lances automáticos já cadastrados); os removidos saem; os novos copiam os valores do produto.
     *
     * @param  list<array{product_id: string, starting_price_cents: int|null, increment_cents: int|null}>  $input
     */
    private function syncItems(AuctionRecord $auction, array $input): void
    {
        $products = ProductRecord::query()->whereIn('public_id', array_column($input, 'product_id'))->get()->keyBy('public_id');
        $existing = $auction->items()->get()->keyBy('product_id');
        $busy = $this->busyProductIds($auction->id);
        $errors = [];

        foreach ($input as $i => $row) {
            $product = $products->get($row['product_id']);
            $mine = $product !== null && $existing->has($product->id);
            if ($product === null) {
                $errors["items.{$i}.product_id"] = ['Produto não encontrado.'];
            } elseif (in_array($product->id, $busy, true)) {
                $errors["items.{$i}.product_id"] = ["\"{$product->title}\" já está em outro lote."];
            } elseif (! $mine && ! in_array($product->status, self::SELECTABLE, true)) {
                $errors["items.{$i}.product_id"] = ["\"{$product->title}\" não está disponível. Deixe o produto como disponível no catálogo."];
            }
            if (($row['increment_cents'] ?? 100) < 100) {
                $errors["items.{$i}.increment_cents"] = ['O incremento mínimo é R$ 1,00.'];
            }
            if (($row['starting_price_cents'] ?? 1) < 1) {
                $errors["items.{$i}.starting_price_cents"] = ['O valor inicial precisa ser maior que zero.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $keep = [];
        // Posições temporárias evitam colisão no UNIQUE(auction_id, position) durante a reordenação.
        AuctionItemRecord::query()->where('auction_id', $auction->id)->update(['position' => DB::raw('position + 1000')]);

        foreach ($input as $i => $row) {
            /** @var ProductRecord $product */
            $product = $products->get($row['product_id']);
            $item = $existing->get($product->id) ?? new AuctionItemRecord([
                'auction_id' => $auction->id,
                'product_id' => $product->id,
                'status' => ItemStatus::Queued,
            ]);
            $item->fill([
                'title' => $product->title,
                'position' => $i + 1,
                'starting_price' => $row['starting_price_cents'] ?? ($item->exists ? $item->starting_price : $product->starting_price),
                'increment' => $row['increment_cents'] ?? ($item->exists ? $item->increment : $product->increment),
            ])->save();
            $keep[] = $item->id;
        }

        $removed = $existing->whereNotIn('id', $keep);
        if ($removed->isNotEmpty()) {
            AuctionItemRecord::query()->whereIn('id', $removed->pluck('id'))->delete();
        }

        if ($auction->status === AuctionStatus::Scheduled) {
            $this->catalog->release(Scalar::ints($removed->pluck('product_id')->filter()));
            $this->catalog->markInAuction($this->productIds($auction));
        }
    }

    /** @return list<int> produtos em itens de outros lotes que ainda não terminaram */
    private function busyProductIds(?int $exceptAuctionId): array
    {
        return Scalar::ints(AuctionItemRecord::query()
            ->whereNotNull('product_id')
            ->when($exceptAuctionId !== null, fn ($q) => $q->where('auction_id', '!=', $exceptAuctionId))
            ->whereIn('status', [ItemStatus::Queued, ItemStatus::Open, ItemStatus::Paused])
            ->whereHas('auction', fn ($q) => $q->whereIn('status', [AuctionStatus::Draft, AuctionStatus::Scheduled, AuctionStatus::Live]))
            ->pluck('product_id'));
    }

    /** @return list<int> */
    private function productIds(AuctionRecord $auction): array
    {
        return Scalar::ints($auction->items()->where('status', ItemStatus::Queued)->whereNotNull('product_id')->pluck('product_id'));
    }

    private function validateDetails(Request $request, bool $creating): void
    {
        $required = $creating ? 'required' : 'sometimes';
        $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:6000'],
            'starts_at' => [$required, 'date'],
            'settings' => ['sometimes', 'array'],
            'items' => ['sometimes', 'array', 'max:'.Auction::MAX_ITEMS],
            'items.*.product_id' => ['required', 'string', 'size:26', 'distinct'],
            'items.*.starting_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'items.*.increment_cents' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
        ], ['items.*.product_id.distinct' => 'O mesmo produto aparece duas vezes no lote.'], ['name' => 'o nome', 'starts_at' => 'a data de início']);
    }

    /** @return array<string, mixed> */
    private function settingsInput(Request $request): array
    {
        $settings = [];
        foreach ((array) $request->input('settings', []) as $key => $value) {
            $settings[(string) $key] = $value;
        }

        return $settings;
    }

    /** @return list<array{product_id: string, starting_price_cents: int|null, increment_cents: int|null}> */
    private function itemsInput(Request $request): array
    {
        $items = [];
        foreach ((array) $request->input('items', []) as $row) {
            $row = (array) $row;
            $items[] = [
                'product_id' => is_string($row['product_id'] ?? null) ? $row['product_id'] : '',
                'starting_price_cents' => isset($row['starting_price_cents']) && is_numeric($row['starting_price_cents']) ? (int) $row['starting_price_cents'] : null,
                'increment_cents' => isset($row['increment_cents']) && is_numeric($row['increment_cents']) ? (int) $row['increment_cents'] : null,
            ];
        }

        return $items;
    }

    private function startsAt(Request $request): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($request->string('starts_at')->value()))->setTimezone(new \DateTimeZone('UTC'));
    }

    private function uniqueSlug(string $name, ?int $exceptId): string
    {
        $base = Slugify::from($name, 90) ?: 'leilao';
        $slug = $base;
        for ($n = 2; AuctionRecord::query()->where('slug', $slug)->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function find(string $publicId): AuctionRecord
    {
        return AuctionRecord::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function resource(AuctionRecord $record): AuctionResource
    {
        $record->refresh()->load(['items.product.photos', 'items.leader'])->loadCount('participations');
        AuctionReadModel::attachCovers([$record]);

        return new AuctionResource($record);
    }

    private function storeChanged(AuctionRecord $record): void
    {
        $this->events->publish(new StorefrontChanged($record->auctioneer_id));
    }
}
