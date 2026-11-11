<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

use DoleLeilao\Chat\Domain\ChatSettings;

/** O lote visto pelo chat: configuração, se ainda está aberto (agendado ou ao vivo) e quando terminou. */
final readonly class ChatContext
{
    public function __construct(
        public int $auctionId,
        public string $auctionPublicId,
        public int $auctioneerId,
        public ChatSettings $settings,
        public bool $open,
        public ?\DateTimeImmutable $finishedAt = null,
    ) {}
}
