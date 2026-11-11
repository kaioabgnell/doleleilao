<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/** Regras de leitura e escrita do chat (RF-D03, RF-F08, RF-F10, RF-G02). */
final class ChatAccess
{
    /** Depois do fim do leilão, a conversa fica na loja por este tempo e some (o painel continua vendo). */
    public const HISTORY_HOURS = 12;

    /** @param  ?\DateTimeImmutable  $finishedAt  quando o lote terminou (null: ainda não terminou) */
    public static function historyVisible(?\DateTimeImmutable $finishedAt, \DateTimeImmutable $now): bool
    {
        return $finishedAt === null || $now < $finishedAt->modify('+'.self::HISTORY_HOURS.' hours');
    }

    public static function canRead(ChatSettings $settings, Participant $who): bool
    {
        if (! $settings->enabled || $who->banned) {
            return false;
        }

        return ! $settings->loggedInOnly || $who->loggedIn;
    }

    /**
     * Motivo para não deixar escrever, ou null se pode.
     *
     * @param  bool  $open  o lote ainda não terminou (agendado ou ao vivo)
     */
    public static function writeDenial(ChatSettings $settings, Participant $who, bool $open, \DateTimeImmutable $now): ?WriteDenied
    {
        return match (true) {
            ! $settings->enabled => WriteDenied::Disabled,
            ! $open => WriteDenied::Closed,
            $who->isAuctioneer => null,
            ! $who->loggedIn => WriteDenied::Login,
            $who->banned => WriteDenied::Banned,
            $who->isMuted($now) => WriteDenied::Muted,
            $settings->eligibleToWrite && ! $who->eligible => WriteDenied::Incomplete,
            default => null,
        };
    }
}
