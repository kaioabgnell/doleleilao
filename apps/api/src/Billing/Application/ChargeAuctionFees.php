<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Application;

use DoleLeilao\Billing\Application\Port\FeeChargeRepository;
use DoleLeilao\Billing\Application\Port\FeeTierRepository;
use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Billing\Application\Port\SoldItems;
use DoleLeilao\Billing\Domain\ChargeLine;
use DoleLeilao\Billing\Domain\FeeCharge;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;

/**
 * Lote encerrado → débito da taxa no cartão do leiloeiro (D-47, D-54): cada item arrematado paga a
 * alíquota da faixa vigente na hora do arremate, e o lote gera um débito com a soma. Sem gateway,
 * o débito fica "a cobrar". Pode rodar de novo sem duplicar (um débito por lote).
 */
final readonly class ChargeAuctionFees
{
    public function __construct(
        private SoldItems $sold,
        private FeeTierRepository $tiers,
        private FeeChargeRepository $charges,
        private PaymentCardRepository $cards,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function handle(int $auctionId): void
    {
        $this->tx->run(function () use ($auctionId): void {
            if ($this->charges->existsForAuction($auctionId)) {
                return;
            }
            $auction = $this->sold->ofAuction($auctionId);
            if ($auction === null) {
                return;
            }

            $schedule = $this->tiers->schedule();
            $lines = [];
            foreach ($auction->items as $item) {
                $fee = $schedule->feeFor($item->amount, $item->awardedAt);
                if ($fee !== null) {
                    $lines[] = new ChargeLine($item->awardId, $item->title, $fee);
                }
            }

            $charge = FeeCharge::forAuction($auction->auctionId, $auction->auctioneerId, $auction->name, $auction->feePayer, $lines, $this->cards->findFor($auction->auctioneerId), $this->clock->now());
            if ($charge !== null) {
                $this->charges->create($charge);
            }
        });
    }
}
