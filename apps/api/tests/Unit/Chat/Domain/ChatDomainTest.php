<?php

declare(strict_types=1);

use DoleLeilao\Chat\Domain\ChatAccess;
use DoleLeilao\Chat\Domain\ChatSettings;
use DoleLeilao\Chat\Domain\InvalidMessage;
use DoleLeilao\Chat\Domain\MessageBody;
use DoleLeilao\Chat\Domain\MuteDuration;
use DoleLeilao\Chat\Domain\Participant;
use DoleLeilao\Chat\Domain\WriteDenied;

/* Chat do auditório: RF-D03, RF-F05 a RF-F08, RF-F10, specs/04 (texto puro, limites). */

function chatAt(int $minutes = 0): DateTimeImmutable
{
    return (new DateTimeImmutable('2026-11-21 23:00:00', new DateTimeZone('UTC')))->modify("+{$minutes} minutes");
}

describe('texto da mensagem', function () {
    it('limpa espaços, junta linhas em branco e tira caracteres de controle', function () {
        expect(MessageBody::from("  Oi,\x07 pessoal!\n\n\n\nBoa sorte  ")->value)->toBe("Oi, pessoal!\n\nBoa sorte");
    });

    it('recusa vazia e acima de 500 caracteres', function (string $text, string $message) {
        try {
            MessageBody::from($text);
            $this->fail('Esperava InvalidMessage');
        } catch (InvalidMessage $e) {
            expect($e->field)->toBe('body')->and($e->getMessage())->toBe($message);
        }
    })->with([
        'vazia' => ["  \n ", 'Escreva uma mensagem.'],
        'longa' => [str_repeat('á', 501), 'A mensagem pode ter até 500 caracteres.'],
    ]);

    it('aceita exatamente 500 caracteres com acento e emoji', function () {
        expect(mb_strlen(MessageBody::from(str_repeat('é', 499).'👏')->value))->toBe(500);
    });

    it('trecho da citação tem até 80 caracteres numa linha só', function () {
        $excerpt = MessageBody::from(str_repeat('palavra ', 20)."\nfim")->excerpt();
        expect(mb_strlen($excerpt))->toBeLessThanOrEqual(80)->and($excerpt)->toEndWith('…')->not->toContain("\n");
    });
});

describe('histórico depois do leilão', function () {
    it('fica visível por 12 horas depois do fim e some depois', function () {
        $fim = chatAt();
        expect(ChatAccess::historyVisible(null, chatAt(60 * 24 * 30)))->toBeTrue()
            ->and(ChatAccess::historyVisible($fim, chatAt(60 * 12 - 1)))->toBeTrue()
            ->and(ChatAccess::historyVisible($fim, chatAt(60 * 12)))->toBeFalse()
            ->and(ChatAccess::historyVisible($fim, chatAt(60 * 48)))->toBeFalse();
    });
});

describe('quem vê o chat (RF-D03, RF-F10)', function () {
    it('visitante vê quando é para todos e não vê quando é só para logados', function () {
        expect(ChatAccess::canRead(ChatSettings::from(true, 'everyone', 'logged_in'), Participant::visitor()))->toBeTrue()
            ->and(ChatAccess::canRead(ChatSettings::from(true, 'logged_in', 'logged_in'), Participant::visitor()))->toBeFalse()
            ->and(ChatAccess::canRead(ChatSettings::from(true, 'logged_in', 'logged_in'), Participant::bidder(eligible: false)))->toBeTrue();
    });

    it('chat desligado não aparece para ninguém, nem para o leiloeiro escrever', function () {
        $off = ChatSettings::from(false, 'everyone', 'logged_in');
        expect(ChatAccess::canRead($off, Participant::bidder()))->toBeFalse()
            ->and(ChatAccess::writeDenial($off, Participant::auctioneer(), true, chatAt()))->toBe(WriteDenied::Disabled);
    });

    it('banido não vê o chat', function () {
        expect(ChatAccess::canRead(ChatSettings::from(true, 'everyone', 'logged_in'), Participant::bidder(banned: true)))->toBeFalse();
    });
});

describe('quem escreve (RF-D03)', function () {
    $everyone = ChatSettings::from(true, 'everyone', 'logged_in');
    $eligibleOnly = ChatSettings::from(true, 'everyone', 'eligible');

    it('precisa estar logado', function () use ($everyone) {
        expect(ChatAccess::writeDenial($everyone, Participant::visitor(), true, chatAt()))->toBe(WriteDenied::Login)
            ->and(ChatAccess::writeDenial($everyone, Participant::bidder(eligible: false), true, chatAt()))->toBeNull();
    });

    it('com "só cadastro completo", exige o cadastro completo', function () use ($eligibleOnly) {
        expect(ChatAccess::writeDenial($eligibleOnly, Participant::bidder(eligible: false), true, chatAt()))->toBe(WriteDenied::Incomplete)
            ->and(ChatAccess::writeDenial($eligibleOnly, Participant::bidder(), true, chatAt()))->toBeNull();
    });

    it('silenciado não escreve até o prazo acabar', function () use ($everyone) {
        $muted = Participant::bidder(mutedUntil: chatAt(15));
        expect(ChatAccess::writeDenial($everyone, $muted, true, chatAt(14)))->toBe(WriteDenied::Muted)
            ->and(ChatAccess::writeDenial($everyone, $muted, true, chatAt(15)))->toBeNull()
            ->and(ChatAccess::writeDenial($everyone, Participant::bidder(mutedForAuction: true), true, chatAt(500)))->toBe(WriteDenied::Muted);
    });

    it('banido não escreve', function () use ($everyone) {
        expect(ChatAccess::writeDenial($everyone, Participant::bidder(banned: true), true, chatAt()))->toBe(WriteDenied::Banned);
    });

    it('depois do leilão o chat fica só para leitura', function () use ($everyone) {
        expect(ChatAccess::writeDenial($everyone, Participant::bidder(), false, chatAt()))->toBe(WriteDenied::Closed)
            ->and(ChatAccess::writeDenial($everyone, Participant::auctioneer(), false, chatAt()))->toBe(WriteDenied::Closed);
    });

    it('o leiloeiro sempre escreve com o chat aberto', function () use ($eligibleOnly) {
        expect(ChatAccess::writeDenial($eligibleOnly, Participant::auctioneer(), true, chatAt()))->toBeNull();
    });

    it('cada recusa tem um texto para a tela', function () {
        expect(WriteDenied::Muted->message())->toBe('O leiloeiro silenciou você neste leilão.')
            ->and(WriteDenied::Incomplete->message())->toBe('Complete seu cadastro para escrever no chat.');
    });
});

describe('silenciar (RF-F08)', function () {
    it('aceita 5, 15 ou 60 minutos, ou até o fim do leilão', function () {
        expect(MuteDuration::minutes(15)->until(chatAt()))->toEqual(chatAt(15))
            ->and(MuteDuration::wholeAuction()->until(chatAt()))->toBeNull();
    });

    it('recusa outros prazos', function () {
        MuteDuration::minutes(7);
    })->throws(InvalidMessage::class);
});
