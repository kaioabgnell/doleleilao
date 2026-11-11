<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

/** MensagemRemovida pelo leiloeiro. */
final readonly class MessageDeleted
{
    public function __construct(public string $auctionPublicId, public bool $privateChannel, public string $messagePublicId) {}
}
