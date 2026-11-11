<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Application;

final class ItemNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não encontramos este item.');
    }
}
