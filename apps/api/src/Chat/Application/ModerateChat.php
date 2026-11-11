<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

use DoleLeilao\Chat\Application\Port\ChatRepository;
use DoleLeilao\Chat\Domain\InvalidMessage;
use DoleLeilao\Chat\Domain\MuteDuration;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;

/** Moderação do chat pelo leiloeiro (RF-F08): apagar mensagem e silenciar no leilão. */
final readonly class ModerateChat
{
    public function __construct(private ChatRepository $chat, private Clock $clock, private EventBus $events) {}

    public function delete(string $auctionKey, string $messagePublicId, int $auctioneerUserId): void
    {
        $context = $this->chat->context($auctionKey) ?? throw new ChatNotFound;
        if (! $this->chat->markDeleted($context->auctionId, $messagePublicId, $auctioneerUserId, $this->clock->now())) {
            throw new InvalidMessage('message', 'Esta mensagem não existe ou já foi apagada.');
        }
        $this->events->publish(new MessageDeleted($context->auctionPublicId, $context->settings->loggedInOnly, $messagePublicId));
    }

    public function mute(string $auctionKey, string $bidderPublicId, MuteDuration $duration, int $auctioneerUserId): ?\DateTimeImmutable
    {
        $context = $this->chat->context($auctionKey) ?? throw new ChatNotFound;
        $bidderId = $this->bidder($bidderPublicId);
        $until = $duration->until($this->clock->now());
        $this->chat->mute($context, $bidderId, $until, $auctioneerUserId);
        $this->events->publish(new BidderMuted($bidderId, $context->auctionPublicId, true, $until));

        return $until;
    }

    public function unmute(string $auctionKey, string $bidderPublicId): void
    {
        $context = $this->chat->context($auctionKey) ?? throw new ChatNotFound;
        $bidderId = $this->bidder($bidderPublicId);
        if ($this->chat->unmute($context->auctionId, $bidderId)) {
            $this->events->publish(new BidderMuted($bidderId, $context->auctionPublicId, false, null));
        }
    }

    private function bidder(string $publicId): int
    {
        return $this->chat->bidderIdOf($publicId) ?? throw new InvalidMessage('bidder', 'Participante não encontrado.');
    }
}
