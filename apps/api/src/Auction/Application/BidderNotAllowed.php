<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

/**
 * Participante sem permissão para dar lance (regra 1): cadastro incompleto ou banido.
 * `reason` = incomplete | banned, para a tela mostrar o caminho certo (completar o cadastro).
 */
final class BidderNotAllowed extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function incomplete(): self
    {
        return new self('incomplete', 'Complete seu cadastro para dar lances.');
    }

    public static function banned(): self
    {
        return new self('banned', 'Sua conta está bloqueada nesta loja.');
    }
}
