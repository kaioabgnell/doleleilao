<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Application\Port;

interface PasswordHasher
{
    public function hash(string $plain): string;
}
