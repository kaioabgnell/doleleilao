<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\InvalidStoreTerms;
use DoleLeilao\Storefront\Domain\StoreTerms;

it('tem um texto padrão que cobre regras, pagamento, envio, falta de pagamento e banimento', function () {
    $text = StoreTerms::default()->text;

    expect($text)
        ->toContain('## Lances')
        ->toContain('## Pagamento')
        ->toContain('## Retirada e envio')
        ->toContain('## Se o pagamento não for feito')
        ->toContain('## Banimento')
        ->toContain('{loja}');
});

it('normaliza quebras de linha e espaços nas pontas', function () {
    expect(StoreTerms::from("  ## Regras\r\n\r\nTexto  ")->text)->toBe("## Regras\n\nTexto");
});

it('recusa termos vazios ou longos demais', function (string $text, string $message) {
    expect(fn () => StoreTerms::from($text))->toThrow(InvalidStoreTerms::class, $message);
})->with([
    ['   ', 'Escreva os termos'],
    [str_repeat('a', StoreTerms::MAX + 1), 'até 20.000 caracteres'],
]);

it('troca os campos conhecidos e mantém os desconhecidos', function () {
    $terms = StoreTerms::from('{loja} atende pelo {whatsapp} e {email}. {outro}');

    expect($terms->render(['loja' => 'Relojoaria Demo', 'whatsapp' => '(11) 99999-0000', 'email' => 'contato@demo.com.br']))
        ->toBe('Relojoaria Demo atende pelo (11) 99999-0000 e contato@demo.com.br. {outro}');
});

it('campo sem valor some do texto', function () {
    expect(StoreTerms::from('Fale pelo {whatsapp}.')->render([]))->toBe('Fale pelo .');
});
