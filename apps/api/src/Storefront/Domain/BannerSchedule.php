<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/** Período de exibição do banner (RF-B06). Início incluído, fim excluído. Sem datas = sempre. */
final readonly class BannerSchedule
{
    public function __construct(
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
    ) {
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidBannerSchedule('ends_at', 'O fim precisa ser depois do início.');
        }
    }

    public function showsAt(\DateTimeImmutable $now, bool $active): bool
    {
        return $active
            && ($this->startsAt === null || $now >= $this->startsAt)
            && ($this->endsAt === null || $now < $this->endsAt);
    }
}
