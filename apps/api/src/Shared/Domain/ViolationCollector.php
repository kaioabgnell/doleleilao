<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Domain;

/** Junta as violações de vários campos e lança todas juntas em `RuleViolations`. */
final class ViolationCollector
{
    /** @var list<RuleViolation> */
    private array $violations = [];

    /**
     * @template T
     *
     * @param  callable(): T  $build
     * @return T|null
     */
    public function attempt(callable $build): mixed
    {
        try {
            return $build();
        } catch (RuleViolation $violation) {
            $this->violations[] = $violation;

            return null;
        } catch (RuleViolations $violations) {
            array_push($this->violations, ...$violations->violations);

            return null;
        }
    }

    public function add(RuleViolation $violation): void
    {
        $this->violations[] = $violation;
    }

    public function hasErrorIn(string $field): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->field === $field) {
                return true;
            }
        }

        return false;
    }

    public function throwIfAny(): void
    {
        if ($this->violations !== []) {
            throw new RuleViolations($this->violations);
        }
    }
}
