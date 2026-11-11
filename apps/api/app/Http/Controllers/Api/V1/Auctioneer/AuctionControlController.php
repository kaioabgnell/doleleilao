<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionItemResource;
use App\Http\Resources\AuctionResource;
use DoleLeilao\Auction\Application\Actor;
use DoleLeilao\Auction\Application\ControlAuction;
use DoleLeilao\Auction\Infrastructure\AuctionReadModel;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionItemRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuditLogRecord;
use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Controles do pregão ao vivo (RF-D05). Cada ação vai para o log de auditoria. */
final class AuctionControlController
{
    use ResolvesAuctioneerUser;

    public function __construct(private readonly ControlAuction $control, private readonly Clock $clock) {}

    /** Painel do pregão: lote, itens, últimos lances do item em pregão (com nome) e log. */
    public function live(string $auction): JsonResponse
    {
        $record = AuctionRecord::query()->where('public_id', $auction)->firstOrFail()
            ->load(['items.product.photos', 'items.leader'])->loadCount('participations');
        AuctionReadModel::attachCovers([$record]);
        $running = $record->items->first(fn (AuctionItemRecord $i): bool => $i->status->isRunning());

        $itemTitles = $record->items->pluck('title', 'public_id');
        $log = AuditLogRecord::query()->where('auction_id', $record->id)->orderByDesc('id')->limit(40)->get()
            ->map(fn (AuditLogRecord $l): array => [
                'action' => $l->action,
                'actor' => $l->actor_type,
                'item_title' => is_string($l->payload['item'] ?? null) ? $itemTitles->get($l->payload['item']) : null,
                'payload' => $l->payload,
                'at' => AuctionItemResource::iso($l->created_at),
            ]);

        return response()->json([
            'auction' => new AuctionResource($record),
            'running_item_id' => $running?->public_id,
            'recent_bids' => $running === null ? [] : AuctionReadModel::recentBids($running->id, 20, withNames: true),
            'log' => $log,
            'server_now' => AuctionItemResource::iso($this->clock->now()),
        ]);
    }

    public function start(Request $request, string $auction): JsonResponse
    {
        $this->control->start($auction, $this->actor($request));

        return $this->live($auction);
    }

    public function pause(Request $request, string $auction): JsonResponse
    {
        $this->control->pause($auction, $this->actor($request));

        return $this->live($auction);
    }

    public function resume(Request $request, string $auction): JsonResponse
    {
        $this->control->resume($auction, $this->actor($request));

        return $this->live($auction);
    }

    public function closeItem(Request $request, string $item): JsonResponse
    {
        $this->control->closeItemNow($item, $this->actor($request));

        return $this->liveOfItem($item);
    }

    public function skipItem(Request $request, string $item): JsonResponse
    {
        $this->control->skipItem($item, $this->actor($request));

        return $this->liveOfItem($item);
    }

    public function voidLastBid(Request $request, string $item): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']], [], ['reason' => 'o motivo']);
        $this->control->voidLastBid($item, $request->string('reason')->value(), $this->actor($request));

        return $this->liveOfItem($item);
    }

    private function liveOfItem(string $item): JsonResponse
    {
        $record = AuctionItemRecord::query()->where('public_id', $item)->with('auction')->firstOrFail();

        return $this->live($record->auction->public_id);
    }

    private function actor(Request $request): Actor
    {
        return Actor::auctioneerUser($this->currentUser($request)->id);
    }
}
