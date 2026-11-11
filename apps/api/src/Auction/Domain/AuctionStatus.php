<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** RF-D06: draft → scheduled → live → finished | cancelled */
enum AuctionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    /** Antes de começar, o lote é editado pelo cadastro. Depois, só pelos controles do pregão (RF-D01). */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Scheduled;
    }

    /** Aparece na loja. */
    public function isPublic(): bool
    {
        return $this !== self::Draft && $this !== self::Cancelled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Scheduled => 'Agendado',
            self::Live => 'Ao vivo',
            self::Finished => 'Encerrado',
            self::Cancelled => 'Cancelado',
        };
    }
}
