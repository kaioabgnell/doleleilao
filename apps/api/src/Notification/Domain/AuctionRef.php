<?php

declare(strict_types=1);

namespace DoleLeilao\Notification\Domain;

/** O que a notificação precisa saber de um lote de leilão. */
final readonly class AuctionRef
{
    public function __construct(
        public string $publicId,
        public string $name,
        public \DateTimeImmutable $startsAt,
        /** Caminho no site: o auditório (participante) ou o lote no painel (leiloeiro). */
        public string $url,
    ) {}
}
