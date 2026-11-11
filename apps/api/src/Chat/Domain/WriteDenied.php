<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/** Por que alguém não pode escrever agora. */
enum WriteDenied: string
{
    case Disabled = 'disabled';
    case Closed = 'closed';
    case Login = 'login';
    case Incomplete = 'incomplete';
    case Banned = 'banned';
    case Muted = 'muted';

    public function message(): string
    {
        return match ($this) {
            self::Disabled => 'O chat está desligado neste leilão.',
            self::Closed => 'O leilão terminou. O chat fica só para leitura.',
            self::Login => 'Entre na sua conta para escrever no chat.',
            self::Incomplete => 'Complete seu cadastro para escrever no chat.',
            self::Banned => 'Sua conta está bloqueada nesta loja.',
            self::Muted => 'O leiloeiro silenciou você neste leilão.',
        };
    }
}
