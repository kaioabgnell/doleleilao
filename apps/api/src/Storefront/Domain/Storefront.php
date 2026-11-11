<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\Phone;

/**
 * A loja do leiloeiro: aparência (modelo, cores, fundo, fonte) e contato (RF-B01 a RF-B05, RF-B08).
 * Sem modelo escolhido, `theme()` é nulo. O endereço (opcional) é de onde a loja envia os produtos
 * e não aparece para os participantes.
 */
final class Storefront
{
    /** @param array<string, string> $socials */
    public function __construct(
        public readonly int $auctioneerId,
        private ?StoreTheme $theme,
        private ?Phone $contactPhone,
        private ?string $contactEmail,
        private array $socials,
        private ?Address $address = null,
    ) {}

    public function theme(): ?StoreTheme
    {
        return $this->theme;
    }

    public function contactPhone(): ?Phone
    {
        return $this->contactPhone;
    }

    public function contactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /** @return array<string, string> */
    public function socials(): array
    {
        return $this->socials;
    }

    public function address(): ?Address
    {
        return $this->address;
    }

    /**
     * Muda a aparência. Trocar de modelo mantém o que continua permitido. Trocar só o fundo ajusta
     * as cores que perderiam contraste; trocar cor e fundo juntos exige uma combinação válida.
     */
    public function customize(
        ThemeCatalog $catalog,
        ?string $template = null,
        ?string $primary = null,
        ?string $accent = null,
        ?string $background = null,
        ?string $headingFont = null,
    ): void {
        $theme = $this->theme;

        if ($template !== null) {
            $theme = $theme?->switchTemplate($template, $catalog) ?? StoreTheme::defaultsFor($template, $catalog);
        }

        if ($primary === null && $accent === null && $background === null && $headingFont === null) {
            $this->theme = $theme;

            return;
        }

        if ($theme === null) {
            throw new InvalidStoreTheme('template', 'Escolha o modelo da loja primeiro.');
        }

        $this->theme = $theme->with(
            $catalog,
            primary: $primary,
            accent: $accent,
            background: $background,
            headingFont: $headingFont,
            adjustColors: $background !== null && $primary === null && $accent === null,
        );
    }

    public function changeContactPhone(?Phone $phone): void
    {
        $this->contactPhone = $phone;
    }

    public function changeContactEmail(?string $email): void
    {
        $this->contactEmail = $email;
    }

    /** @param array<string, string> $socials */
    public function changeSocials(array $socials): void
    {
        $this->socials = $socials;
    }

    public function changeAddress(?Address $address): void
    {
        $this->address = $address;
    }
}
