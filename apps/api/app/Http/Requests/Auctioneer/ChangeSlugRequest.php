<?php

declare(strict_types=1);

namespace App\Http\Requests\Auctioneer;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeSlugRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:60']];
    }
}
