<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/** RF-D03: quem pode ver o chat. */
enum ChatVisibility: string
{
    case Everyone = 'everyone';
    case LoggedIn = 'logged_in';
}
