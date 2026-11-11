<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;

/** Respostas de erro no padrão `application/problem+json` (RFC 9457), specs/06-api-e-tempo-real.md. */
final class Problem
{
    private const array TITLES = [
        400 => 'Requisição inválida',
        401 => 'Não autenticado',
        403 => 'Acesso negado',
        404 => 'Não encontrado',
        405 => 'Método não permitido',
        409 => 'Conflito',
        419 => 'Sessão expirada',
        422 => 'Dados inválidos',
        429 => 'Muitas tentativas',
        500 => 'Erro interno',
        503 => 'Serviço indisponível',
    ];

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function make(int $status, ?string $detail = null, array $extra = [], array $headers = []): JsonResponse
    {
        $body = array_filter([
            'type' => 'about:blank',
            'title' => self::TITLES[$status] ?? 'Erro',
            'status' => $status,
            'detail' => $detail,
        ], static fn (mixed $v): bool => $v !== null) + $extra;

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json'] + $headers);
    }

    /** @param array<string, array<int, string>> $errors */
    public static function validation(array $errors): JsonResponse
    {
        $first = array_values($errors)[0][0] ?? 'Confira os campos destacados.';

        return self::make(422, $first, ['errors' => $errors]);
    }
}
