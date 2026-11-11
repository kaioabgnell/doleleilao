<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Requests\AddressPayload;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** O próprio participante (Minha conta). O CPF nunca sai completo (RNF-09). @mixin BidderUser */
final class BidderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BidderUser $b */
        $b = $this->resource;

        return [
            'id' => $b->public_id,
            'name' => $b->name,
            'nickname' => $b->nickname,
            'email' => $b->email,
            'phone' => Phone::fromString($b->phone)->formatted(),
            'cpf_masked' => $b->cpf()->masked(),
            'photo_url' => app(MediaStorage::class)->url($b->photo_path),
            'avatar_key' => $b->avatar_key,
            'alert_minutes' => $b->alert_minutes,
            'address' => AddressPayload::present($b->shippingAddress()),
            'email_verified' => $b->email_verified_at !== null,
            'eligible' => $b->isEligible(),
            'missing' => $b->missingForEligibility(),
        ];
    }
}
