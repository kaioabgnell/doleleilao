<?php

use App\Http\Controllers\Api\V1\Auctioneer\AuctionControlController;
use App\Http\Controllers\Api\V1\Auctioneer\AuctionController;
use App\Http\Controllers\Api\V1\Auctioneer\AuctionCoverController;
use App\Http\Controllers\Api\V1\Auctioneer\AwardController;
use App\Http\Controllers\Api\V1\Auctioneer\BannerController;
use App\Http\Controllers\Api\V1\Auctioneer\BillingController;
use App\Http\Controllers\Api\V1\Auctioneer\CategoryController;
use App\Http\Controllers\Api\V1\Auctioneer\ChatModerationController;
use App\Http\Controllers\Api\V1\Auctioneer\ConsignmentInboxController;
use App\Http\Controllers\Api\V1\Auctioneer\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auctioneer\LogoController;
use App\Http\Controllers\Api\V1\Auctioneer\MeController;
use App\Http\Controllers\Api\V1\Auctioneer\NotificationController;
use App\Http\Controllers\Api\V1\Auctioneer\OnboardingController;
use App\Http\Controllers\Api\V1\Auctioneer\PasswordController;
use App\Http\Controllers\Api\V1\Auctioneer\ProductController;
use App\Http\Controllers\Api\V1\Auctioneer\ProductHistoryController;
use App\Http\Controllers\Api\V1\Auctioneer\ProductPhotoController;
use App\Http\Controllers\Api\V1\Auctioneer\RegistrationController;
use App\Http\Controllers\Api\V1\Auctioneer\SessionController;
use App\Http\Controllers\Api\V1\Auctioneer\SlugAvailabilityController;
use App\Http\Controllers\Api\V1\Auctioneer\StorefrontController;
use App\Http\Controllers\Api\V1\Auctioneer\StorefrontSlugController;
use App\Http\Controllers\Api\V1\Auctioneer\StoreTermsController;
use App\Http\Controllers\Api\V1\Auctioneer\SuggestionController;
use App\Http\Controllers\Api\V1\Bidder\AccountController;
use App\Http\Controllers\Api\V1\Bidder\AwardController as BidderAwardController;
use App\Http\Controllers\Api\V1\Bidder\BidController;
use App\Http\Controllers\Api\V1\Bidder\ChatController as BidderChatController;
use App\Http\Controllers\Api\V1\Bidder\FavoriteController;
use App\Http\Controllers\Api\V1\Bidder\ParticipationController;
use App\Http\Controllers\Api\V1\BroadcastAuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PostalCodeController;
use App\Http\Controllers\Api\V1\PublicStorefrontController;
use App\Http\Controllers\Api\V1\Store\AuctionController as StoreAuctionController;
use App\Http\Controllers\Api\V1\Store\BidderAuthController;
use App\Http\Controllers\Api\V1\Store\CatalogController;
use App\Http\Controllers\Api\V1\Store\ChatController as StoreChatController;
use App\Http\Controllers\Api\V1\Store\ConsignmentController;
use Illuminate\Support\Facades\Route;

// API versionada (RNF-11). Prefixo final: /api/v1. Rotas: specs/06-api-e-tempo-real.md
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    // Endereço pelo CEP (painel e "Minha conta"); o domínio confere o formato
    Route::get('/postal-codes/{postalCode}', PostalCodeController::class)
        ->middleware('throttle:public-lookup')->name('postal-codes.show');

    // Loja pública (RF-E01)
    Route::get('/storefronts/{slug}', [PublicStorefrontController::class, 'show'])
        ->where('slug', '[A-Za-z0-9-]{1,60}')->name('storefronts.show');
    Route::get('/storefronts/{slug}/status', [PublicStorefrontController::class, 'status'])
        ->where('slug', '[A-Za-z0-9-]{1,60}')->name('storefronts.status');
    Route::get('/storefronts/{slug}/terms', [PublicStorefrontController::class, 'terms'])
        ->where('slug', '[A-Za-z0-9-]{1,60}')->name('storefronts.terms');

    // Loja pública: catálogo, participante e "quero leiloar" (Fase 2)
    Route::prefix('storefronts/{slug}')->where(['slug' => '[A-Za-z0-9-]{1,60}'])->name('storefronts.')->group(function () {
        Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');
        Route::post('/products/{product}/views', [CatalogController::class, 'view'])->middleware('throttle:120,1')->name('products.views');
        Route::get('/auctions', [StoreAuctionController::class, 'index'])->name('auctions.index');
        Route::get('/auctions/{auction}', [StoreAuctionController::class, 'show'])->name('auctions.show');
        Route::get('/auctions/{auction}/state', [StoreAuctionController::class, 'state'])->middleware('throttle:120,1')->name('auctions.state');
        Route::get('/auctions/{auction}/chat', [StoreChatController::class, 'index'])->middleware('throttle:120,1')->name('auctions.chat');
        Route::post('/consignment-requests', [ConsignmentController::class, 'store'])->middleware('throttle:5,60')->name('consignment.store');

        Route::post('/bidders', [BidderAuthController::class, 'register'])->middleware('throttle:auctioneer-register')->name('bidders.register');
        Route::post('/sessions', [BidderAuthController::class, 'login'])->middleware('throttle:auctioneer-login')->name('bidders.login');
        Route::post('/password/forgot', [BidderAuthController::class, 'forgotPassword'])->middleware('throttle:auctioneer-password')->name('bidders.password.forgot');
        Route::post('/password/reset', [BidderAuthController::class, 'resetPassword'])->middleware('throttle:auctioneer-password')->name('bidders.password.reset');
    });
    Route::get('/bidders/email/verify/{bidder}/{hash}', [BidderAuthController::class, 'verifyEmail'])
        ->middleware('throttle:public-lookup')->name('bidders.email.verify');

    // Participante (token `bidder:{loja}`)
    Route::prefix('bidders/me')->middleware(['auth:sanctum', 'bidder'])->name('bidders.me.')->group(function () {
        Route::get('/', [AccountController::class, 'me'])->name('show');
        Route::patch('/', [AccountController::class, 'update'])->name('update');
        Route::post('/photo', [AccountController::class, 'photo'])->name('photo');
        Route::delete('/photo', [AccountController::class, 'removePhoto'])->name('photo.destroy');
        Route::put('/avatar', [AccountController::class, 'avatar'])->name('avatar');
        Route::get('/eligibility', [AccountController::class, 'eligibility'])->name('eligibility');
        Route::post('/email/verification', [AccountController::class, 'resendVerification'])->middleware('throttle:6,1')->name('email.resend');
        Route::delete('/sessions/current', [AccountController::class, 'logout'])->name('logout');
        Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::put('/favorites/{product}', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::get('/auctions/{auction}', [ParticipationController::class, 'show'])->name('auctions.show');
        Route::put('/participations/{auction}', [ParticipationController::class, 'store'])->name('participations.store');
        Route::delete('/participations/{auction}', [ParticipationController::class, 'destroy'])->name('participations.destroy');
        Route::post('/auctions/{auction}/chat', [BidderChatController::class, 'store'])->name('chat.store');
        Route::get('/awards', [BidderAwardController::class, 'awards'])->name('awards.index');
        Route::get('/upcoming-auctions', [BidderAwardController::class, 'upcoming'])->name('upcoming');
    });

    // Lances (RF-F02, RF-F03): HTTP idempotente, distribuído por WebSocket (T-02)
    Route::prefix('items/{item}')->middleware(['auth:sanctum', 'bidder', 'throttle:bids'])->name('items.')->group(function () {
        Route::post('/bids', [BidController::class, 'store'])->name('bids.store');
        Route::put('/auto-bid', [BidController::class, 'setAutoBid'])->name('auto-bid.update');
        Route::delete('/auto-bid', [BidController::class, 'cancelAutoBid'])->name('auto-bid.destroy');
    });

    // Leiloeiro: cadastro e acesso (públicos)
    Route::post('/auctioneers', RegistrationController::class)
        ->middleware('throttle:auctioneer-register')->name('auctioneers.register');
    Route::get('/auctioneers/slug-availability', SlugAvailabilityController::class)
        ->middleware('throttle:public-lookup')->name('auctioneers.slug-availability');
    Route::get('/auctioneers/suggestions', SuggestionController::class)
        ->middleware('throttle:public-lookup')->name('auctioneers.suggestions');

    Route::prefix('auctioneer')->name('auctioneer.')->group(function () {
        Route::post('/sessions', [SessionController::class, 'store'])
            ->middleware('throttle:auctioneer-login')->name('sessions.store');
        Route::post('/password/forgot', [PasswordController::class, 'forgot'])
            ->middleware('throttle:auctioneer-password')->name('password.forgot');
        Route::post('/password/reset', [PasswordController::class, 'reset'])
            ->middleware('throttle:auctioneer-password')->name('password.reset');
        Route::get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('throttle:public-lookup')->name('email.verify');

        // Painel (token com escopo `auctioneer`)
        Route::middleware(['auth:sanctum', 'abilities:auctioneer', 'auctioneer.active', 'auctioneer.tenant'])->group(function () {
            Route::delete('/sessions/current', [SessionController::class, 'destroy'])->name('sessions.destroy');
            Route::get('/me', MeController::class)->name('me');
            Route::post('/email/verification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1')->name('email.resend');

            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

            Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
            Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');

            Route::get('/storefront', [StorefrontController::class, 'show'])->name('storefront.show');
            Route::patch('/storefront', [StorefrontController::class, 'update'])->name('storefront.update');
            Route::put('/storefront/slug', StorefrontSlugController::class)->name('storefront.slug');
            Route::post('/storefront/logo', [LogoController::class, 'store'])->name('storefront.logo.store');
            Route::delete('/storefront/logo', [LogoController::class, 'destroy'])->name('storefront.logo.destroy');
            Route::get('/storefront/terms', [StoreTermsController::class, 'show'])->name('storefront.terms.show');
            Route::put('/storefront/terms', [StoreTermsController::class, 'update'])->name('storefront.terms.update');

            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/order', [CategoryController::class, 'reorder'])->name('categories.reorder');
            Route::post('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/attribute-names', [ProductController::class, 'attributeNames'])->name('products.attribute-names');
            Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::get('/products/{product}/history', [ProductHistoryController::class, 'show'])->name('products.history');
            Route::post('/products/{product}/return-to-catalog', [ProductHistoryController::class, 'returnToCatalog'])->name('products.return');
            Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/photos', [ProductPhotoController::class, 'store'])->name('products.photos.store');
            Route::put('/products/{product}/photos/order', [ProductPhotoController::class, 'reorder'])->name('products.photos.reorder');
            Route::delete('/products/{product}/photos/{photo}', [ProductPhotoController::class, 'destroy'])->name('products.photos.destroy');

            Route::get('/consignment-requests', [ConsignmentInboxController::class, 'index'])->name('consignment.index');
            Route::patch('/consignment-requests/{consignment}', [ConsignmentInboxController::class, 'update'])->name('consignment.update');

            Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');
            Route::post('/auctions', [AuctionController::class, 'store'])->name('auctions.store');
            Route::get('/auctions/selectable-products', [AuctionController::class, 'selectableProducts'])->name('auctions.selectable-products');
            Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');
            Route::patch('/auctions/{auction}', [AuctionController::class, 'update'])->name('auctions.update');
            Route::delete('/auctions/{auction}', [AuctionController::class, 'destroy'])->name('auctions.destroy');
            Route::put('/auctions/{auction}/visibility', [AuctionController::class, 'visibility'])->name('auctions.visibility');
            Route::post('/auctions/{auction}/cover', [AuctionCoverController::class, 'update'])->name('auctions.cover.update');
            Route::delete('/auctions/{auction}/cover', [AuctionCoverController::class, 'destroy'])->name('auctions.cover.destroy');
            Route::post('/auctions/{auction}/schedule', [AuctionController::class, 'schedule'])->name('auctions.schedule');
            Route::post('/auctions/{auction}/unschedule', [AuctionController::class, 'unschedule'])->name('auctions.unschedule');
            Route::post('/auctions/{auction}/cancel', [AuctionController::class, 'cancel'])->name('auctions.cancel');
            Route::get('/auctions/{auction}/live', [AuctionControlController::class, 'live'])->name('auctions.live');
            Route::post('/auctions/{auction}/start', [AuctionControlController::class, 'start'])->name('auctions.start');
            Route::post('/auctions/{auction}/pause', [AuctionControlController::class, 'pause'])->name('auctions.pause');
            Route::post('/auctions/{auction}/resume', [AuctionControlController::class, 'resume'])->name('auctions.resume');
            Route::post('/items/{item}/close', [AuctionControlController::class, 'closeItem'])->name('items.close');
            Route::post('/items/{item}/skip', [AuctionControlController::class, 'skipItem'])->name('items.skip');
            Route::post('/items/{item}/void-last-bid', [AuctionControlController::class, 'voidLastBid'])->name('items.void-last-bid');

            Route::get('/auctions/{auction}/chat', [ChatModerationController::class, 'index'])->name('chat.index');
            Route::post('/auctions/{auction}/chat', [ChatModerationController::class, 'store'])->name('chat.store');
            Route::delete('/auctions/{auction}/chat/{message}', [ChatModerationController::class, 'destroy'])->name('chat.destroy');
            Route::put('/auctions/{auction}/mutes/{bidder}', [ChatModerationController::class, 'mute'])->name('chat.mute');
            Route::delete('/auctions/{auction}/mutes/{bidder}', [ChatModerationController::class, 'unmute'])->name('chat.unmute');
            Route::put('/bidders/{bidder}/ban', [ChatModerationController::class, 'ban'])->name('bidders.ban');
            Route::delete('/bidders/{bidder}/ban', [ChatModerationController::class, 'unban'])->name('bidders.unban');
            Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'auctioneer'])->name('broadcasting.auth');

            Route::get('/awards', [AwardController::class, 'index'])->name('awards.index');
            Route::get('/awards/export', [AwardController::class, 'export'])->name('awards.export');
            Route::get('/winner-message-template', [AwardController::class, 'template'])->name('awards.template');
            Route::put('/winner-message-template', [AwardController::class, 'saveTemplate'])->name('awards.template.save');
            Route::patch('/awards/{award}', [AwardController::class, 'update'])->name('awards.update');
            Route::get('/awards/{award}/message', [AwardController::class, 'draft'])->name('awards.draft');
            Route::post('/awards/{award}/message', [AwardController::class, 'message'])->name('awards.message');

            // Configurações → Pagamento (D-54). O número do cartão nunca é guardado.
            Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
            Route::put('/billing/card', [BillingController::class, 'saveCard'])->middleware('throttle:10,1')->name('billing.card');

            Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
            Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
            Route::put('/banners/order', [BannerController::class, 'reorder'])->name('banners.reorder');
            Route::post('/banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
            Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
        });
    });

    // Autorização dos canais privados do WebSocket (participante logado na loja)
    Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'bidder'])->middleware(['auth:sanctum', 'bidder'])->name('broadcasting.auth');
});
