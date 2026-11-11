<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Shared\Domain\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuctioneerUser */
final class AuctioneerUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuctioneerUser $u */
        $u = $this->resource;

        return [
            'id' => $u->public_id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => Phone::fromString($u->phone)->formatted(),
            'role' => $u->role,
            'email_verified' => $u->email_verified_at !== null,
        ];
    }
}
