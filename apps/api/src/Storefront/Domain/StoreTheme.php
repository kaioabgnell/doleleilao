<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/**
 * Aparência escolhida pelo leiloeiro: modelo, cor primária, cor de destaque, fundo e fonte dos títulos
 * (RF-B01, RF-B03 a RF-B05). Sempre uma combinação válida do catálogo.
 */
final readonly class StoreTheme
{
    private function __construct(
        public string $template,
        public string $primary,
        public string $accent,
        public string $background,
        public string $headingFont,
    ) {}

    public static function defaultsFor(string $template, ThemeCatalog $catalog): self
    {
        if (! $catalog->hasTemplate($template)) {
            throw new InvalidStoreTheme('template', 'Modelo de loja inválido.');
        }
        $d = $catalog->defaultsOf($template);

        return self::create($template, $d['primary'], $d['accent'], $d['background'], $d['headingFont'], $catalog);
    }

    public static function create(
        string $template,
        string $primary,
        string $accent,
        string $background,
        string $headingFont,
        ThemeCatalog $catalog,
    ): self {
        if (! $catalog->hasTemplate($template)) {
            throw new InvalidStoreTheme('template', 'Modelo de loja inválido.');
        }
        if (! $catalog->hasBackground($background)) {
            throw new InvalidStoreTheme('background', 'Fundo inválido.');
        }
        if (! in_array($background, $catalog->backgroundsOf($template), true)) {
            throw new InvalidStoreTheme('background', 'Este fundo não está disponível neste modelo.');
        }
        foreach (['primary_color' => $primary, 'accent_color' => $accent] as $field => $color) {
            if (! $catalog->hasColor($color)) {
                throw new InvalidStoreTheme($field, 'Cor inválida.');
            }
            if (! $catalog->isColorAllowedOn($color, $background)) {
                throw new InvalidStoreTheme($field, 'Esta cor não tem contraste suficiente com o fundo escolhido.');
            }
        }
        if (! $catalog->hasFont($headingFont)) {
            throw new InvalidStoreTheme('heading_font', 'Fonte inválida.');
        }

        return new self($template, $primary, $accent, $background, $headingFont);
    }

    /**
     * Troca o modelo sem perder a personalização que continua permitida (RF-B01): mantém a fonte,
     * mantém o fundo se ele existir no novo modelo e mantém as cores que têm contraste com o fundo.
     */
    public function switchTemplate(string $template, ThemeCatalog $catalog): self
    {
        if (! $catalog->hasTemplate($template)) {
            throw new InvalidStoreTheme('template', 'Modelo de loja inválido.');
        }
        $background = in_array($this->background, $catalog->backgroundsOf($template), true)
            ? $this->background
            : $catalog->defaultsOf($template)['background'];

        return self::adjusted($template, $this->primary, $this->accent, $background, $this->headingFont, $catalog);
    }

    /**
     * Altera cores, fundo ou fonte. Com `adjustColors`, as cores que perderiam contraste no novo fundo
     * são trocadas pelos padrões do modelo, em vez de recusar a mudança.
     */
    public function with(
        ThemeCatalog $catalog,
        ?string $primary = null,
        ?string $accent = null,
        ?string $background = null,
        ?string $headingFont = null,
        bool $adjustColors = false,
    ): self {
        $args = [
            $this->template,
            $primary ?? $this->primary,
            $accent ?? $this->accent,
            $background ?? $this->background,
            $headingFont ?? $this->headingFont,
            $catalog,
        ];

        return $adjustColors ? self::adjusted(...$args) : self::create(...$args);
    }

    /** @return array{template: string, primary: string, accent: string, background: string, headingFont: string} */
    public function toArray(): array
    {
        return [
            'template' => $this->template,
            'primary' => $this->primary,
            'accent' => $this->accent,
            'background' => $this->background,
            'headingFont' => $this->headingFont,
        ];
    }

    private static function adjusted(
        string $template,
        string $primary,
        string $accent,
        string $background,
        string $headingFont,
        ThemeCatalog $catalog,
    ): self {
        $defaults = $catalog->defaultsOf($template);
        $fallback = static function (string $color, string $default) use ($catalog, $background): string {
            if ($catalog->isColorAllowedOn($color, $background)) {
                return $color;
            }
            if ($catalog->isColorAllowedOn($default, $background)) {
                return $default;
            }

            return $catalog->allowedColorsOn($background)[0] ?? $default;
        };

        return self::create(
            $template,
            $fallback($primary, $defaults['primary']),
            $fallback($accent, $defaults['accent']),
            $background,
            $headingFont,
            $catalog,
        );
    }
}
