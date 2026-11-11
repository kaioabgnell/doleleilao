<?php

declare(strict_types=1);

namespace App\Http\Requests\Auctioneer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** POST /api/v1/auctioneers. Formato aqui; regras de negócio (documento, slug, unicidade) no domínio. */
final class RegisterAuctioneerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:255'],
            'document_type' => ['required', 'string', 'in:cpf,cnpj'],
            'document' => ['required', 'string', 'max:20'],
            'company_name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['required', 'string', 'max:60'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'accept_terms' => ['accepted'],
        ];
    }
}
