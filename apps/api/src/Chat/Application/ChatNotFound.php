<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

final class ChatNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não encontramos este leilão.');
    }
}
