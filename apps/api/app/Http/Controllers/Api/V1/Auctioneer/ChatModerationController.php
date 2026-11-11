<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use DoleLeilao\Chat\Application\Author;
use DoleLeilao\Chat\Application\ChatNotFound;
use DoleLeilao\Chat\Application\ModerateChat;
use DoleLeilao\Chat\Application\PostMessage;
use DoleLeilao\Chat\Domain\MuteDuration;
use DoleLeilao\Chat\Infrastructure\BroadcastChatEvents;
use DoleLeilao\Chat\Infrastructure\ChatReadModel;
use DoleLeilao\Chat\Infrastructure\Eloquent\ChatMuteRecord;
use DoleLeilao\Chat\Infrastructure\EloquentChatRepository;
use DoleLeilao\Identity\Infrastructure\BidderBans;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Infrastructure\Scalar;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Chat no painel do pregão: o leiloeiro lê (com nomes), escreve e modera (RF-F08, RF-G02). */
final class ChatModerationController
{
    use ResolvesAuctioneerUser;

    public function __construct(private readonly EloquentChatRepository $chat, private readonly Clock $clock) {}

    public function index(Request $request, string $auction): JsonResponse
    {
        $request->validate(['before' => ['nullable', 'string', 'size:26']]);
        $context = $this->chat->context($auction) ?? throw new ChatNotFound;
        $page = ChatReadModel::page($context->auctionId, $request->filled('before') ? $request->string('before')->value() : null, withNames: true);

        $now = $this->clock->now();
        $mutes = ChatMuteRecord::query()->where('auction_id', $context->auctionId)->get()
            ->filter(fn (ChatMuteRecord $m) => $m->until === null || $m->until->greaterThan($now));
        $mutedIds = DB::table('bidders')->whereIn('id', $mutes->pluck('bidder_id'))->pluck('public_id', 'id');

        return response()->json($page + [
            'muted' => (object) $mutes->mapWithKeys(fn (ChatMuteRecord $m) => [
                Scalar::string($mutedIds->get($m->bidder_id)) => $m->until?->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            ])->all(),
            'banned' => DB::table('bans')->join('bidders', 'bidders.id', '=', 'bans.bidder_id')
                ->where('bans.auctioneer_id', $context->auctioneerId)->whereNull('bans.lifted_at')->pluck('bidders.public_id'),
            'settings' => ['enabled' => $context->settings->enabled, 'open' => $context->open],
            'channel' => ['name' => BroadcastChatEvents::name($context->auctionPublicId), 'private' => $context->settings->loggedInOnly],
        ]);
    }

    public function store(Request $request, string $auction, PostMessage $post): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:4000'], 'reply_to_id' => ['nullable', 'string', 'size:26']], [], ['body' => 'a mensagem']);
        $message = $post->execute($auction, Author::auctioneerUser($this->currentUser($request)->id), $request->string('body')->value(), $request->input('reply_to_id') === null ? null : $request->string('reply_to_id')->value());

        return response()->json($message, 201);
    }

    public function destroy(Request $request, string $auction, string $message, ModerateChat $moderate): JsonResponse
    {
        $moderate->delete($auction, $message, $this->currentUser($request)->id);

        return response()->json(null, 204);
    }

    /** `minutes`: 5, 15, 60 ou nulo (até o fim do leilão). */
    public function mute(Request $request, string $auction, string $bidder, ModerateChat $moderate): JsonResponse
    {
        $request->validate(['minutes' => ['nullable', 'integer']]);
        $duration = $request->input('minutes') === null ? MuteDuration::wholeAuction() : MuteDuration::minutes($request->integer('minutes'));
        $until = $moderate->mute($auction, $bidder, $duration, $this->currentUser($request)->id);

        return response()->json(['until' => $until?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z')]);
    }

    public function unmute(string $auction, string $bidder, ModerateChat $moderate): JsonResponse
    {
        $moderate->unmute($auction, $bidder);

        return response()->json(null, 204);
    }

    public function ban(Request $request, string $bidder, BidderBans $bans): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:300']], [], ['reason' => 'o motivo']);
        $bans->ban($this->bidder($bidder), $request->string('reason')->value(), $this->currentUser($request)->id);

        return response()->json(null, 204);
    }

    public function unban(string $bidder, BidderBans $bans): JsonResponse
    {
        $bans->lift($this->bidder($bidder));

        return response()->json(null, 204);
    }

    private function bidder(string $publicId): BidderUser
    {
        $bidder = BidderUser::query()->where('auctioneer_id', $this->tenantId())->where('public_id', $publicId)->first();
        if ($bidder === null) {
            abort(Problem::make(404, 'Participante não encontrado.'));
        }

        return $bidder;
    }

    private function tenantId(): int
    {
        return app(CurrentTenant::class)->id();
    }
}
