<?php

namespace App\Providers;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * v1: ninguém acessa pela web fora do ambiente local (sem painel super admin, D-28).
     * Em produção, consulte o Horizon por túnel SSH ou `php artisan horizon:status`.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?AuctioneerUser $user = null): bool => false);
    }
}
