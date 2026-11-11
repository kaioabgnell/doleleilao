<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use Illuminate\Http\Request;

trait ResolvesAuctioneerUser
{
    private function currentUser(Request $request): AuctioneerUser
    {
        $user = $request->user();
        if (! $user instanceof AuctioneerUser) {
            abort(401);
        }

        return $user;
    }
}
