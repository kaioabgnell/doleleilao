<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Domain;

/** Status do produto (RF-C05). Os três últimos são definidos pelo leilão (Fase 3). */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case InAuction = 'in_auction';
    case Sold = 'sold';
    case Unsold = 'unsold';

    public function canBeSetByAuctioneer(): bool
    {
        return $this === self::Draft || $this === self::Available;
    }

    /** Produto num lote ou já arrematado não muda pelo cadastro. */
    public function isLocked(): bool
    {
        return $this === self::InAuction || $this === self::Sold;
    }

    /** Disponível = aparece na loja e conta no passo 2 dos primeiros passos (RF-A12). */
    public function assertPublishable(int $photoCount): void
    {
        if ($this === self::Available && $photoCount < 1) {
            throw new InvalidProduct('status', 'Adicione pelo menos 1 foto para deixar o produto disponível.');
        }
    }

    public function isPublic(): bool
    {
        return $this !== self::Draft;
    }
}
