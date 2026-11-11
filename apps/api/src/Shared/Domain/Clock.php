<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * Porta do relógio. O domínio nunca chama `new DateTime()` direto: o horário do servidor
 * decide o encerramento dos itens (RNF-07), e os testes usam um relógio fixo.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
