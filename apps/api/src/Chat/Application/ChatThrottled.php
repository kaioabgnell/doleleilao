<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

final class ChatThrottled extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Calma! Espere um pouco para mandar outra mensagem.');
    }
}
