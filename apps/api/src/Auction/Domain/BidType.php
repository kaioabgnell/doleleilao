<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

enum BidType: string
{
    case Manual = 'manual';
    case Auto = 'auto';
}
