<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** Resumo de um item para decidir o andamento do lote. */
final readonly class ItemSnapshot
{
    public function __construct(
        public int $id,
        public int $position,
        public ItemStatus $status,
        public ?\DateTimeImmutable $closedAt,
    ) {}
}
