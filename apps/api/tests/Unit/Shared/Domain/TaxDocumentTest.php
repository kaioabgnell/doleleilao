<?php

declare(strict_types=1);

use DoleLeilao\Shared\Domain\InvalidTaxDocument;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Domain\TaxDocumentType;

describe('CPF', function () {
    it('aceita CPF válido com ou sem máscara e guarda só os dígitos', function (string $input) {
        $doc = TaxDocument::cpf($input);

        expect($doc->type)->toBe(TaxDocumentType::Cpf)
            ->and($doc->value)->toBe('52998224725');
    })->with(['529.982.247-25', '52998224725', ' 529 982 247 25 ']);

    it('recusa CPF com dígito verificador errado, tamanho errado ou dígitos repetidos', function (string $input) {
        TaxDocument::cpf($input);
    })->with(['529.982.247-24', '5299822472', '111.111.111-11', '', 'abc'])
        ->throws(InvalidTaxDocument::class);

    it('mascara o CPF para a interface', function () {
        expect(TaxDocument::cpf('52998224725')->masked())->toBe('***.982.247-**');
    });

    it('formata o CPF completo', function () {
        expect(TaxDocument::cpf('52998224725')->formatted())->toBe('529.982.247-25');
    });
});

describe('CNPJ', function () {
    it('aceita CNPJ numérico válido', function () {
        $doc = TaxDocument::cnpj('11.222.333/0001-81');

        expect($doc->type)->toBe(TaxDocumentType::Cnpj)
            ->and($doc->value)->toBe('11222333000181')
            ->and($doc->formatted())->toBe('11.222.333/0001-81');
    });

    it('aceita CNPJ alfanumérico (Receita Federal, a partir de julho de 2026)', function () {
        $doc = TaxDocument::cnpj('12.abc.345/01de-35');

        expect($doc->value)->toBe('12ABC34501DE35')
            ->and($doc->formatted())->toBe('12.ABC.345/01DE-35');
    });

    it('recusa CNPJ inválido', function (string $input) {
        TaxDocument::cnpj($input);
    })->with(['11.222.333/0001-82', '1122233300018', '00.000.000/0000-00', '12.ABC.345/01DE-3A'])
        ->throws(InvalidTaxDocument::class);

    it('mascara o CNPJ para a interface', function () {
        expect(TaxDocument::cnpj('11222333000181')->masked())->toBe('11.222.***/****-81');
    });
});

it('cria pelo tipo informado', function () {
    expect(TaxDocument::of(TaxDocumentType::Cnpj, '11222333000181')->type)->toBe(TaxDocumentType::Cnpj)
        ->and(TaxDocument::of(TaxDocumentType::Cpf, '52998224725')->type)->toBe(TaxDocumentType::Cpf);
});

it('informa o campo do erro', function () {
    try {
        TaxDocument::cpf('123');
    } catch (InvalidTaxDocument $e) {
        expect($e->field)->toBe('document')
            ->and($e->getMessage())->toBe('CPF inválido.');

        return;
    }
    test()->fail('Deveria ter lançado InvalidTaxDocument.');
});
