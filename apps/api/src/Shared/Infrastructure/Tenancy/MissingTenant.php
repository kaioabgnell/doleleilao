<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Tenancy;

/** Consulta a dados de uma loja sem saber qual é a loja: falha fechada, nunca devolve dados de todas. */
final class MissingTenant extends \LogicException
{
    public function __construct()
    {
        parent::__construct('Nenhuma loja definida para esta consulta (CurrentTenant). Use o middleware de tenant ou CurrentTenant::runAs().');
    }
}
