<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Application\Port\BidderGate;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Money;

/**
 * Lance manual (specs/04-arquitetura.md, fluxo de um lance): trava o item, aplica as regras do
 * domínio e os automáticos encadeados na mesma transação, e só publica os eventos depois do commit.
 * A chave de idempotência faz o clique duplo devolver o mesmo resultado em vez de um segundo lance.
 */
final readonly class PlaceBid
{
    public function __construct(
        private TransactionManager $tx,
        private AuctionItemRepository $items,
        private AuctionRepository $auctions,
        private BidderGate $gate,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function execute(int $bidderId, string $itemPublicId, int $amountCents, string $idempotencyKey): BidOutcome
    {
        $this->gate->assertCanBid($bidderId);
        $itemId = $this->items->idOf($itemPublicId) ?? throw new ItemNotFound;

        [$outcome, $events] = $this->tx->run(function () use ($bidderId, $itemId, $amountCents, $idempotencyKey): array {
            $item = $this->items->lockForUpdate($itemId);

            // Depois da trava: o segundo clique espera o primeiro terminar e encontra o lance gravado.
            $existing = $this->items->findByIdempotencyKey($bidderId, $idempotencyKey);
            if ($existing !== null) {
                return [new BidOutcome($existing->publicId, true, ItemState::of($item, $bidderId)), []];
            }

            $item->placeBid($bidderId, Money::fromCents($amountCents), $this->clock->now(), $this->auctions->settingsOf($item->auctionId), $idempotencyKey);
            $this->items->save($item);
            $stored = $this->items->findByIdempotencyKey($bidderId, $idempotencyKey);

            return [new BidOutcome($stored->publicId ?? '', false, ItemState::of($item, $bidderId)), $item->releaseEvents()];
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }

        return $outcome;
    }
}
