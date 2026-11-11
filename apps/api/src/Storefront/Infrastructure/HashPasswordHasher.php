<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Infrastructure;

use DoleLeilao\Storefront\Application\Port\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;

/** Argon2id (RNF-08), configurado em HASH_DRIVER. */
final readonly class HashPasswordHasher implements PasswordHasher
{
    public function __construct(private Hasher $hasher) {}

    public function hash(string $plain): string
    {
        return $this->hasher->make($plain);
    }
}
