<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/**
 * Listas fechadas de modelos, cores, fundos e fontes, com as cores permitidas em cada fundo.
 * Vem do pacote design-tokens (fonte única das regras de contraste), D-04 e D-13.
 *
 * @phpstan-type TemplateDefaults array{primary: string, accent: string, background: string, headingFont: string}
 * @phpstan-type CatalogArray array{
 *     colors: list<string>,
 *     fonts: list<string>,
 *     backgrounds: array<string, array{dark: bool, allowedColors: list<string>}>,
 *     templates: array<string, array{backgrounds: list<string>, defaults: TemplateDefaults}>
 * }
 */
final readonly class ThemeCatalog
{
    /** @param CatalogArray $data */
    private function __construct(private array $data) {}

    /** @param CatalogArray $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function hasTemplate(string $template): bool
    {
        return isset($this->data['templates'][$template]);
    }

    /** @return list<string> */
    public function templates(): array
    {
        return array_keys($this->data['templates']);
    }

    /** @return list<string> */
    public function backgroundsOf(string $template): array
    {
        return $this->data['templates'][$template]['backgrounds'] ?? [];
    }

    /** @return TemplateDefaults */
    public function defaultsOf(string $template): array
    {
        return $this->data['templates'][$template]['defaults']
            ?? throw new InvalidStoreTheme('template', 'Modelo de loja inválido.');
    }

    public function hasColor(string $color): bool
    {
        return in_array($color, $this->data['colors'], true);
    }

    public function hasBackground(string $background): bool
    {
        return isset($this->data['backgrounds'][$background]);
    }

    public function hasFont(string $font): bool
    {
        return in_array($font, $this->data['fonts'], true);
    }

    public function isColorAllowedOn(string $color, string $background): bool
    {
        return in_array($color, $this->data['backgrounds'][$background]['allowedColors'] ?? [], true);
    }

    /** @return list<string> */
    public function allowedColorsOn(string $background): array
    {
        return $this->data['backgrounds'][$background]['allowedColors'] ?? [];
    }

    /** @return CatalogArray */
    public function toArray(): array
    {
        return $this->data;
    }
}
