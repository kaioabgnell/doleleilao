<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Problem;
use DoleLeilao\Shared\Application\Port\PostalCodeLookup;
use DoleLeilao\Shared\Application\PostalCodeLookupUnavailable;
use DoleLeilao\Shared\Domain\PostalCode;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/postal-codes/{cep}: preenche o endereço a partir do CEP (painel e "Minha conta").
 * 404 = CEP não existe; 503 = serviço de CEP fora (a pessoa preenche à mão).
 */
final class PostalCodeController
{
    public function __invoke(string $postalCode, PostalCodeLookup $lookup): JsonResponse
    {
        $code = PostalCode::fromString($postalCode);

        try {
            $result = $lookup->find($code);
        } catch (PostalCodeLookupUnavailable $e) {
            return Problem::make(503, $e->getMessage());
        }

        if ($result === null) {
            return Problem::make(404, 'Não achamos este CEP. Confira os números.');
        }

        return response()->json([
            'postal_code' => $code->formatted(),
            'street' => $result->street,
            'district' => $result->district,
            'city' => $result->city,
            'state' => $result->state,
        ]);
    }
}
