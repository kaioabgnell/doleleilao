<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure;

use DoleLeilao\Auction\Domain\AuctionSettings;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Auction\Infrastructure\Eloquent\AwardRecord;
use DoleLeilao\Billing\Application\Port\SoldItems;
use DoleLeilao\Billing\Application\SoldAuction;
use DoleLeilao\Billing\Application\SoldItem;
use DoleLeilao\Shared\Domain\Money;

/** Camada anticorrupção: traduz o lote e as arrematações do contexto Leilão para a cobrança. */
final class AuctionSoldItems implements SoldItems
{
    public function ofAuction(int $auctionId): ?SoldAuction
    {
        $auction = AuctionRecord::withoutGlobalScope('tenant')->find($auctionId);
        if ($auction === null) {
            return null;
        }

        $awards = AwardRecord::withoutGlobalScope('tenant')
            ->with(['item' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->whereHas('item', fn ($q) => $q->withoutGlobalScope('tenant')->where('auction_id', $auctionId))
            ->orderBy('awarded_at')
            ->get();

        return new SoldAuction(
            $auction->id,
            $auction->auctioneer_id,
            $auction->name,
            AuctionSettings::fromArray($auction->settings)->feePayer,
            array_values($awards->map(static fn (AwardRecord $a): SoldItem => new SoldItem(
                $a->id,
                $a->item->title,
                Money::fromCents($a->amount),
                $a->awarded_at->toDateTimeImmutable(),
            ))->all()),
        );
    }
}
