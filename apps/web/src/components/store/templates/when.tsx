"use client";

import { useSyncExternalStore } from "react";
import { Countdown } from "@/components/auction/countdown";
import { LiveBadge } from "@/components/auction/status-badges";
import { formatAuctionDate } from "@/lib/auction-time";

const noop = () => () => {};
const SOON_MS = 48 * 3_600_000;

/**
 * Quando é o leilão: "Ao vivo" ou a data; nas últimas 48 h, também a contagem regressiva.
 * A conta da hora roda só no navegador (nada de relógio no servidor).
 */
export function AuctionWhen({ startsAt, live, variant }: { startsAt: string; live: boolean; variant: "inline" | "blocks" | "serif" }) {
  const soon = useSyncExternalStore(noop, () => Date.parse(startsAt) - Date.now() < SOON_MS, () => false);

  if (live) return <LiveBadge label="Ao vivo agora" />;

  const date = <span className="tabular">{formatAuctionDate(startsAt)}</span>;
  if (!soon) return <p className="text-store-text-muted">{date}</p>;

  if (variant === "blocks") {
    return (
      <div className="flex flex-col gap-2">
        <p className="text-sm text-store-text-muted">{date}</p>
        <p className="flex items-center justify-between gap-3 rounded-field bg-store-bg px-4 py-3">
          <span className="text-sm text-store-text-muted">Começa em</span>
          <Countdown endsAt={startsAt} offsetMs={0} urgentBelowMs={0} className="text-2xl font-bold" />
        </p>
      </div>
    );
  }
  return (
    <p className="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-store-text-muted">
      {date}
      <span aria-hidden>·</span>
      <span>
        começa em{" "}
        <Countdown endsAt={startsAt} offsetMs={0} urgentBelowMs={0} className={variant === "serif" ? "font-store-heading text-lg text-store-text [font-variant-numeric:lining-nums_tabular-nums]" : "font-semibold text-store-text"} />
      </span>
    </p>
  );
}
