<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** queued → open ⇄ paused → closed_sold | closed_unsold | skipped */
enum ItemStatus: string
{
    case Queued = 'queued';
    case Open = 'open';
    case Paused = 'paused';
    case ClosedSold = 'closed_sold';
    case ClosedUnsold = 'closed_unsold';
    case Skipped = 'skipped';

    public function isClosed(): bool
    {
        return in_array($this, [self::ClosedSold, self::ClosedUnsold, self::Skipped], true);
    }

    /** Em pregão: aberto ou pausado. */
    public function isRunning(): bool
    {
        return $this === self::Open || $this === self::Paused;
    }
}
