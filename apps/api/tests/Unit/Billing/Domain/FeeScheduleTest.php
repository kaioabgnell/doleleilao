<?php

declare(strict_types=1);

use DoleLeilao\Auction\Domain\FeePayer;
use DoleLeilao\Billing\Domain\CardBrand;
use DoleLeilao\Billing\Domain\ChargeLine;
use DoleLeilao\Billing\Domain\CreditCard;
use DoleLeilao\Billing\Domain\FeeCharge;
use DoleLeilao\Billing\Domain\FeeChargeStatus;
use DoleLeilao\Billing\Domain\FeeSchedule;
use DoleLeilao\Billing\Domain\FeeTier;
use DoleLeilao\Shared\Domain\Money;

/*
 * Taxa sobre item arrematado (D-47): alíquota única pela faixa do valor, a partir de 01/12/2026
 * (00:00 de Brasília = 03:00 UTC). Até lá, a plataforma é gratuita.
 */

function utc(string $at): DateTimeImmutable
{
    return new DateTimeImmutable($at, new DateTimeZone('UTC'));
}

function reais(string $value): Money
{
    return Money::fromDecimalString($value);
}

/** As faixas iniciais, como estão na migração. */
function d47(): FeeSchedule
{
    $from = utc('2026-12-01 03:00:00');

    return new FeeSchedule([
        new FeeTier(Money::zero(), reais('100,00'), 750, $from),
        new FeeTier(reais('100,01'), reais('500,00'), 600, $from),
        new FeeTier(reais('500,01'), reais('1.000,00'), 500, $from),
        new FeeTier(reais('1.000,01'), null, 250, $from),
    ]);
}

describe('faixas da taxa (D-47)', function () {
    it('aplica a alíquota da faixa sobre o valor inteiro do arremate', function (string $amount, int $rate, string $fee) {
        $applied = d47()->feeFor(reais($amount), utc('2026-12-05 23:00:00'));

        expect($applied?->rateBps)->toBe($rate)
            ->and($applied?->fee->format())->toBe($fee)
            ->and($applied?->base->format())->toBe(reais($amount)->format());
    })->with([
        'R$ 100,00 → 7,5%' => ['100,00', 750, 'R$ 7,50'],
        'R$ 100,01 → 6%' => ['100,01', 600, 'R$ 6,00'],
        'R$ 200,00 → 6%' => ['200,00', 600, 'R$ 12,00'],
        'R$ 500,00 → 6%' => ['500,00', 600, 'R$ 30,00'],
        'R$ 500,01 → 5%' => ['500,01', 500, 'R$ 25,00'],
        'R$ 800,00 → 5%' => ['800,00', 500, 'R$ 40,00'],
        'R$ 1.000,00 → 5%' => ['1.000,00', 500, 'R$ 50,00'],
        'R$ 1.000,01 → 2,5%' => ['1.000,01', 250, 'R$ 25,00'],
        'R$ 12.345,67 → 2,5%' => ['12.345,67', 250, 'R$ 308,64'],
    ]);

    it('arredonda meio centavo para cima', function () {
        // 7,5% de R$ 0,10 = 0,75 centavo → 1 centavo; 7,5% de R$ 0,06 = 0,45 centavo → 0.
        expect(d47()->feeFor(reais('0,10'), utc('2026-12-05 00:00:00'))?->fee->cents)->toBe(1)
            ->and(d47()->feeFor(reais('0,06'), utc('2026-12-05 00:00:00'))?->fee->cents)->toBe(0);
    });

    it('não cobra nada antes de 01/12/2026 (período gratuito)', function () {
        expect(d47()->feeFor(reais('800,00'), utc('2026-12-01 02:59:59')))->toBeNull()
            ->and(d47()->feeFor(reais('800,00'), utc('2026-12-01 03:00:00')))->not->toBeNull();
    });

    it('usa a faixa vigente na hora do arremate quando as faixas mudam', function () {
        $schedule = new FeeSchedule([
            new FeeTier(Money::zero(), null, 750, utc('2026-12-01 03:00:00'), utc('2027-01-01 03:00:00')),
            new FeeTier(Money::zero(), null, 500, utc('2027-01-01 03:00:00')),
        ]);

        expect($schedule->feeFor(reais('100,00'), utc('2026-12-31 23:00:00'))?->rateBps)->toBe(750)
            ->and($schedule->feeFor(reais('100,00'), utc('2027-01-01 03:00:00'))?->rateBps)->toBe(500);
    });

    it('mostra as faixas em vigor ou, no período gratuito, as próximas', function () {
        expect(array_map(fn (FeeTier $t) => $t->rateBps, d47()->inEffect(utc('2026-11-20 12:00:00'))))->toBe([750, 600, 500, 250])
            ->and(array_map(fn (FeeTier $t) => $t->rateBps, d47()->inEffect(utc('2027-03-01 12:00:00'))))->toBe([750, 600, 500, 250])
            ->and((new FeeSchedule([]))->inEffect(utc('2026-11-20 12:00:00')))->toBe([]);
    });

    it('recusa faixa com alíquota fora de 0% a 100% ou valor máximo menor que o mínimo', function () {
        expect(fn () => new FeeTier(Money::zero(), null, 10_001, utc('2026-12-01 03:00:00')))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new FeeTier(reais('10,00'), reais('9,99'), 500, utc('2026-12-01 03:00:00')))->toThrow(InvalidArgumentException::class);
    });
});

describe('débito no cartão por lote', function () {
    it('soma as taxas dos itens arrematados num débito pendente', function () {
        $at = utc('2026-12-05 23:00:00');
        $lines = [];
        foreach ([[1, 'Produto A', '100,00'], [2, 'Produto B', '200,00'], [3, 'Produto C', '800,00']] as [$award, $title, $amount]) {
            $lines[] = new ChargeLine($award, $title, d47()->feeFor(reais($amount), $at) ?? throw new LogicException);
        }

        $card = new CreditCard(CardBrand::Visa, '1111', 'MARIA SILVA', 12, 2030);
        $charge = FeeCharge::forAuction(7, 9, 'Leilão X', FeePayer::Auctioneer, $lines, $card, $at);

        expect($charge?->total()->format())->toBe('R$ 59,50')
            ->and($charge?->status)->toBe(FeeChargeStatus::Pending)
            ->and($charge?->lines)->toHaveCount(3)
            ->and($charge?->card?->last4)->toBe('1111');
    });

    it('não gera débito sem taxa a cobrar', function () {
        $free = new ChargeLine(1, 'Produto A', d47()->feeFor(reais('0,06'), utc('2026-12-05 00:00:00')) ?? throw new LogicException);

        expect(FeeCharge::forAuction(7, 9, 'Leilão X', FeePayer::Bidder, [], null, utc('2026-12-05 00:00:00')))->toBeNull()
            ->and(FeeCharge::forAuction(7, 9, 'Leilão X', FeePayer::Bidder, [$free], null, utc('2026-12-05 00:00:00')))->toBeNull();
    });
});
