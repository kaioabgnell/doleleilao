<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

/** MensagemEnviada: vai para o canal do chat do lote (público ou privado, conforme RF-D03). */
final readonly class MessagePosted
{
    /** @param array<string, mixed> $message */
    public function __construct(public string $auctionPublicId, public bool $privateChannel, public array $message) {}
}
