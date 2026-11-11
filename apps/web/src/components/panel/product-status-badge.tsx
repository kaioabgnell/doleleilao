import type { ProductStatus } from "@/lib/api-types";

const labels: Record<ProductStatus, [string, string]> = {
  draft: ["Rascunho", "bg-dl-page text-dl-ink-muted"],
  available: ["Disponível", "bg-state-winning/10 text-state-winning"],
  in_auction: ["Em leilão", "bg-state-live/10 text-state-live"],
  sold: ["Arrematado", "bg-state-closed/10 text-state-closed"],
  unsold: ["Não arrematado", "bg-dl-page text-dl-ink-muted"],
};

export function ProductStatusBadge({ status }: { status: ProductStatus }) {
  const [label, tone] = labels[status];
  return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>;
}
