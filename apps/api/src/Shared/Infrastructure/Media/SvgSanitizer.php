<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Media;

use DoleLeilao\Shared\Domain\InvalidUpload;
use enshrined\svgSanitize\Sanitizer;

/** SVG enviado pelo leiloeiro (RF-B02): remove scripts, eventos e referências externas. */
final class SvgSanitizer
{
    public function sanitize(string $path, string $field): string
    {
        $raw = file_get_contents($path);
        if ($raw === false || ! str_contains($raw, '<svg')) {
            throw new InvalidUpload($field, 'Este arquivo não é um SVG válido.');
        }

        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);
        $clean = $sanitizer->sanitize($raw);

        if ($clean === false || $clean === '' || ! str_contains($clean, '<svg')) {
            throw new InvalidUpload($field, 'Este arquivo não é um SVG válido.');
        }

        return $clean;
    }
}
