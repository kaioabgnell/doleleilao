<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\ViolationCollector;
use DoleLeilao\Storefront\Application\Port\StorefrontRepository;
use DoleLeilao\Storefront\Domain\Storefront;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Domain\ThemeCatalog;

/** Tela "Dados da empresa": contato, endereço de envio e aparência da loja (RF-A11, RF-B01 a RF-B05, RF-B08). */
final readonly class UpdateStorefront
{
    public function __construct(
        private StorefrontRepository $storefronts,
        private ThemeCatalog $catalog,
        private PublishStorefrontIfReady $publish,
        private EventBus $events,
    ) {}

    public function handle(UpdateStorefrontInput $input): Storefront
    {
        $storefront = $this->storefronts->get($input->auctioneerId);
        $errors = new ViolationCollector;

        $errors->attempt(fn () => $storefront->customize(
            $this->catalog,
            template: $input->template,
            primary: $input->primaryColor,
            accent: $input->accentColor,
            background: $input->background,
            headingFont: $input->headingFont,
        ));

        if (in_array('contact_phone', $input->cleared, true)) {
            $storefront->changeContactPhone(null);
        } elseif ($input->contactPhone !== null) {
            $phone = $errors->attempt(fn () => Phone::fromString($input->contactPhone, 'contact_phone'));
            if ($phone !== null) {
                $storefront->changeContactPhone($phone);
            }
        }

        if (in_array('contact_email', $input->cleared, true)) {
            $storefront->changeContactEmail(null);
        } elseif ($input->contactEmail !== null) {
            $storefront->changeContactEmail(mb_strtolower(trim($input->contactEmail)));
        }

        if ($input->socials !== null) {
            $storefront->changeSocials($input->socials);
        }

        if (in_array('address', $input->cleared, true)) {
            $storefront->changeAddress(null);
        } elseif ($input->address !== null) {
            $address = $errors->attempt(fn () => Address::fromArray($input->address));
            if ($address !== null) {
                $storefront->changeAddress($address);
            }
        }

        $errors->throwIfAny();
        $this->storefronts->save($storefront);
        $this->publish->handle($input->auctioneerId);
        $this->events->publish(new StorefrontChanged($input->auctioneerId));

        return $storefront;
    }
}
