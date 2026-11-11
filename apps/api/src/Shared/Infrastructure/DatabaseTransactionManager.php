<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure;

use DoleLeilao\Shared\Application\TransactionManager;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(private ConnectionInterface $db) {}

    public function run(callable $fn): mixed
    {
        // 3 tentativas: um deadlock raro entre o relógio e um lance é repetido em vez de virar erro.
        return $this->db->transaction(static fn () => $fn(), 3);
    }
}
