<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Storefront\Domain\ThemeCatalog;

/**
 * Lê `resources/design/theme-catalog.json`, gerado pelo pacote design-tokens
 * (`npm run build -w @doleleilao/design-tokens`). Um teste do pacote garante que ele está atualizado.
 */
final class JsonThemeCatalog
{
    public static function load(string $path): ThemeCatalog
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Catálogo de temas não encontrado em {$path}.");
        }

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! isset($data['colors'], $data['fonts'], $data['backgrounds'], $data['templates'])) {
            throw new \RuntimeException("Catálogo de temas inválido em {$path}.");
        }

        /** @var array{colors: list<string>, fonts: list<string>, backgrounds: array<string, array{dark: bool, allowedColors: list<string>}>, templates: array<string, array{backgrounds: list<string>, defaults: array{primary: string, accent: string, background: string, headingFont: string}}>} $data */
        return ThemeCatalog::fromArray($data);
    }
}
