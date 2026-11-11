<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\SlugChangePolicy;
use DoleLeilao\Storefront\Domain\SlugChangeTooSoon;

it('permite a primeira troca a qualquer momento', function () {
    $policy = new SlugChangePolicy;

    expect($policy->nextChangeAllowedAt(null))->toBeNull();
    $policy->assertCanChange(null, new DateTimeImmutable('2026-11-07 10:00:00'));
});

it('permite 1 troca a cada 30 dias (RF-A06)', function () {
    $policy = new SlugChangePolicy;
    $last = new DateTimeImmutable('2026-11-01 10:00:00');

    expect($policy->nextChangeAllowedAt($last)?->format('Y-m-d H:i'))->toBe('2026-12-01 10:00');
    $policy->assertCanChange($last, new DateTimeImmutable('2026-12-01 10:00:00'));
});

it('recusa a troca antes de 30 dias, dizendo quando poderá trocar', function () {
    $policy = new SlugChangePolicy;

    expect(fn () => $policy->assertCanChange(
        new DateTimeImmutable('2026-11-01 10:00:00'),
        new DateTimeImmutable('2026-11-20 10:00:00'),
    ))->toThrow(fn (SlugChangeTooSoon $e) => expect($e->field)->toBe('slug')
        ->and($e->allowedAt->format('Y-m-d'))->toBe('2026-12-01'));
});

it('o endereço antigo redireciona por 90 dias', function () {
    expect((new SlugChangePolicy)->redirectExpiresAt(new DateTimeImmutable('2026-11-07 10:00:00'))->format('Y-m-d'))
        ->toBe('2027-02-05');
});
