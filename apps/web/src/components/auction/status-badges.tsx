import type { AuctionStatus, ItemStatus } from "@/lib/api-types";

/** Estados fixos do leilão (RF-F04, D-05): as cores não mudam com a personalização da loja. */
const auctionLabels: Record<AuctionStatus, [string, string]> = {
  draft: ["Rascunho", "bg-dl-page text-dl-ink-muted ring-1 ring-dl-line"],
  scheduled: ["Agendado", "bg-state-winning/10 text-state-winning"],
  live: ["Ao vivo", "bg-state-live text-white"],
  finished: ["Encerrado", "bg-state-closed/10 text-state-closed"],
  cancelled: ["Cancelado", "bg-dl-page text-dl-ink-muted ring-1 ring-dl-line"],
};

export function AuctionStatusBadge({ status, publicLabel = false }: { status: AuctionStatus; publicLabel?: boolean }) {
  const [label, tone] = auctionLabels[status];
  if (status === "live") return <LiveBadge />;
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone}`}>
      {publicLabel && status === "scheduled" ? "Em breve" : label}
    </span>
  );
}

/** Selo "AO VIVO" com ponto pulsante (RF-F04). */
export function LiveBadge({ label = "Ao vivo" }: { label?: string }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-state-live px-2.5 py-0.5 text-xs font-bold tracking-wide text-white uppercase">
      <span aria-hidden className="relative flex size-2">
        <span className="absolute inline-flex size-full animate-ping rounded-full bg-white opacity-75 motion-reduce:animate-none" />
        <span className="relative inline-flex size-2 rounded-full bg-white" />
      </span>
      {label}
    </span>
  );
}

const itemLabels: Record<ItemStatus, [string, string]> = {
  queued: ["Na fila", "bg-dl-page text-dl-ink-muted ring-1 ring-dl-line"],
  open: ["Em pregão", "bg-state-live text-white"],
  paused: ["Pausado", "bg-dl-ink text-white"],
  closed_sold: ["Arrematado", "bg-state-closed/10 text-state-closed"],
  closed_unsold: ["Sem lances", "bg-dl-page text-dl-ink-muted ring-1 ring-dl-line"],
  skipped: ["Pulado", "bg-dl-page text-dl-ink-muted ring-1 ring-dl-line"],
};

export function ItemStatusBadge({ status }: { status: ItemStatus }) {
  const [label, tone] = itemLabels[status];
  return <span className={`inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>;
}
