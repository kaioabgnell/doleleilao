<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Application;

/** Transação do banco. As travas (`FOR UPDATE`) duram até o fim de `run`. */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function run(callable $fn): mixed;
}
