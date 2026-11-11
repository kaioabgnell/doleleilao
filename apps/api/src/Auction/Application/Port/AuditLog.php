<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application\Port;

use DoleLeilao\Auction\Application\Actor;

/** Log de auditoria do pregão (RF-D05). Somente inserção. */
interface AuditLog
{
    /** @param array<string, mixed> $payload */
    public function record(int $auctionId, Actor $actor, string $action, array $payload, \DateTimeImmutable $at): void;
}
