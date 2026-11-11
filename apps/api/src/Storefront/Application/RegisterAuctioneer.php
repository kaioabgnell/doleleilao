<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application;

use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Domain\TaxDocumentType;
use DoleLeilao\Shared\Domain\ViolationCollector;
use DoleLeilao\Storefront\Application\Port\AuctioneerRepository;
use DoleLeilao\Storefront\Application\Port\PasswordHasher;
use DoleLeilao\Storefront\Domain\AuctioneerRegistered;
use DoleLeilao\Storefront\Domain\DisplayName;
use DoleLeilao\Storefront\Domain\DocumentAlreadyRegistered;
use DoleLeilao\Storefront\Domain\EmailAlreadyRegistered;
use DoleLeilao\Storefront\Domain\InvalidSlug;
use DoleLeilao\Storefront\Domain\StoreSlug;

/**
 * Cadastro do leiloeiro (RF-A01 a RF-A03): cria o tenant, o usuário dono e a loja ainda sem modelo.
 * Devolve todos os erros de uma vez para o formulário.
 */
final readonly class RegisterAuctioneer
{
    public function __construct(
        private AuctioneerRepository $auctioneers,
        private PasswordHasher $hasher,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function handle(RegisterAuctioneerInput $input): RegisteredAuctioneer
    {
        $errors = new ViolationCollector;

        $type = TaxDocumentType::tryFrom($input->documentType) ?? TaxDocumentType::Cpf;
        $document = $errors->attempt(fn () => TaxDocument::of($type, $input->document));
        $slug = $errors->attempt(fn () => StoreSlug::fromString($input->slug));
        $displayName = $errors->attempt(fn () => $input->displayName !== null && trim($input->displayName) !== ''
            ? DisplayName::fromString($input->displayName)
            : DisplayName::suggestFrom($input->companyName));
        $phone = $errors->attempt(fn () => Phone::fromString($input->phone));
        $email = mb_strtolower(trim($input->email));

        if ($slug !== null && $this->auctioneers->slugIsTaken($slug)) {
            $errors->add(InvalidSlug::taken());
        }
        if ($document !== null && $this->auctioneers->documentIsTaken($document)) {
            $errors->add(new DocumentAlreadyRegistered($document->type));
        }
        if ($this->auctioneers->emailIsTaken($email)) {
            $errors->add(new EmailAlreadyRegistered);
        }

        $errors->throwIfAny();
        assert($document !== null && $slug !== null && $displayName !== null && $phone !== null);

        $registered = $this->auctioneers->register(new NewAuctioneer(
            companyName: trim($input->companyName),
            displayName: $displayName,
            slug: $slug,
            document: $document,
            ownerName: trim($input->ownerName),
            email: $email,
            phone: $phone,
            passwordHash: $this->hasher->hash($input->password),
            registeredAt: $this->clock->now(),
        ));

        $this->events->publish(new AuctioneerRegistered($registered->auctioneerId, $registered->ownerUserId));

        return $registered;
    }
}
