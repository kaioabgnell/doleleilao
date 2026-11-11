<?php

namespace App\Providers;

use DoleLeilao\Auction\Application\Port\AuctionItemRepository;
use DoleLeilao\Auction\Application\Port\AuctionRepository;
use DoleLeilao\Auction\Application\Port\AuditLog;
use DoleLeilao\Auction\Application\Port\AwardRepository;
use DoleLeilao\Auction\Application\Port\BidderGate;
use DoleLeilao\Auction\Application\Port\CatalogGateway;
use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Auction\Domain\Event\AuctionScheduled;
use DoleLeilao\Auction\Domain\Event\AuctionStarted;
use DoleLeilao\Auction\Domain\Event\AutoBidExhausted;
use DoleLeilao\Auction\Domain\Event\BidAccepted;
use DoleLeilao\Auction\Domain\Event\BidderOutbid;
use DoleLeilao\Auction\Domain\Event\BidVoided;
use DoleLeilao\Auction\Domain\Event\ItemAwarded;
use DoleLeilao\Auction\Domain\Event\ItemClosed;
use DoleLeilao\Auction\Domain\Event\ItemOpened;
use DoleLeilao\Auction\Domain\Event\ItemPaused;
use DoleLeilao\Auction\Domain\Event\ItemResumed;
use DoleLeilao\Auction\Domain\Event\ParticipationConfirmed;
use DoleLeilao\Auction\Infrastructure\BroadcastAuctionEvents;
use DoleLeilao\Auction\Infrastructure\DatabaseAuditLog;
use DoleLeilao\Auction\Infrastructure\EloquentAuctionItemRepository;
use DoleLeilao\Auction\Infrastructure\EloquentAuctionRepository;
use DoleLeilao\Auction\Infrastructure\EloquentAwardRepository;
use DoleLeilao\Auction\Infrastructure\EloquentBidderGate;
use DoleLeilao\Auction\Infrastructure\EloquentCatalogGateway;
use DoleLeilao\Auction\Infrastructure\RevalidateStoreOnAuctionChange;
use DoleLeilao\Billing\Application\Port\FeeChargeRepository;
use DoleLeilao\Billing\Application\Port\FeeTierRepository;
use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Billing\Application\Port\SoldItems;
use DoleLeilao\Billing\Infrastructure\AuctionSoldItems;
use DoleLeilao\Billing\Infrastructure\ChargeFeesWhenAuctionFinishes;
use DoleLeilao\Billing\Infrastructure\EloquentFeeChargeRepository;
use DoleLeilao\Billing\Infrastructure\EloquentFeeTierRepository;
use DoleLeilao\Billing\Infrastructure\EloquentPaymentCardRepository;
use DoleLeilao\Chat\Application\BidderMuted;
use DoleLeilao\Chat\Application\MessageDeleted;
use DoleLeilao\Chat\Application\MessagePosted;
use DoleLeilao\Chat\Application\Port\ChatRepository;
use DoleLeilao\Chat\Application\Port\ChatThrottle;
use DoleLeilao\Chat\Infrastructure\BroadcastChatEvents;
use DoleLeilao\Chat\Infrastructure\EloquentChatRepository;
use DoleLeilao\Chat\Infrastructure\RateLimiterChatThrottle;
use DoleLeilao\Identity\Infrastructure\BidderBanned;
use DoleLeilao\Identity\Infrastructure\BidderBecameEligible;
use DoleLeilao\Notification\Application\Port\NotificationRepository;
use DoleLeilao\Notification\Infrastructure\AuctionNotifications;
use DoleLeilao\Notification\Infrastructure\EloquentNotificationRepository;
use DoleLeilao\Notification\Infrastructure\NotifyAuctioneerOnRegistration;
use DoleLeilao\Notification\Infrastructure\NotifyAuctioneerOnStorefrontPublished;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Application\Port\PostalCodeLookup;
use DoleLeilao\Shared\Application\TransactionManager;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Infrastructure\DatabaseTransactionManager;
use DoleLeilao\Shared\Infrastructure\HttpPostalCodeLookup;
use DoleLeilao\Shared\Infrastructure\LaravelEventBus;
use DoleLeilao\Shared\Infrastructure\Scalar;
use DoleLeilao\Shared\Infrastructure\SystemClock;
use DoleLeilao\Shared\Infrastructure\TaxDocumentHasher;
use DoleLeilao\Shared\Infrastructure\Tenancy\CurrentTenant;
use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Application\Port\OnboardingRepository;
use DoleLeilao\Storefront\Application\Port\PasswordHasher;
use DoleLeilao\Storefront\Application\Port\StorefrontRepository;
use DoleLeilao\Storefront\Domain\AuctioneerRegistered;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Domain\StorefrontPublished;
use DoleLeilao\Storefront\Domain\ThemeCatalog;
use DoleLeilao\Storefront\Infrastructure\EloquentAuctioneerRepository;
use DoleLeilao\Storefront\Infrastructure\EloquentOnboardingRepository;
use DoleLeilao\Storefront\Infrastructure\EloquentStorefrontRepository;
use DoleLeilao\Storefront\Infrastructure\HashPasswordHasher;
use DoleLeilao\Storefront\Infrastructure\JsonThemeCatalog;
use DoleLeilao\Storefront\Infrastructure\RevalidateStorefrontCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
        CurrentTenant::class => CurrentTenant::class,
        EventBus::class => LaravelEventBus::class,
        AuctioneerRepository::class => EloquentAuctioneerRepository::class,
        StorefrontRepository::class => EloquentStorefrontRepository::class,
        OnboardingRepository::class => EloquentOnboardingRepository::class,
        PasswordHasher::class => HashPasswordHasher::class,
        NotificationRepository::class => EloquentNotificationRepository::class,
        TransactionManager::class => DatabaseTransactionManager::class,
        AwardRepository::class => EloquentAwardRepository::class,
        AuditLog::class => DatabaseAuditLog::class,
        BidderGate::class => EloquentBidderGate::class,
        CatalogGateway::class => EloquentCatalogGateway::class,
        ChatRepository::class => EloquentChatRepository::class,
        ChatThrottle::class => RateLimiterChatThrottle::class,
        PostalCodeLookup::class => HttpPostalCodeLookup::class,
        PaymentCardRepository::class => EloquentPaymentCardRepository::class,
        FeeTierRepository::class => EloquentFeeTierRepository::class,
        FeeChargeRepository::class => EloquentFeeChargeRepository::class,
        SoldItems::class => AuctionSoldItems::class,
    ];

    /**
     * Repositórios com mapa de registros carregados: uma instância por uso, nunca compartilhada.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AuctionRepository::class => EloquentAuctionRepository::class,
        AuctionItemRepository::class => EloquentAuctionItemRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ThemeCatalog::class, fn (): ThemeCatalog => JsonThemeCatalog::load(
            config()->string('doleleilao.theme_catalog'),
        ));

        $this->app->singleton(TaxDocumentHasher::class, function (): TaxDocumentHasher {
            $key = config()->string('app.key');

            return new TaxDocumentHasher(str_starts_with($key, 'base64:')
                ? (string) base64_decode(substr($key, 7), true)
                : $key);
        });
    }

    public function boot(): void
    {
        // Respostas sem o envelope `data` (listas paginadas trazem `data` + `next_cursor`).
        JsonResource::withoutWrapping();
        $this->configureRateLimiting();

        // Eventos entre contextos (specs/03-dominio.md#eventos-de-domínio).
        Event::listen(AuctioneerRegistered::class, NotifyAuctioneerOnRegistration::class);
        Event::listen(StorefrontPublished::class, NotifyAuctioneerOnStorefrontPublished::class);
        Event::listen([StorefrontChanged::class, StorefrontPublished::class], RevalidateStorefrontCache::class);

        // Leilão (Fase 3): tempo real, avisos do sino e cache da loja.
        Event::listen([
            ItemOpened::class, BidAccepted::class, ItemPaused::class, ItemResumed::class, ItemClosed::class, BidVoided::class,
            AuctionStarted::class, AuctionFinished::class, BidderOutbid::class, AutoBidExhausted::class, ItemAwarded::class,
        ], BroadcastAuctionEvents::class);
        Event::listen(ParticipationConfirmed::class, [AuctionNotifications::class, 'onParticipation']);
        Event::listen(BidderBecameEligible::class, [AuctionNotifications::class, 'onEligible']);
        Event::listen(ItemAwarded::class, [AuctionNotifications::class, 'onAwarded']);

        // Chat (Fase 4) e banimento: tempo real.
        Event::listen([MessagePosted::class, MessageDeleted::class, BidderMuted::class, BidderBanned::class], BroadcastChatEvents::class);
        Event::listen([AuctionScheduled::class, AuctionStarted::class, AuctionFinished::class, ItemClosed::class], RevalidateStoreOnAuctionChange::class);

        // Cobrança (D-47, D-54): lote encerrado gera o débito da taxa no cartão do leiloeiro.
        Event::listen(AuctionFinished::class, ChargeFeesWhenAuctionFinishes::class);
    }

    /**
     * Limites de requisição (specs/04-arquitetura.md#segurança). O IP é o do visitante:
     * o Next.js (BFF) repassa o X-Forwarded-For e a API confia só no proxy local.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auctioneer-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(mb_strtolower($request->string('email')->trim()->value()).'|'.$request->ip()));

        RateLimiter::for('auctioneer-register', fn (Request $request): Limit => Limit::perHour(10)
            ->by((string) $request->ip()));

        RateLimiter::for('auctioneer-password', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->ip()));

        // Lances: folga para disputas acirradas, sem deixar um robô inundar o item (D-37: 20 lances/s por item).
        RateLimiter::for('bids', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('bidder:'.(($user = $request->user('sanctum')) instanceof Authenticatable ? Scalar::string($user->getAuthIdentifier()) : (string) $request->ip())));

        RateLimiter::for('public-lookup', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) $request->ip()));
    }
}
