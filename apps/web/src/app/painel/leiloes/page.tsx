import type { Metadata } from "next";
import Link from "next/link";
import { CalendarClock, Eye, EyeOff, Gavel, ImageOff, Plus, Users } from "lucide-react";
import { Suspense } from "react";
import { AuctionStatusBadge } from "@/components/auction/status-badges";
import { buttonClass } from "@/components/ui/button-styles";
import type { Auction, AuctionList, AuctionStatus } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { formatAuctionDate } from "@/lib/auction-time";

export const metadata: Metadata = { title: "Leilões" };

const tabs: { key: "" | AuctionStatus; label: string }[] = [
  { key: "", label: "Todos" },
  { key: "live", label: "Ao vivo" },
  { key: "scheduled", label: "Agendados" },
  { key: "draft", label: "Rascunhos" },
  { key: "finished", label: "Encerrados" },
];

export default function AuctionsPage(props: PageProps<"/painel/leiloes">) {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Leilões</h1>
          <p className="mt-1 text-dl-ink-muted">Cada lote é o grupo de itens leiloados num dia, um de cada vez.</p>
        </div>
        <Link href="/painel/leiloes/novo" className={buttonClass("primary")}>
          <Plus aria-hidden className="size-5" />
          Novo lote
        </Link>
      </div>
      <Suspense fallback={<div className="h-64 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <AuctionGrid searchParams={props.searchParams} />
      </Suspense>
    </div>
  );
}

async function AuctionGrid({ searchParams }: Pick<PageProps<"/painel/leiloes">, "searchParams">) {
  const params = await searchParams;
  const status = typeof params.status === "string" ? params.status : "";
  const result = await panelApi<AuctionList>(`/auctioneer/auctions${status ? `?status=${encodeURIComponent(status)}` : ""}`);
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar os leilões.");
  const { data, counts = {} } = result.data;
  const total = Object.values(counts).reduce((a, b) => a + (b ?? 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <nav aria-label="Filtrar por status" className="flex gap-1 overflow-x-auto">
        {tabs.map((tab) => {
          const active = status === tab.key;
          return (
            <Link
              key={tab.key}
              href={tab.key ? `/painel/leiloes?status=${tab.key}` : "/painel/leiloes"}
              aria-current={active ? "page" : undefined}
              className={`flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium ${active ? "bg-dl-ink text-white" : "bg-dl-card text-dl-ink-muted hover:text-dl-ink"}`}
            >
              {tab.label} <span className="tabular opacity-70">{tab.key === "" ? total : (counts[tab.key] ?? 0)}</span>
            </Link>
          );
        })}
      </nav>

      {data.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-12 text-center">
          <Gavel aria-hidden className="size-8 text-dl-copper-icon" />
          <p className="font-semibold text-dl-ink">{status ? "Nenhum lote aqui" : "Nenhum lote ainda"}</p>
          <p className="max-w-sm text-sm text-dl-ink-muted">
            Escolha os produtos disponíveis, a data e a hora. Ao agendar, o leilão aparece na sua loja e os participantes podem marcar
            &ldquo;Quero participar&rdquo;.
          </p>
          {!status ? (
            <Link href="/painel/leiloes/novo" className={buttonClass("primary", "mt-2")}>
              Criar o primeiro lote
            </Link>
          ) : null}
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {data.map((auction) => {
            const href = auction.status === "live" ? `/painel/leiloes/${auction.id}/pregao` : `/painel/leiloes/${auction.id}`;
            return (
              <li key={auction.id}>
                <Link
                  href={href}
                  className="flex h-full gap-3 rounded-card border border-dl-line bg-dl-card p-3 hover:border-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper"
                >
                  {auction.cover?.thumb ? (
                    // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                    <img src={auction.cover.thumb} alt="" className="size-20 shrink-0 rounded-field object-cover" />
                  ) : (
                    <span className="flex size-20 shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted">
                      <ImageOff aria-hidden className="size-6" />
                    </span>
                  )}
                  <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="line-clamp-2 font-semibold text-dl-ink">{auction.name}</span>
                    <span className="flex items-center gap-1.5 text-sm text-dl-ink-muted">
                      <CalendarClock aria-hidden className="size-4 shrink-0" />
                      <span className="tabular">{formatAuctionDate(auction.starts_at)}</span>
                    </span>
                    <span className="mt-auto flex flex-wrap items-center gap-3 text-xs text-dl-ink-muted">
                      <AuctionStatusBadge status={auction.status} />
                      <span className="tabular">{auction.items_count} {auction.items_count === 1 ? "item" : "itens"}</span>
                      <span className="flex items-center gap-1 tabular" title="Marcaram “Quero participar”">
                        <Users aria-hidden className="size-3.5" />
                        {auction.participants_count ?? 0}
                      </span>
                      <StoreVisibility auction={auction} />
                    </span>
                  </span>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

/** Aparece na loja: agendado e ao vivo sempre; encerrado se "Visível no site" estiver ligado; rascunho e cancelado nunca. */
function StoreVisibility({ auction }: { auction: Auction }) {
  const visible =
    auction.status === "scheduled" || auction.status === "live" || (auction.status === "finished" && auction.visible_in_store !== false);
  const Icon = visible ? Eye : EyeOff;
  return (
    <span className="flex items-center gap-1">
      <Icon aria-hidden className="size-3.5" />
      {visible ? "Visível no site" : "Oculto no site"}
    </span>
  );
}
