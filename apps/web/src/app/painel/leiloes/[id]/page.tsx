import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, ExternalLink, Radio } from "lucide-react";
import { Suspense } from "react";
import { AuctionStatusBadge, ItemStatusBadge } from "@/components/auction/status-badges";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import type { Auction, AuctionList, BillingSettings } from "@/lib/api-types";
import { getMe, panelApi } from "@/lib/auctioneer";
import { formatAuctionDate } from "@/lib/auction-time";
import { formatCents } from "@/lib/money";
import { AuctionForm } from "../auction-form";
import { ClosedAuctionActions } from "./closed-auction-actions";

export const metadata: Metadata = { title: "Lote de leilão" };

export default function AuctionPage(props: PageProps<"/painel/leiloes/[id]">) {
  return (
    <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
      <AuctionDetail {...props} />
    </Suspense>
  );
}

async function AuctionDetail({ params, searchParams }: PageProps<"/painel/leiloes/[id]">) {
  const { id } = await params;
  const query = await searchParams;
  const [result, defaults, me, billing] = await Promise.all([
    panelApi<Auction>(`/auctioneer/auctions/${encodeURIComponent(id)}`),
    panelApi<AuctionList>("/auctioneer/auctions?status=draft"),
    getMe(),
    panelApi<BillingSettings>("/auctioneer/billing"),
  ]);
  if (!result.ok) {
    if (result.status === 404) notFound();
    throw new Error(result.problem.detail ?? "Não foi possível carregar o lote.");
  }
  const auction = result.data;
  const editable = auction.status === "draft" || auction.status === "scheduled";
  const storeUrl = `/${me.auctioneer.slug}/leilao/${auction.slug}`;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3">
        <Link href="/painel/leiloes" className="inline-flex min-h-11 w-fit items-center gap-1 text-sm font-medium text-dl-copper hover:underline">
          <ArrowLeft aria-hidden className="size-4" />
          Leilões
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">{auction.name}</h1>
              <AuctionStatusBadge status={auction.status} />
            </div>
            <p className="mt-1 text-dl-ink-muted tabular">{formatAuctionDate(auction.starts_at)}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {auction.status !== "draft" && auction.status !== "cancelled" && auction.visible_in_store !== false ? (
              <a href={storeUrl} target="_blank" rel="noopener" className={buttonClass("secondary")}>
                <ExternalLink aria-hidden className="size-4" />
                Ver na loja
              </a>
            ) : null}
            {auction.status === "scheduled" || auction.status === "live" || auction.status === "finished" ? (
              <Link href={`/painel/leiloes/${auction.id}/pregao`} className={buttonClass("primary")}>
                <Radio aria-hidden className="size-4" />
                {auction.status === "finished" ? "Ver resultado" : "Abrir pregão"}
              </Link>
            ) : null}
          </div>
        </div>
      </div>

      {query.agendado ? <Alert tone="success" title="Lote agendado! Ele já aparece na sua loja." /> : null}
      {query.capa === "erro" ? <Alert tone="error" title="Lote salvo, mas a capa não subiu. Escolha a imagem de novo e salve." /> : null}

      {editable ? (
        <AuctionForm
          key={`${auction.id}-${auction.status}`}
          auction={auction}
          defaults={defaults.ok && defaults.data.defaults ? defaults.data.defaults : (auction.settings as never)}
          hasPaymentCard={!billing.ok || billing.data.card !== null}
        />
      ) : (
        <section className="flex flex-col gap-3 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
          <h2 className="font-brand text-lg font-bold text-dl-ink">Itens</h2>
          <p className="text-sm text-dl-ink-muted">
            {auction.status === "cancelled" ? "Este lote foi cancelado." : "Depois de iniciado, o lote só muda pelos controles do pregão."}
          </p>
          {auction.status !== "cancelled" ? (
            <p className="text-sm text-dl-ink-muted">Taxa do leilão: {auction.settings.fee_payer === "bidder" ? "repassada ao vencedor" : "assumida por você"}.</p>
          ) : null}
          <ol className="flex flex-col divide-y divide-dl-line">
            {(auction.items ?? []).map((item) => (
              <li key={item.id} className="flex items-center gap-3 py-3">
                <span className="w-6 text-center font-semibold text-dl-ink-muted tabular">{item.position}</span>
                <span className="min-w-0 flex-1 truncate font-medium text-dl-ink">{item.title}</span>
                <span className="text-sm font-semibold text-dl-ink tabular">{item.current_price_cents ? formatCents(item.current_price_cents) : "—"}</span>
                <ItemStatusBadge status={item.status} />
              </li>
            ))}
          </ol>
        </section>
      )}

      {auction.status === "finished" || auction.status === "cancelled" ? <ClosedAuctionActions key={auction.id} auction={auction} /> : null}
    </div>
  );
}
