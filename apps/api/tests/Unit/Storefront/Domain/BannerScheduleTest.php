<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\BannerSchedule;
use DoleLeilao\Storefront\Domain\InvalidBannerSchedule;

$at = fn (string $d) => new DateTimeImmutable($d, new DateTimeZone('UTC'));

it('sem período, o banner aparece sempre que estiver ativo', function () use ($at) {
    $s = new BannerSchedule(null, null);

    expect($s->showsAt($at('2026-11-08 10:00'), active: true))->toBeTrue()
        ->and($s->showsAt($at('2026-11-08 10:00'), active: false))->toBeFalse();
});

it('respeita início e fim do período (RF-B06)', function () use ($at) {
    $s = new BannerSchedule($at('2026-11-10 00:00'), $at('2026-11-20 00:00'));

    expect($s->showsAt($at('2026-11-09 23:59'), true))->toBeFalse()
        ->and($s->showsAt($at('2026-11-10 00:00'), true))->toBeTrue()
        ->and($s->showsAt($at('2026-11-19 23:59'), true))->toBeTrue()
        ->and($s->showsAt($at('2026-11-20 00:00'), true))->toBeFalse();
});

it('recusa fim antes do início', function () use ($at) {
    new BannerSchedule($at('2026-11-20'), $at('2026-11-10'));
})->throws(InvalidBannerSchedule::class, 'O fim precisa ser depois do início.');
