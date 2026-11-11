<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/**
 * Nome de exibição da loja: vira a logo de texto quando não há logo (D-10, D-16).
 * No máximo 2 palavras e 24 caracteres.
 */
final readonly class DisplayName
{
    public const int MAX_WORDS = 2;

    public const int MAX_LENGTH = 24;

    private const array CONNECTIVES = ['de', 'do', 'da', 'dos', 'das', 'e'];

    private function __construct(public string $value) {}

    public static function fromString(string $input): self
    {
        $name = self::collapse($input);

        if ($name === '') {
            throw InvalidDisplayName::because('Informe o nome de exibição.');
        }
        if (count(explode(' ', $name)) > self::MAX_WORDS) {
            throw InvalidDisplayName::because('Use no máximo 2 palavras.');
        }
        if (mb_strlen($name) > self::MAX_LENGTH) {
            throw InvalidDisplayName::because('Use no máximo 24 caracteres.');
        }

        return new self($name);
    }

    /** As 2 primeiras palavras do nome da empresa, sem conectivos nem símbolos soltos. */
    public static function suggestFrom(string $companyName): self
    {
        $words = array_values(array_filter(
            explode(' ', self::collapse($companyName)),
            static fn (string $w): bool => preg_match('/[\p{L}\p{N}]/u', $w) === 1
                && ! in_array(mb_strtolower($w), self::CONNECTIVES, true),
        ));

        if ($words === []) {
            throw InvalidDisplayName::because('Informe o nome de exibição.');
        }

        $name = implode(' ', array_slice($words, 0, self::MAX_WORDS));
        if (mb_strlen($name) > self::MAX_LENGTH) {
            $name = mb_substr($words[0], 0, self::MAX_LENGTH);
        }

        return self::fromString($name);
    }

    private static function collapse(string $input): string
    {
        return trim(preg_replace('/\s+/u', ' ', $input) ?? '');
    }
}
