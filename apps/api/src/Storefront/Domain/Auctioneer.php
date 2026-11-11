<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** O leiloeiro (tenant). Nesta fase o agregado cuida só do endereço da loja. */
final class Auctioneer
{
    public function __construct(
        public readonly int $id,
        private StoreSlug $slug,
        private ?\DateTimeImmutable $slugChangedAt,
    ) {}

    public function slug(): StoreSlug
    {
        return $this->slug;
    }

    public function slugChangedAt(): ?\DateTimeImmutable
    {
        return $this->slugChangedAt;
    }

    /** Troca o endereço da loja e devolve o antigo, que passa a redirecionar (RF-A06). */
    public function changeSlug(StoreSlug $new, \DateTimeImmutable $now, SlugChangePolicy $policy): StoreSlug
    {
        if ($new->equals($this->slug)) {
            throw InvalidSlug::unchanged();
        }
        $policy->assertCanChange($this->slugChangedAt, $now);

        $old = $this->slug;
        $this->slug = $new;
        $this->slugChangedAt = $now;

        return $old;
    }
}
