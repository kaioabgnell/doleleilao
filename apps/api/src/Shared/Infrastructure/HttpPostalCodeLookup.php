<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

use DoleLeilao\Shared\Application\Port\PostalCodeLookup;
use DoleLeilao\Shared\Application\PostalCodeLookupUnavailable;
use DoleLeilao\Shared\Application\PostalCodeResult;
use DoleLeilao\Shared\Domain\PostalCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * CEP pelo ViaCEP, com a BrasilAPI de reserva (fora do ar ou CEP que o ViaCEP não conhece).
 * O cache guarda o endereço por 30 dias e o "não existe" por 1 hora; erro de serviço não fica guardado.
 */
final readonly class HttpPostalCodeLookup implements PostalCodeLookup
{
    private const int FOUND_TTL = 60 * 60 * 24 * 30;

    private const int MISSING_TTL = 60 * 60;

    public function find(PostalCode $postalCode): ?PostalCodeResult
    {
        $key = "postal-code:{$postalCode->value}";
        $cached = Cache::get($key);
        if (is_array($cached)) {
            /** @var array{street: string, district: string, city: string, state: string}|array{} $cached */
            return $cached === [] ? null : $this->result($postalCode, $cached);
        }

        $answered = 0;
        foreach ([$this->viaCep(...), $this->brasilApi(...)] as $provider) {
            try {
                $fields = $provider($postalCode);
            } catch (ConnectionException|RequestException|\UnexpectedValueException) {
                continue;
            }

            $answered++;
            if ($fields !== null) {
                Cache::put($key, $fields, self::FOUND_TTL);

                return $this->result($postalCode, $fields);
            }
        }

        if ($answered === 0) {
            throw new PostalCodeLookupUnavailable;
        }
        if ($answered === 2) {
            Cache::put($key, [], self::MISSING_TTL);
        }

        return null;
    }

    /** @return array{street: string, district: string, city: string, state: string}|null */
    private function viaCep(PostalCode $postalCode): ?array
    {
        $response = $this->http()->get("https://viacep.com.br/ws/{$postalCode->value}/json/");
        if ($response->status() === 400) {
            return null;
        }
        $response->throw();

        $json = $response->json();
        if (is_array($json) && isset($json['erro'])) {
            return null;
        }

        return $this->fields($json, street: 'logradouro', district: 'bairro', city: 'localidade', state: 'uf');
    }

    /** @return array{street: string, district: string, city: string, state: string}|null */
    private function brasilApi(PostalCode $postalCode): ?array
    {
        $response = $this->http()->get("https://brasilapi.com.br/api/cep/v1/{$postalCode->value}");
        if ($response->status() === 404) {
            return null;
        }
        $response->throw();

        return $this->fields($response->json(), street: 'street', district: 'neighborhood', city: 'city', state: 'state');
    }

    /** @return array{street: string, district: string, city: string, state: string} */
    private function fields(mixed $json, string $street, string $district, string $city, string $state): array
    {
        $read = static fn (string $key): string => is_array($json) && is_string($json[$key] ?? null) ? trim($json[$key]) : '';
        $fields = [
            'street' => $read($street),
            'district' => $read($district),
            'city' => $read($city),
            'state' => mb_strtoupper($read($state)),
        ];

        if ($fields['city'] === '' || strlen($fields['state']) !== 2) {
            throw new \UnexpectedValueException('Resposta do serviço de CEP sem cidade ou UF.');
        }

        return $fields;
    }

    /** @param array{street: string, district: string, city: string, state: string} $fields */
    private function result(PostalCode $postalCode, array $fields): PostalCodeResult
    {
        return new PostalCodeResult($postalCode, $fields['street'], $fields['district'], $fields['city'], $fields['state']);
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(2)->timeout(3)->withUserAgent('DoleLeilao/1.0');
    }
}
