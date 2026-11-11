<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/** Texto → trecho de URL: sem acentos, minúsculo, palavras separadas por hífen. */
final class Slugify
{
    private const array ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ç' => 'c', 'Ç' => 'c', 'ñ' => 'n', 'Ñ' => 'n',
    ];

    public static function from(string $text, int $maxLength = 60): string
    {
        $slug = strtolower(strtr(trim($text), self::ACCENTS));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');

        if (strlen($slug) > $maxLength) {
            $slug = substr($slug, 0, $maxLength);
            $cut = strrpos($slug, '-');
            $slug = $cut !== false && $cut > 0 ? substr($slug, 0, $cut) : $slug;
        }

        return $slug;
    }
}
