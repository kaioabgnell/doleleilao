<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

/** Regra do lote ou do pregão violada (status, datas, configurações, itens). */
final class InvalidAuction extends RuleViolation {}
