<?php

declare(strict_types=1);

use DoleLeilao\Auction\Domain\AwardStatus;
use DoleLeilao\Auction\Domain\InvalidAuction;
use DoleLeilao\Auction\Domain\WinnerMessageTemplate;
use DoleLeilao\Shared\Domain\Money;

/* Pós-leilão: arrematações (RF-E08) e mensagem ao vencedor (RF-G05, D-25). */

describe('status da arrematação (RF-E08)', function () {
    it('começa aguardando contato e tem os rótulos da tela', function () {
        expect(AwardStatus::initial())->toBe(AwardStatus::AwaitingContact)
            ->and(array_map(fn (AwardStatus $s) => $s->label(), AwardStatus::cases()))
            ->toBe(['Aguardando contato', 'Em negociação', 'Pago', 'Entregue', 'Desistência']);
    });

    it('entregue e desistência encerram o acompanhamento', function () {
        expect(AwardStatus::Delivered->isFinal())->toBeTrue()
            ->and(AwardStatus::Withdrawn->isFinal())->toBeTrue()
            ->and(AwardStatus::Paid->isFinal())->toBeFalse();
    });
});

describe('modelo da mensagem ao vencedor (RF-G05)', function () {
    $values = [
        'nome' => 'Ana Paula Souza',
        'apelido' => 'Ana S.',
        'item' => 'Rolex Datejust 1985',
        'valor' => Money::fromCents(1_700_000)->format(),
        'leilao' => 'Relógios de Coleção',
        'data' => '21/11/2026',
        'loja' => 'Relojoaria Demo',
        'whatsapp' => '(11) 98765-4321',
    ];

    it('o padrão já vem pronto para enviar', function () use ($values) {
        expect(WinnerMessageTemplate::default()->render($values))
            ->toBe("Olá, Ana Paula Souza! Parabéns: você arrematou Rolex Datejust 1985 no leilão Relógios de Coleção por R$ 17.000,00.\n\nVamos combinar o pagamento e a entrega? Responda esta mensagem ou chame a gente no WhatsApp (11) 98765-4321.\n\nRelojoaria Demo");
    });

    it('troca os campos e deixa o resto do texto como está', function () use ($values) {
        $t = WinnerMessageTemplate::from('Oi {apelido}, {item} saiu por {valor}. {desconhecido}');
        expect($t->render($values))->toBe('Oi Ana S., Rolex Datejust 1985 saiu por R$ 17.000,00. {desconhecido}');
    });

    it('lista os campos disponíveis para o painel', function () {
        expect(array_keys(WinnerMessageTemplate::FIELDS))->toBe(['nome', 'apelido', 'item', 'valor', 'leilao', 'data', 'loja', 'whatsapp']);
    });

    it('recusa vazio e acima de 2000 caracteres', function (string $text) {
        WinnerMessageTemplate::from($text);
    })->throws(InvalidAuction::class)->with(['   ', str_repeat('a', 2001)]);
});
