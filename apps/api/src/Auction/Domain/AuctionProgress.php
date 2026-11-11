<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/**
 * Andamento de um lote ao vivo (regra 8): um item em pregão por vez; o próximo abre depois do
 * intervalo configurado (um item pulado não espera); sem itens na fila, o lote termina.
 */
final class AuctionProgress
{
    /** @param list<ItemSnapshot> $items */
    public static function next(array $items, AuctionSettings $settings, \DateTimeImmutable $now): NextStep
    {
        $queued = null;
        $lastClosed = null;
        foreach ($items as $item) {
            if ($item->status->isRunning()) {
                return NextStep::wait();
            }
            if ($item->status === ItemStatus::Queued && ($queued === null || $item->position < $queued->position)) {
                $queued = $item;
            }
            if ($item->closedAt !== null && ($lastClosed === null || $item->closedAt > $lastClosed->closedAt)) {
                $lastClosed = $item;
            }
        }

        if ($queued === null) {
            return NextStep::finish();
        }

        if ($lastClosed !== null && $lastClosed->closedAt !== null && $lastClosed->status !== ItemStatus::Skipped) {
            $readyAt = $lastClosed->closedAt->modify("+{$settings->gapS} seconds");
            if ($now < $readyAt) {
                return NextStep::wait();
            }
        }

        return NextStep::open($queued->id);
    }
}
