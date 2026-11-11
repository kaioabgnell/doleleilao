<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Application;

use DoleLeilao\Chat\Domain\WriteDenied;

final class ChatWriteDeniedException extends \RuntimeException
{
    public function __construct(public readonly WriteDenied $reason)
    {
        parent::__construct($reason->message());
    }
}
