<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application\Port;

use DoleLeilao\Chat\Application\Author;
use DoleLeilao\Chat\Application\ChatContext;
use DoleLeilao\Chat\Domain\MessageBody;
use DoleLeilao\Chat\Domain\Participant;

interface ChatRepository
{
    /** Lote da loja atual pelo endereço ou id público. Rascunhos e cancelados não têm chat. */
    public function context(string $auctionKey): ?ChatContext;

    public function participant(int $bidderId, ChatContext $context): Participant;

    /** id interno da mensagem que vai ser respondida, se existir no lote e não estiver apagada. */
    public function replyTarget(int $auctionId, string $messagePublicId): ?int;

    /** Grava e devolve o public_id. */
    public function add(ChatContext $context, Author $author, MessageBody $body, ?int $replyToId, \DateTimeImmutable $at): string;

    /** @return array<string, mixed> a mensagem como sai na API e no WebSocket */
    public function view(string $messagePublicId): array;

    public function markDeleted(int $auctionId, string $messagePublicId, int $deletedBy, \DateTimeImmutable $at): bool;

    public function bidderIdOf(string $bidderPublicId): ?int;

    public function mute(ChatContext $context, int $bidderId, ?\DateTimeImmutable $until, int $mutedBy): void;

    public function unmute(int $auctionId, int $bidderId): bool;
}
