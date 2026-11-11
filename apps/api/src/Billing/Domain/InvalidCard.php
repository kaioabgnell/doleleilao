<?php

declare(strict_types=1);

namespace DoleLeilao\Billing\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

/** Dado do cartão recusado (número, nome, validade ou código de segurança). */
final class InvalidCard extends RuleViolation {}
