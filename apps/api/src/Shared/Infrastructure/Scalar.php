<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

/**
 * Valores crus do banco (query builder, cache) com o tipo conferido. Um valor inesperado é erro,
 * não vira 0 ou "" em silêncio.
 */
final class Scalar
{
    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw new \UnexpectedValueException('Esperava um número inteiro, veio '.get_debug_type($value).'.');
    }

    public static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : self::int($value);
    }

    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        throw new \UnexpectedValueException('Esperava um texto, veio '.get_debug_type($value).'.');
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<int>
     */
    public static function ints(iterable $values): array
    {
        $ints = [];
        foreach ($values as $value) {
            $ints[] = self::int($value);
        }

        return $ints;
    }
}
