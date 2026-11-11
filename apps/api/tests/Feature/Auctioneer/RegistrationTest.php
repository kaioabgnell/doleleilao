<?php

declare(strict_types=1);

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('cadastra o leiloeiro, devolve o token do painel e envia a validação de e-mail (RF-A01, RF-A04)', function () {
    $response = $this->postJson('/api/v1/auctioneers', auctioneerPayload());

    $response->assertCreated()
        ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email', 'phone'], 'auctioneer' => ['id', 'slug']])
        ->assertJsonPath('user.email', 'maria@leiloessilva.com.br')
        ->assertJsonPath('user.phone', '(11) 98765-4321')
        ->assertJsonPath('user.email_verified', false)
        ->assertJsonPath('auctioneer.company_name', 'Leilões Silva & Filhos Ltda')
        ->assertJsonPath('auctioneer.display_name', 'Leilões Silva')
        ->assertJsonPath('auctioneer.slug', 'leiloes-silva')
        ->assertJsonPath('auctioneer.store_url', 'http://localhost:3000/leiloes-silva')
        ->assertJsonPath('auctioneer.document_type', 'cnpj')
        ->assertJsonPath('auctioneer.document_masked', '11.222.***/****-81')
        ->assertJsonPath('auctioneer.status', 'pending');

    $user = AuctioneerUser::query()->sole();
    Notification::assertSentTo($user, VerifyAuctioneerEmail::class);
    expect($user->role)->toBe('owner')
        ->and($user->terms_accepted_at)->not->toBeNull();
});

it('guarda o CNPJ criptografado e a senha com Argon2id (RNF-08, RNF-09)', function () {
    $this->postJson('/api/v1/auctioneers', auctioneerPayload())->assertCreated();

    $row = DB::table('auctioneers')->first();
    $password = DB::table('auctioneer_users')->value('password');

    expect($row->document_enc)->not->toContain('11222333000181')
        ->and($row->document_hash)->toHaveLength(64)
        ->and(AuctioneerRecord::query()->sole()->document()->value)->toBe('11222333000181')
        ->and($password)->toStartWith('$argon2id$');
});

it('aceita o nome de exibição escolhido pelo leiloeiro (RF-A03)', function () {
    $this->postJson('/api/v1/auctioneers', auctioneerPayload(['display_name' => 'Casa Silva']))
        ->assertCreated()
        ->assertJsonPath('auctioneer.display_name', 'Casa Silva');
});

it('cadastra com CPF e com CNPJ alfanumérico', function (string $type, string $document, string $masked) {
    $this->postJson('/api/v1/auctioneers', auctioneerPayload(['document_type' => $type, 'document' => $document]))
        ->assertCreated()
        ->assertJsonPath('auctioneer.document_masked', $masked);
})->with([
    ['cpf', '529.982.247-25', '***.982.247-**'],
    ['cnpj', '12.ABC.345/01DE-35', '12.ABC.***/****-35'],
]);

it('devolve todos os erros de regra de uma vez, no formato problem+json', function () {
    $response = $this->postJson('/api/v1/auctioneers', auctioneerPayload([
        'document' => '11.222.333/0001-80',
        'slug' => 'painel',
        'phone' => '1234',
        'display_name' => 'Nome Com Três',
    ]));

    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 422)
        ->assertJsonPath('title', 'Dados inválidos')
        ->assertJsonPath('errors.document.0', 'CNPJ inválido.')
        ->assertJsonPath('errors.slug.0', 'Este endereço é reservado. Escolha outro.')
        ->assertJsonPath('errors.phone.0', 'Telefone inválido. Use DDD + número, por exemplo (11) 98765-4321.')
        ->assertJsonPath('errors.display_name.0', 'Use no máximo 2 palavras.');
});

it('valida os campos obrigatórios em português', function () {
    $this->postJson('/api/v1/auctioneers', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Preencha o seu nome.')
        ->assertJsonPath('errors.accept_terms.0', 'Você precisa aceitar os termos de uso e a política de privacidade.');
});

it('exige senha com pelo menos 8 caracteres, letras e números', function () {
    $this->postJson('/api/v1/auctioneers', auctioneerPayload(['password' => 'abcdefgh']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'A senha precisa ter pelo menos um número.');
});

it('não deixa repetir documento, e-mail nem endereço da loja (RF-A01, RF-A02)', function () {
    registerAuctioneer();

    $this->postJson('/api/v1/auctioneers', auctioneerPayload(['email' => 'MARIA@leiloessilva.com.br']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.document.0', 'Este CNPJ já tem uma loja cadastrada.')
        ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado. Entre com ele ou recupere a senha.')
        ->assertJsonPath('errors.slug.0', 'Este endereço já está em uso. Escolha outro.');
});

it('o mesmo documento com outra máscara continua sendo o mesmo', function () {
    registerAuctioneer(['document_type' => 'cpf', 'document' => '529.982.247-25']);

    $this->postJson('/api/v1/auctioneers', auctioneerPayload([
        'document_type' => 'cpf', 'document' => '52998224725', 'email' => 'outra@ex.com', 'slug' => 'outra-loja',
    ]))->assertUnprocessable()->assertJsonPath('errors.document.0', 'Este CPF já tem uma loja cadastrada.');
});

it('limita os cadastros por IP', function () {
    for ($i = 1; $i <= 10; $i++) {
        $this->postJson('/api/v1/auctioneers', [])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auctioneers', [])
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('detail', 'Muitas tentativas. Aguarde um pouco e tente de novo.');
});
