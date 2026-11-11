<?php

namespace Database\Seeders;

use DoleLeilao\Identity\Infrastructure\AuctioneerEmailVerifier;
use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Storefront\Application\RegisterAuctioneer;
use DoleLeilao\Storefront\Application\RegisterAuctioneerInput;
use Illuminate\Database\Seeder;

/**
 * Dados de desenvolvimento (só local): `bin/artisan migrate:fresh --seed`.
 * Leiloeiro de demonstração: demo@doleleilao.com.br / demo12345, loja em /relojoaria-demo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(RegisterAuctioneer $register, AuctioneerEmailVerifier $verifier): void
    {
        $result = $register->handle(new RegisterAuctioneerInput(
            ownerName: 'Leiloeiro Demo',
            email: 'demo@doleleilao.com.br',
            phone: '(11) 98765-4321',
            password: 'demo12345',
            documentType: 'cpf',
            document: '529.982.247-25',
            companyName: 'Relojoaria Demo de Leilões',
            slug: 'relojoaria-demo',
        ));

        $verifier->verify(AuctioneerUser::query()->findOrFail($result->ownerUserId));

        $this->command->info('Leiloeiro demo: demo@doleleilao.com.br / demo12345 (loja /relojoaria-demo)');
    }
}
