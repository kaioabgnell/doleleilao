import Link from "next/link";
import { CalendarClock, Gavel } from "lucide-react";
import type { Auction } from "@/lib/api-types";
import { formatAuctionDate } from "@/lib/auction-time";
import { AuctionStatusBadge } from "./status-badges";

/** Cartão de leilão na loja (lista e home). */
export function AuctionCard({ slug, auction, priority = false }: { slug: string; auction: Auction; priority?: boolean }) {
  const cover = auction.cover;
  return (
    <Link
      href={`/${slug}/leilao/${auction.slug}`}
      className="group flex w-full flex-col overflow-hidden rounded-card border border-store-border bg-store-surface transition-colors hover:border-store-primary focus-visible:outline-2 focus-visible:outline-store-primary"
    >
      <div className="relative aspect-[16/10] bg-store-bg">
        {cover?.card.webp ? (
          <picture>
            {cover.card.avif ? <source srcSet={cover.card.avif} type="image/avif" /> : null}
            <img
              src={cover.card.webp}
              alt=""
              className="size-full object-cover"
              loading={priority ? "eager" : "lazy"}
              fetchPriority={priority ? "high" : undefined}
              decoding="async"
            />
          </picture>
        ) : (
          <span className="flex size-full items-center justify-center text-store-text-muted">
            <Gavel aria-hidden className="size-10" />
          </span>
        )}
        <span className="absolute top-3 left-3">
          <AuctionStatusBadge status={auction.status} publicLabel />
        </span>
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-4">
        <h3 className="font-store-heading text-lg font-semibold text-store-text group-hover:text-store-link">{auction.name}</h3>
        <p className="flex items-center gap-1.5 text-sm text-store-text-muted">
          <CalendarClock aria-hidden className="size-4 shrink-0" />
          <span className="tabular">{formatAuctionDate(auction.starts_at)}</span>
        </p>
        <p className="mt-auto text-sm text-store-text-muted tabular">
          {auction.items_count} {auction.items_count === 1 ? "item" : "itens"}
        </p>
      </div>
    </Link>
  );
}
