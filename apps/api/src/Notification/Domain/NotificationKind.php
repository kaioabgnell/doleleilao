<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Domain;

/** Tipos de aviso do sino (D-43, RF-N03 e RF-N04). */
enum NotificationKind: string
{
    case Welcome = 'welcome';
    case StoreLive = 'store_live';
    case AuctionTomorrow = 'auction_tomorrow';
    case AuctionToday = 'auction_today';
    case AuctionWon = 'auction_won';
    case ParticipationEnabled = 'participation_enabled';
    case ItemSold = 'item_sold';
    case NewParticipant = 'new_participant';
    case ConsignmentRequest = 'consignment_request';
    case WinnerMessage = 'winner_message';

    /** @return list<RecipientType> */
    public function audience(): array
    {
        return match ($this) {
            self::Welcome, self::StoreLive, self::ItemSold, self::NewParticipant, self::ConsignmentRequest => [RecipientType::AuctioneerUser],
            self::AuctionWon, self::ParticipationEnabled, self::WinnerMessage => [RecipientType::Bidder],
            self::AuctionTomorrow, self::AuctionToday => [RecipientType::AuctioneerUser, RecipientType::Bidder],
        };
    }
}
