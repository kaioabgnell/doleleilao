<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('a loja começa sem modelo e com o contato do cadastro', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront')
        ->assertOk()
        ->assertJsonPath('template', null)
        ->assertJsonPath('contact_phone', '(11) 98765-4321')
        ->assertJsonPath('contact_email', 'maria@leiloessilva.com.br')
        ->assertJsonPath('display_name', 'Leilões Silva')
        ->assertJsonPath('is_public', false);
});

it('escolher o modelo aplica as cores, o fundo e a fonte padrão dele (RF-B01)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches'])
        ->assertOk()
        ->assertJsonPath('template', 'watches')
        ->assertJsonPath('primary_color', 'copper')
        ->assertJsonPath('accent_color', 'dark-gold')
        ->assertJsonPath('background', 'ivory')
        ->assertJsonPath('heading_font', 'playfair');
});

it('personaliza cores, fundo e fonte dentro das listas fechadas (RF-B03 a RF-B05)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', [
        'template' => 'fashion', 'primary_color' => 'wine', 'accent_color' => 'black', 'background' => 'sand', 'heading_font' => 'poppins',
    ])->assertOk()
        ->assertJsonPath('primary_color', 'wine')
        ->assertJsonPath('background', 'sand')
        ->assertJsonPath('heading_font', 'poppins');
});

it('bloqueia cor sem contraste com o fundo (D-13)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', [
        'template' => 'watches', 'background' => 'ebony', 'primary_color' => 'black', 'accent_color' => 'copper',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.primary_color.0', 'Esta cor não tem contraste suficiente com o fundo escolhido.');
});

it('trocar só o fundo ajusta as cores que perderiam contraste', function () {
    $auth = registerAuctioneer();
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches', 'primary_color' => 'black']);

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['background' => 'ebony'])
        ->assertOk()
        ->assertJsonPath('background', 'ebony')
        ->assertJsonPath('primary_color', 'copper');
});

it('recusa valores fora das listas e cores antes do modelo', function (array $payload, string $field, string $message) {
    $auth = registerAuctioneer();

    $response = asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', $payload)->assertUnprocessable();

    expect($response->json('errors')[$field][0] ?? null)->toBe($message);
})->with([
    [['template' => 'carros'], 'template', 'Modelo de loja inválido.'],
    [['primary_color' => 'copper'], 'template', 'Escolha o modelo da loja primeiro.'],
    [['template' => 'watches', 'primary_color' => '#FF0000'], 'primary_color', 'Cor inválida.'],
    [['template' => 'electronics', 'background' => 'ebony'], 'background', 'Este fundo não está disponível neste modelo.'],
    [['template' => 'watches', 'heading_font' => 'comic-sans'], 'heading_font', 'Fonte inválida.'],
    [['contact_phone' => '123'], 'contact_phone', 'Telefone inválido. Use DDD + número, por exemplo (11) 98765-4321.'],
    [['socials' => ['instagram' => 'http://instagram.com/x']], 'socials.instagram', 'Informe um endereço válido começando com https://.'],
]);

it('atualiza e apaga o contato e as redes sociais (RF-B08)', function () {
    $auth = registerAuctioneer();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', [
        'contact_phone' => '21 3456-7890',
        'contact_email' => 'Contato@LeiloesSilva.com.br',
        'socials' => ['instagram' => 'https://instagram.com/leiloessilva', 'website' => null],
    ])->assertOk()
        ->assertJsonPath('contact_phone', '(21) 3456-7890')
        ->assertJsonPath('contact_email', 'contato@leiloessilva.com.br')
        ->assertJsonPath('socials.instagram', 'https://instagram.com/leiloessilva')
        ->assertJsonMissingPath('socials.website');

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['contact_phone' => null, 'socials' => []])
        ->assertOk()
        ->assertJsonPath('contact_phone', null)
        ->assertJsonPath('socials', []);
});

it('a loja fica pública com e-mail validado e modelo escolhido (RF-A04)', function () {
    $auth = registerAuctioneer(verified: true);

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'electronics'])
        ->assertOk()
        ->assertJsonPath('is_public', true);
});

it('cada leiloeiro só vê e altera a própria loja (multi-tenant)', function () {
    $maria = registerAuctioneer();
    $joao = registerAuctioneer(['email' => 'joao@ex.com', 'document' => '12.ABC.345/01DE-35', 'slug' => 'joao-leiloes', 'phone' => '(21) 99999-0000']);

    asAuctioneer($joao)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'fashion'])->assertOk();
    app('auth')->forgetGuards();

    asAuctioneer($maria)->getJson('/api/v1/auctioneer/storefront')
        ->assertJsonPath('template', null)
        ->assertJsonPath('contact_phone', '(11) 98765-4321');
});
