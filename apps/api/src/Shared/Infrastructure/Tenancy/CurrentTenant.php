<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Tenancy;

/**
 * A loja (leiloeiro) da requisição atual (specs/04-arquitetura.md#multi-tenant).
 * Painel: vem do token. Loja pública: vem do slug da URL.
 */
final class CurrentTenant
{
    private ?int $auctioneerId = null;

    public function set(int $auctioneerId): void
    {
        $this->auctioneerId = $auctioneerId;
    }

    public function clear(): void
    {
        $this->auctioneerId = null;
    }

    public function id(): int
    {
        return $this->auctioneerId ?? throw new MissingTenant;
    }

    public function has(): bool
    {
        return $this->auctioneerId !== null;
    }

    /**
     * Roda um trecho como se fosse outra loja (jobs e comandos que passam por várias lojas).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(int $auctioneerId, callable $callback): mixed
    {
        $previous = $this->auctioneerId;
        $this->auctioneerId = $auctioneerId;
        try {
            return $callback();
        } finally {
            $this->auctioneerId = $previous;
        }
    }
}
