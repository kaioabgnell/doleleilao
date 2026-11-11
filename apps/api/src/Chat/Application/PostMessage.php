<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

use DoleLeilao\Chat\Application\Port\ChatRepository;
use DoleLeilao\Chat\Application\Port\ChatThrottle;
use DoleLeilao\Chat\Domain\ChatAccess;
use DoleLeilao\Chat\Domain\InvalidMessage;
use DoleLeilao\Chat\Domain\MessageBody;
use DoleLeilao\Chat\Domain\Participant;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Domain\Clock;

/** Enviar mensagem no chat do auditório (RF-F05, RF-F06), respeitando RF-D03 e a moderação. */
final readonly class PostMessage
{
    public function __construct(
        private ChatRepository $chat,
        private ChatThrottle $throttle,
        private Clock $clock,
        private EventBus $events,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $auctionKey, Author $author, string $text, ?string $replyTo = null): array
    {
        $context = $this->chat->context($auctionKey) ?? throw new ChatNotFound;
        $who = $author->isAuctioneer() || $author->bidderId === null
            ? Participant::auctioneer()
            : $this->chat->participant($author->bidderId, $context);

        $denied = ChatAccess::writeDenial($context->settings, $who, $context->open, $this->clock->now());
        if ($denied !== null) {
            throw new ChatWriteDeniedException($denied);
        }

        $body = MessageBody::from($text);
        if ($this->throttle->tooMany("chat:{$context->auctionId}:{$author->key()}")) {
            throw new ChatThrottled;
        }

        $replyToId = null;
        if ($replyTo !== null && $replyTo !== '') {
            $replyToId = $this->chat->replyTarget($context->auctionId, $replyTo)
                ?? throw new InvalidMessage('reply_to_id', 'A mensagem que você quer responder não existe mais.');
        }

        $publicId = $this->chat->add($context, $author, $body, $replyToId, $this->clock->now());
        $message = $this->chat->view($publicId);
        $this->events->publish(new MessagePosted($context->auctionPublicId, $context->settings->loggedInOnly, $message));

        return $message;
    }
}
