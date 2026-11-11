<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/**
 * Quem arca com a taxa do Dole Leilão sobre os itens arrematados do lote (D-47, D-54).
 * Nos dois casos o débito sai do cartão do leiloeiro: "repassar" quer dizer que ele cobra a taxa
 * do vencedor junto com o valor do arremate.
 */
enum FeePayer: string
{
    case Auctioneer = 'auctioneer';
    case Bidder = 'bidder';

    public function label(): string
    {
        return match ($this) {
            self::Auctioneer => 'Assumida pelo leiloeiro',
            self::Bidder => 'Repassada ao vencedor',
        };
    }
}
