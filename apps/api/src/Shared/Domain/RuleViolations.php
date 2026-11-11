<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/** Várias regras violadas de uma vez, para o formulário mostrar todos os erros juntos. */
final class RuleViolations extends \DomainException
{
    /** @param non-empty-list<RuleViolation> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct($violations[0]->getMessage());
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        $errors = [];
        foreach ($this->violations as $violation) {
            $errors[$violation->field][] = $violation->getMessage();
        }

        return $errors;
    }
}
