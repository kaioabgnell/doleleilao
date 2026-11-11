<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure;

use DoleLeilao\Billing\Application\Port\FeeChargeRepository;
use DoleLeilao\Billing\Domain\FeeCharge;
use DoleLeilao\Billing\Infrastructure\Eloquent\AwardFeeRecord;
use DoleLeilao\Billing\Infrastructure\Eloquent\FeeChargeRecord;

/** Roda na fila, sem loja definida: o `auctioneer_id` vem do débito. */
final class EloquentFeeChargeRepository implements FeeChargeRepository
{
    public function existsForAuction(int $auctionId): bool
    {
        return FeeChargeRecord::withoutGlobalScope('tenant')->where('auction_id', $auctionId)->lockForUpdate()->exists();
    }

    public function create(FeeCharge $charge): void
    {
        $record = FeeChargeRecord::withoutGlobalScope('tenant')->create([
            'auctioneer_id' => $charge->auctioneerId,
            'auction_id' => $charge->auctionId,
            'auction_name' => $charge->auctionName,
            'fee_payer' => $charge->feePayer,
            'amount' => $charge->total()->cents,
            'status' => $charge->status,
            'card_brand' => $charge->card?->brand,
            'card_last4' => $charge->card?->last4,
            'created_at' => $charge->createdAt,
            'updated_at' => $charge->createdAt,
        ]);

        foreach ($charge->lines as $line) {
            AwardFeeRecord::withoutGlobalScope('tenant')->create([
                'auctioneer_id' => $charge->auctioneerId,
                'fee_charge_id' => $record->id,
                'award_id' => $line->awardId,
                'item_title' => mb_substr($line->itemTitle, 0, 120),
                'base_amount' => $line->fee->base->cents,
                'rate_bps' => $line->fee->rateBps,
                'fee_amount' => $line->fee->fee->cents,
            ]);
        }
    }
}
