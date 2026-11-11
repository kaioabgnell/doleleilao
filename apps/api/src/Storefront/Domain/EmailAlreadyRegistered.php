<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\RuleViolation;

final class EmailAlreadyRegistered extends RuleViolation
{
    public function __construct()
    {
        parent::__construct('email', 'Este e-mail já está cadastrado. Entre com ele ou recupere a senha.');
    }
}
