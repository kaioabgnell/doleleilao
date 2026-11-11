<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();
});

function viaCepPaulista(): array
{
    return [
        'cep' => '01310-100', 'logradouro' => 'Avenida Paulista', 'complemento' => 'de 612 a 1510 - lado par',
        'bairro' => 'Bela Vista', 'localidade' => 'São Paulo', 'uf' => 'SP', 'ibge' => '3550308',
    ];
}

/** Endereço completo como o formulário manda. */
function addressPayload(array $overrides = []): array
{
    return [
        'postal_code' => '01310-100', 'street' => 'Avenida Paulista', 'number' => '1000', 'complement' => 'Sala 12',
        'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP', ...$overrides,
    ];
}

it('busca o endereço do CEP no ViaCEP e guarda em cache', function () {
    Http::fake(['viacep.com.br/*' => Http::response(viaCepPaulista())]);

    $this->getJson('/api/v1/postal-codes/01310100')
        ->assertOk()
        ->assertExactJson([
            'postal_code' => '01310-100', 'street' => 'Avenida Paulista', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP',
        ]);
    $this->getJson('/api/v1/postal-codes/01310-100')->assertOk();

    Http::assertSentCount(1);
});

it('usa a BrasilAPI quando o ViaCEP está fora do ar', function () {
    Http::fake([
        'viacep.com.br/*' => fn () => throw new ConnectionException('timeout'),
        'brasilapi.com.br/*' => Http::response(['cep' => '01310100', 'state' => 'SP', 'city' => 'São Paulo', 'neighborhood' => 'Bela Vista', 'street' => 'Avenida Paulista']),
    ]);

    $this->getJson('/api/v1/postal-codes/01310100')->assertOk()->assertJsonPath('street', 'Avenida Paulista');
});

it('CEP geral de cidade vem sem rua e sem bairro', function () {
    Http::fake(['viacep.com.br/*' => Http::response(['cep' => '13165-000', 'logradouro' => '', 'bairro' => '', 'localidade' => 'Engenheiro Coelho', 'uf' => 'SP'])]);

    $this->getJson('/api/v1/postal-codes/13165000')
        ->assertOk()
        ->assertJsonPath('street', '')
        ->assertJsonPath('city', 'Engenheiro Coelho');
});

it('responde 404 quando nenhum serviço conhece o CEP', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response(['erro' => 'true']),
        'brasilapi.com.br/*' => Http::response(['message' => 'CEP não encontrado'], 404),
    ]);

    $this->getJson('/api/v1/postal-codes/99999999')
        ->assertNotFound()
        ->assertJsonPath('detail', 'Não achamos este CEP. Confira os números.');
});

it('responde 503 quando os dois serviços estão fora e não guarda o erro', function () {
    $down = true;
    Http::fake(function () use (&$down) {
        return $down ? Http::response('erro', 500) : Http::response(viaCepPaulista());
    });

    $this->getJson('/api/v1/postal-codes/01310100')
        ->assertStatus(503)
        ->assertJsonPath('detail', 'Não conseguimos buscar o CEP agora. Preencha o endereço abaixo.');

    $down = false;
    $this->getJson('/api/v1/postal-codes/01310100')->assertOk();
});

it('recusa CEP com formato errado sem chamar o serviço', function () {
    Http::fake();

    $this->getJson('/api/v1/postal-codes/1234')
        ->assertUnprocessable()
        ->assertJsonPath('errors.postal_code.0', 'CEP inválido. Use os 8 números, por exemplo 01310-100.');
    Http::assertNothingSent();
});

it('o leiloeiro guarda o endereço de envio, que não aparece na loja pública', function () {
    $auth = liveStore();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['address' => addressPayload(['state' => 'sp'])])
        ->assertOk()
        ->assertJsonPath('address.postal_code', '01310-100')
        ->assertJsonPath('address.state', 'SP')
        ->assertJsonPath('address.complement', 'Sala 12');

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront')->assertJsonPath('address.number', '1000');
    $this->withoutToken()->getJson('/api/v1/storefronts/leiloes-silva')->assertOk()->assertJsonMissingPath('address');
});

it('salvar outros dados da loja não mexe no endereço; CEP vazio apaga', function () {
    $auth = registerAuctioneer();
    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['address' => addressPayload()])->assertOk();

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['template' => 'watches'])
        ->assertOk()->assertJsonPath('address.street', 'Avenida Paulista');

    asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', ['address' => ['postal_code' => '', 'street' => 'Sobra']])
        ->assertOk()->assertJsonPath('address', null);
});

it('aponta os campos do endereço que faltam junto com os outros erros da loja', function () {
    $auth = registerAuctioneer();

    $errors = asAuctioneer($auth)->patchJson('/api/v1/auctioneer/storefront', [
        'contact_phone' => '123',
        'address' => addressPayload(['postal_code' => '0131', 'number' => '', 'state' => 'XX']),
    ])->assertUnprocessable()->json('errors');

    expect($errors)->toMatchArray([
        'contact_phone' => ['Telefone inválido. Use DDD + número, por exemplo (11) 98765-4321.'],
        'address.postal_code' => ['CEP inválido. Use os 8 números, por exemplo 01310-100.'],
        'address.number' => ['Informe o número. Sem número? Escreva S/N.'],
        'address.state' => ['Escolha o estado (UF).'],
    ]);

    asAuctioneer($auth)->getJson('/api/v1/auctioneer/storefront')->assertJsonPath('address', null);
});

it('o participante guarda o endereço de entrega em "Seus dados"', function () {
    liveStore();
    app('auth')->forgetGuards();
    $auth = registerBidder();

    $this->withToken($auth['token'])->getJson('/api/v1/bidders/me')->assertJsonPath('address', null);

    $this->withToken($auth['token'])->patchJson('/api/v1/bidders/me', [
        'name' => 'Ana Paula Souza', 'nickname' => 'Ana S.', 'phone' => '(21) 99876-5432',
        'address' => addressPayload(['complement' => '']),
    ])->assertOk()
        ->assertJsonPath('address.city', 'São Paulo')
        ->assertJsonPath('address.complement', null);

    expect($this->withToken($auth['token'])->patchJson('/api/v1/bidders/me', ['address' => addressPayload(['district' => ''])])
        ->assertUnprocessable()->json('errors'))->toBe(['address.district' => ['Informe o bairro.']]);

    $this->withToken($auth['token'])->patchJson('/api/v1/bidders/me', ['address' => null])
        ->assertOk()->assertJsonPath('address', null);
});
