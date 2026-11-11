<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

use DoleLeilao\Storefront\Application\PublishStorefrontIfReady;
use DoleLeilao\Storefront\Domain\AuctioneerStatus;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

/** Marca o e-mail como validado e ativa a loja do dono (RF-A04: a loja só fica pública depois disso). */
final readonly class AuctioneerEmailVerifier
{
    public function __construct(private PublishStorefrontIfReady $publish) {}

    public function verify(AuctioneerUser $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->markEmailAsVerified();
            if ($user->role === 'owner') {
                $user->auctioneer()
                    ->where('status', AuctioneerStatus::Pending->value)
                    ->update(['status' => AuctioneerStatus::Active->value]);
            }
        });

        event(new Verified($user));
        $this->publish->handle($user->auctioneer_id);
    }
}
