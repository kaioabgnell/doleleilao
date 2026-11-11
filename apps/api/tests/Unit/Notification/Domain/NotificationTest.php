<?php

declare(strict_types=1);

use DoleLeilao\Notification\Domain\AuctionRef;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\NotificationKind;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Shared\Domain\Money;

function auctioneerRecipient(): Recipient
{
    return Recipient::auctioneerUser(10, auctioneerId: 1);
}

function bidderRecipient(): Recipient
{
    return Recipient::bidder(20, auctioneerId: 1);
}

function saturdayAuction(): AuctionRef
{
    // 21/11/2026 às 20h em Brasília = 23h UTC
    return new AuctionRef('01jauction', 'Relógios de Sábado', new DateTimeImmutable('2026-11-21 23:00:00', new DateTimeZone('UTC')), '/relojoaria/leilao/relogios-de-sabado');
}

it('boas-vindas ao leiloeiro com os primeiros passos (RF-N04)', function () {
    $n = Notification::welcome(auctioneerRecipient());

    expect($n->kind)->toBe(NotificationKind::Welcome)
        ->and($n->title)->toBe('Boas-vindas ao Dole Leilão!')
        ->and($n->body)->toBe('Siga os primeiros passos para agendar seu primeiro leilão.')
        ->and($n->url)->toBe('/painel')
        ->and($n->dedupeKey)->toBe('welcome:auctioneer_user:10');
});

it('avisa o leiloeiro que a loja está no ar', function () {
    $n = Notification::storeLive(auctioneerRecipient(), 'doleleilao.com.br/relojoaria');

    expect($n->title)->toBe('Sua loja está no ar')
        ->and($n->body)->toBe('Compartilhe o link com seus clientes: doleleilao.com.br/relojoaria.')
        ->and($n->dedupeKey)->toBe('store_live:auctioneer_user:10');
});

it('participante: o leilão é amanhã e é hoje, com a hora de Brasília (RF-N03)', function () {
    $tomorrow = Notification::auctionTomorrow(bidderRecipient(), saturdayAuction());
    $today = Notification::auctionToday(bidderRecipient(), saturdayAuction());

    expect($tomorrow->title)->toBe('O leilão Relógios de Sábado é amanhã')
        ->and($tomorrow->body)->toBe('Começa amanhã às 20h. Você marcou que vai participar.')
        ->and($tomorrow->url)->toBe('/relojoaria/leilao/relogios-de-sabado')
        ->and($tomorrow->dedupeKey)->toBe('auction_tomorrow:01jauction:bidder:20')
        ->and($today->title)->toBe('O leilão Relógios de Sábado é hoje')
        ->and($today->body)->toBe('Começa hoje às 20h. Você marcou que vai participar.');
});

it('leiloeiro: o seu leilão é amanhã e é hoje', function () {
    expect(Notification::auctionTomorrow(auctioneerRecipient(), saturdayAuction())->title)->toBe('Seu leilão Relógios de Sábado é amanhã')
        ->and(Notification::auctionToday(auctioneerRecipient(), saturdayAuction())->body)->toBe('Começa hoje às 20h.');
});

it('mostra os minutos quando o leilão não começa na hora cheia', function () {
    $auction = new AuctionRef('a', 'Noturno', new DateTimeImmutable('2026-11-21 22:30:00', new DateTimeZone('UTC')), '/x/leilao/noturno');

    expect(Notification::auctionToday(bidderRecipient(), $auction)->body)->toStartWith('Começa hoje às 19h30.');
});

it('participante: venceu o leilão, com a data, o item e o valor (RF-N03)', function () {
    $n = Notification::auctionWon(
        bidderRecipient(),
        saturdayAuction(),
        'Rolex Datejust 1985',
        Money::fromCents(1245000),
        new DateTimeImmutable('2026-11-22 00:15:00', new DateTimeZone('UTC')),
        awardRef: '01jaward',
    );

    expect($n->title)->toBe('Você foi o vencedor no leilão Relógios de Sábado')
        ->and($n->body)->toBe('Em 21/11/2026 você arrematou Rolex Datejust 1985 por R$ 12.450,00.')
        ->and($n->dedupeKey)->toBe('auction_won:01jaward');
});

it('participante: cadastro habilitado para o leilão (RF-N03)', function () {
    $n = Notification::participationEnabled(bidderRecipient(), saturdayAuction());

    expect($n->title)->toBe('Seu cadastro está habilitado para participar do leilão Relógios de Sábado')
        ->and($n->body)->toBe('Começa em 21/11/2026 às 20h. Vamos avisar na véspera e no dia.')
        ->and($n->dedupeKey)->toBe('participation_enabled:01jauction:bidder:20');
});

it('leiloeiro: item arrematado e novo participante (RF-N04)', function () {
    $sold = Notification::itemSold(auctioneerRecipient(), saturdayAuction(), 'Rolex Datejust 1985', Money::fromCents(1245000), awardRef: '01jaward');
    $joined = Notification::newParticipant(auctioneerRecipient(), saturdayAuction(), 'Ana Souza', participationRef: '77');

    expect($sold->title)->toBe('Rolex Datejust 1985 foi arrematado por R$ 12.450,00')
        ->and($sold->body)->toBe('No leilão Relógios de Sábado.')
        ->and($joined->title)->toBe('Novo participante no leilão Relógios de Sábado')
        ->and($joined->body)->toBe('Ana Souza vai participar.');
});

it('cada tipo de aviso só vale para o público certo', function () {
    Notification::auctionWon(auctioneerRecipient(), saturdayAuction(), 'x', Money::fromCents(100), new DateTimeImmutable, 'a');
})->throws(InvalidArgumentException::class, 'auction_won é só para bidder');
