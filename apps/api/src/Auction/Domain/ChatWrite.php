<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** RF-D03: quem pode escrever no chat (sempre logado, briefing). */
enum ChatWrite: string
{
    case LoggedIn = 'logged_in';
    case Eligible = 'eligible';
}
