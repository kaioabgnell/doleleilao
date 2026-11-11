<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure;

use DoleLeilao\Auction\Application\ItemNotFound;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Domain\Auction;
use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Domain\ItemSnapshot;
use DoleLeilao\Auction\Domain\ItemStatus;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Shared\Infrastructure\Scalar;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentAuctionRepository implements AuctionRepository
{
    /** @var array<int, AuctionRecord> */
    private array $loaded = [];

    public function idOf(string $publicId): ?int
    {
        $id = AuctionRecord::query()->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function lockForUpdate(int $auctionId): Auction
    {
        $record = AuctionRecord::withoutGlobalScope('tenant')->lockForUpdate()->find($auctionId) ?? throw new ItemNotFound;
        $this->loaded[$record->id] = $record;

        return self::toDomain($record);
    }

    public static function toDomain(AuctionRecord $record): Auction
    {
        return new Auction(
            id: $record->id,
            publicId: $record->public_id,
            auctioneerId: $record->auctioneer_id,
            name: $record->name,
            slug: $record->slug,
            description: $record->description ?? '',
            startsAt: $record->starts_at->toDateTimeImmutable(),
            status: $record->status,
            settings: AuctionSettings::fromArray($record->settings),
            startedAt: $record->started_at?->toDateTimeImmutable(),
            finishedAt: $record->finished_at?->toDateTimeImmutable(),
            visibleInStore: $record->visible_in_store,
        );
    }

    public static function fill(AuctionRecord $record, Auction $auction): AuctionRecord
    {
        return $record->fill([
            'name' => $auction->name(),
            'slug' => $auction->slug(),
            'description' => $auction->description() === '' ? null : $auction->description(),
            'starts_at' => $auction->startsAt(),
            'status' => $auction->status(),
            'settings' => $auction->settings()->toArray(),
            'started_at' => $auction->startedAt(),
            'finished_at' => $auction->finishedAt(),
            'visible_in_store' => $auction->isVisibleInStore(),
        ]);
    }

    public function save(Auction $auction): void
    {
        $record = $this->loaded[$auction->id] ?? throw new \LogicException('Lote não foi carregado com trava.');
        unset($this->loaded[$auction->id]);
        self::fill($record, $auction)->save();
    }

    public function settingsOf(int $auctionId): AuctionSettings
    {
        $raw = DB::table('auctions')->where('id', $auctionId)->value('settings');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $settings = [];
        foreach (is_array($data) ? $data : [] as $key => $value) {
            $settings[(string) $key] = $value;
        }

        return AuctionSettings::fromArray($settings);
    }

    public function itemSnapshots(int $auctionId): array
    {
        $snapshots = [];
        foreach (DB::table('auction_items')->where('auction_id', $auctionId)->orderBy('position')->get(['id', 'position', 'status', 'closed_at']) as $row) {
            $snapshots[] = new ItemSnapshot(
                Scalar::int($row->id),
                Scalar::int($row->position),
                ItemStatus::from(Scalar::string($row->status)),
                $row->closed_at === null ? null : Carbon::parse(Scalar::string($row->closed_at), 'UTC')->toDateTimeImmutable(),
            );
        }

        return $snapshots;
    }

    public function setCurrentItem(int $auctionId, ?int $itemId): void
    {
        DB::table('auctions')->where('id', $auctionId)->update(['current_item_id' => $itemId]);
    }

    public function runningItemId(int $auctionId): ?int
    {
        $id = DB::table('auction_items')->where('auction_id', $auctionId)->whereIn('status', ['open', 'paused'])->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function itemCount(int $auctionId): int
    {
        return DB::table('auction_items')->where('auction_id', $auctionId)->count();
    }

    public function queuedProductIds(int $auctionId): array
    {
        return Scalar::ints(DB::table('auction_items')
            ->where('auction_id', $auctionId)
            ->where('status', 'queued')
            ->whereNotNull('product_id')
            ->pluck('product_id'));
    }

    public function lockDueToStart(\DateTimeImmutable $now, int $limit): array
    {
        return Scalar::ints(DB::table('auctions')
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', $now->format('Y-m-d H:i:s.u'))
            ->orderBy('starts_at')
            ->limit($limit)
            ->lock('for update skip locked')
            ->pluck('id'));
    }

    public function lockLiveWithoutRunningItem(int $limit): array
    {
        return Scalar::ints(DB::table('auctions')
            ->where('status', 'live')
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
                ->from('auction_items')
                ->whereColumn('auction_items.auction_id', 'auctions.id')
                ->whereIn('auction_items.status', ['open', 'paused']))
            ->limit($limit)
            ->lock('for update skip locked')
            ->pluck('id'));
    }
}
