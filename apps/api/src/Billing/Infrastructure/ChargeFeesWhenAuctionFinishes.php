<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Infrastructure;

use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Billing\Application\ChargeAuctionFees;
use Illuminate\Contracts\Queue\ShouldQueue;

/** LoteEncerrado → débito da taxa (D-54). Na fila: nunca atrasa o relógio do pregão. */
final class ChargeFeesWhenAuctionFinishes implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(private readonly ChargeAuctionFees $charge) {}

    public function handle(AuctionFinished $event): void
    {
        $this->charge->handle($event->auctionId);
    }
}
