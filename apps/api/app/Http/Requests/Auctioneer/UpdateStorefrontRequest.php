<?php

declare(strict_types=1);

namespace App\Http\Requests\Auctioneer;

use App\Http\Requests\AddressPayload;
use Illuminate\Foundation\Http\FormRequest;

/** PATCH /api/v1/auctioneer/storefront. As listas fechadas (modelo, cores, fundo, fonte) são checadas no domínio. */
final class UpdateStorefrontRequest extends FormRequest
{
    public const array SOCIALS = ['instagram', 'facebook', 'tiktok', 'youtube', 'website'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template' => ['sometimes', 'string', 'max:16'],
            'primary_color' => ['sometimes', 'string', 'max:16'],
            'accent_color' => ['sometimes', 'string', 'max:16'],
            'background' => ['sometimes', 'string', 'max:16'],
            'heading_font' => ['sometimes', 'string', 'max:16'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'socials' => ['sometimes', 'array:'.implode(',', self::SOCIALS)],
            'socials.*' => ['nullable', 'string', 'url:https', 'max:255'],
            ...AddressPayload::rules(),
        ];
    }
}
