<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/**
 * Regra de negócio violada por um dado de entrada. A camada HTTP transforma em 422
 * (`application/problem+json`) com o erro no campo `$field`.
 */
abstract class RuleViolation extends \DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
