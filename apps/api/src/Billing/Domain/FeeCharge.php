<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Auction\Domain\FeePayer;
use DoleLeilao\Shared\Domain\Money;

/**
 * Débito da taxa no cartão do leiloeiro, um por lote encerrado (D-54): a soma das taxas dos itens
 * arrematados. "Quem paga" (`feePayer`) vem do lote; o débito é sempre no cartão do leiloeiro.
 */
final readonly class FeeCharge
{
    /** @param non-empty-list<ChargeLine> $lines */
    private function __construct(
        public int $auctionId,
        public int $auctioneerId,
        public string $auctionName,
        public FeePayer $feePayer,
        public array $lines,
        public ?CreditCard $card,
        public FeeChargeStatus $status,
        public \DateTimeImmutable $createdAt,
    ) {}

    /**
     * Nulo quando não há nada a cobrar (nenhum item arrematado, período gratuito ou taxa zero).
     * `card` é o cartão cadastrado na hora (nulo só em lote agendado antes de o cartão ser exigido).
     *
     * @param  list<ChargeLine>  $lines
     */
    public static function forAuction(int $auctionId, int $auctioneerId, string $auctionName, FeePayer $feePayer, array $lines, ?CreditCard $card, \DateTimeImmutable $now): ?self
    {
        $lines = array_values(array_filter($lines, static fn (ChargeLine $l): bool => $l->fee->fee->cents > 0));
        if ($lines === []) {
            return null;
        }

        return new self($auctionId, $auctioneerId, $auctionName, $feePayer, $lines, $card, FeeChargeStatus::Pending, $now);
    }

    public function total(): Money
    {
        return array_reduce($this->lines, static fn (Money $sum, ChargeLine $l): Money => $sum->add($l->fee->fee), Money::zero());
    }
}
