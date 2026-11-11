<?php

declare(strict_types=1);

use DoleLeilao\Billing\Domain\CardBrand;
use DoleLeilao\Billing\Domain\CreditCard;
use DoleLeilao\Billing\Domain\InvalidCard;
use DoleLeilao\Shared\Domain\RuleViolations;

/*
 * Cartão de crédito do leiloeiro (Configurações → Pagamento). Ainda sem gateway: o número é
 * validado e só a bandeira, os 4 últimos dígitos, o nome e a validade ficam guardados.
 */

/** Completa o número com o dígito verificador (Luhn). */
function withLuhn(string $partial): string
{
    $sum = 0;
    foreach (str_split(strrev($partial)) as $i => $char) {
        $digit = (int) $char;
        if ($i % 2 === 0) {
            $digit *= 2;
            $digit = $digit > 9 ? $digit - 9 : $digit;
        }
        $sum += $digit;
    }

    return $partial.(string) ((10 - $sum % 10) % 10);
}

function cardToday(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('UTC'));
}

function newCard(string $number = '4111 1111 1111 1111', string $holder = 'Maria Silva', string $expiry = '12/30', string $cvv = '123'): CreditCard
{
    return CreditCard::register($number, $holder, $expiry, $cvv, cardToday());
}

describe('cartão do leiloeiro', function () {
    it('guarda só a bandeira, os 4 últimos dígitos, o nome e a validade', function () {
        $card = newCard('4111 1111-1111 1111', '  maria   da silva ', '07/2031');

        expect($card->brand)->toBe(CardBrand::Visa)
            ->and($card->last4)->toBe('1111')
            ->and($card->holderName)->toBe('MARIA DA SILVA')
            ->and($card->expMonth)->toBe(7)
            ->and($card->expYear)->toBe(2031)
            ->and(get_object_vars($card))->not->toHaveKey('number')
            ->and(serialize($card))->not->toContain('41111111');
    });

    it('reconhece as bandeiras aceitas', function (string $number, CardBrand $brand, string $cvv) {
        expect(newCard($number, cvv: $cvv)->brand)->toBe($brand);
    })->with([
        'Visa' => ['4111111111111111', CardBrand::Visa, '123'],
        'Mastercard' => ['5555555555554444', CardBrand::Mastercard, '123'],
        'Mastercard série 2' => [withLuhn('222300000000000'), CardBrand::Mastercard, '123'],
        'American Express' => ['378282246310005', CardBrand::Amex, '1234'],
        'Diners' => ['30569309025904', CardBrand::Diners, '123'],
        'Elo (636368)' => [withLuhn('636368000000000'), CardBrand::Elo, '123'],
        'Elo (começa com 4)' => [withLuhn('438935000000000'), CardBrand::Elo, '123'],
        'Elo (5067)' => [withLuhn('506726000000000'), CardBrand::Elo, '123'],
        'Hipercard' => [withLuhn('606282000000000'), CardBrand::Hipercard, '123'],
    ]);

    it('recusa número com dígito verificador errado', function () {
        newCard('4111 1111 1111 1112');
    })->throws(InvalidCard::class, 'Confira o número do cartão.');

    it('recusa bandeira desconhecida', function () {
        newCard(withLuhn('900000000000000'));
    })->throws(InvalidCard::class, 'Não reconhecemos a bandeira');

    it('recusa cartão vencido e aceita até o último dia do mês da validade', function () {
        expect(newCard(expiry: '11/26')->expMonth)->toBe(11);
        newCard(expiry: '10/26');
    })->throws(InvalidCard::class, 'Este cartão está vencido.');

    it('recusa validade em formato errado', function (string $expiry) {
        newCard(expiry: $expiry);
    })->throws(InvalidCard::class)->with(['13/30', '00/30', '1230', 'aa/bb', '12/60']);

    it('exige 3 dígitos no código de segurança (4 no American Express)', function () {
        expect(fn () => newCard(cvv: '12'))->toThrow(InvalidCard::class, 'O código de segurança tem 3 dígitos.')
            ->and(fn () => newCard('378282246310005', cvv: '123'))->toThrow(InvalidCard::class, 'O código de segurança tem 4 dígitos.');
    });

    it('exige o nome como está no cartão', function () {
        newCard(holder: 'M4ria');
    })->throws(InvalidCard::class, 'Digite o nome como está impresso no cartão.');

    it('aponta todos os campos com erro de uma vez', function () {
        try {
            CreditCard::register('123', '', '99/99', '', cardToday());
            $this->fail('Deveria recusar.');
        } catch (RuleViolations $e) {
            expect(array_keys($e->toArray()))->toBe(['number', 'holder_name', 'expiry', 'cvv']);
        }
    });
});
