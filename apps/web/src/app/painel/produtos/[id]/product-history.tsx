"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ExternalLink, ImageOff, RotateCcw, Trophy, Zap } from "lucide-react";
import { AuctionStatusBadge, ItemStatusBadge } from "@/components/auction/status-badges";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { Modal } from "@/components/ui/dialog";
import { inputClass } from "@/components/ui/field";
import type { Product, ProductHistoryEntry } from "@/lib/api-types";
import { formatAuctionDate } from "@/lib/auction-time";
import { formatCents } from "@/lib/money";
import { panelRequest } from "@/lib/panel-client";

const dateTime = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
const clock = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", hour: "2-digit", minute: "2-digit", second: "2-digit" });

/**
 * Produto que está num lote ou já foi arrematado: não muda pelo cadastro, mas mostra tudo o que
 * aconteceu com ele e, se a venda não se concretizar, volta para o catálogo ("Retornar item para leilão").
 */
export function LockedProduct({ product, history }: { product: Product; history: ProductHistoryEntry[] }) {
  const current = history[0];
  const sold = product.status === "sold";
  const cover = product.photos.find((p) => p.status === "ready");

  return (
    <div className="flex flex-col gap-6">
      <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:flex-row sm:p-6">
        {cover?.card.webp ? (
          // eslint-disable-next-line @next/next/no-img-element -- foto já processada
          <img src={cover.card.webp} alt="" className="aspect-square w-full shrink-0 rounded-field object-cover sm:w-44" />
        ) : (
          <span className="flex aspect-square w-full shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted sm:w-44">
            <ImageOff aria-hidden className="size-8" />
          </span>
        )}
        <div className="flex min-w-0 flex-1 flex-col gap-3">
          {sold && current?.award ? (
            <>
              <p className="flex items-center gap-2 text-sm font-semibold text-state-closed">
                <Trophy aria-hidden className="size-4" />
                Arrematado em {dateTime.format(new Date(current.award.awarded_at))}
              </p>
              <dl className="grid gap-3 sm:grid-cols-3">
                <Fact label="Valor" value={formatCents(current.award.amount_cents)} big />
                <Fact label="Arrematante" value={`${current.award.bidder.name} (${current.award.bidder.nickname})`} />
                <Fact label="Situação" value={current.award.status_label} />
              </dl>
              <p className="flex flex-wrap gap-x-4 text-sm text-dl-ink-muted">
                <a href={`tel:+55${current.award.bidder.phone.replace(/\D/g, "")}`} className="tabular hover:text-dl-copper">
                  {current.award.bidder.phone}
                </a>
                <a href={`mailto:${current.award.bidder.email}`} className="break-all hover:text-dl-copper">
                  {current.award.bidder.email}
                </a>
              </p>
            </>
          ) : (
            <p className="text-sm font-semibold text-state-live">Este produto está num lote. Ele volta a ser editável quando sair do lote ou o leilão terminar sem venda.</p>
          )}
          <dl className="grid gap-3 border-t border-dl-line pt-3 text-sm sm:grid-cols-3">
            <Fact label="Lance inicial" value={formatCents(product.starting_price_cents)} />
            <Fact label="Incremento" value={formatCents(product.increment_cents)} />
            <Fact label="Categoria" value={product.category?.name ?? "Sem categoria"} />
          </dl>
          <div className="mt-auto flex flex-wrap gap-2">
            {current ? (
              <Link href={`/painel/leiloes/${current.auction.id}${current.auction.status === "live" ? "/pregao" : ""}`} className={buttonClass("secondary")}>
                <ExternalLink aria-hidden className="size-4" />
                Ver o lote
              </Link>
            ) : null}
            {sold && current?.award ? (
              <Link href={`/painel/arrematacoes?lote=${current.auction.id}&status=all`} className={buttonClass("secondary")}>
                <Trophy aria-hidden className="size-4" />
                Ver arrematação
              </Link>
            ) : null}
            {sold ? <ReturnToAuction productId={product.id} /> : null}
          </div>
        </div>
      </section>

      <ProductHistory history={history} />
    </div>
  );
}

function Fact({ label, value, big = false }: { label: string; value: string; big?: boolean }) {
  return (
    <div className="flex min-w-0 flex-col">
      <dt className="text-xs font-medium tracking-wide text-dl-ink-muted uppercase">{label}</dt>
      <dd className={`truncate text-dl-ink ${big ? "text-2xl font-bold tabular" : "font-medium"}`}>{value}</dd>
    </div>
  );
}

/** Volta o produto arrematado para o catálogo: a arrematação vira desistência (com o motivo) e ele pode entrar num lote novo. */
function ReturnToAuction({ productId }: { productId: string }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    setBusy(true);
    setError(null);
    const result = await panelRequest<Product>(`products/${productId}/return-to-catalog`, { method: "POST", body: { reason } });
    setBusy(false);
    if (!result.ok) return setError(result.message ?? "Não foi possível devolver o item.");
    setOpen(false);
    router.refresh();
    window.scrollTo({ top: 0, behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
  }

  return (
    <>
      <button type="button" className={buttonClass("primary")} onClick={() => setOpen(true)}>
        <RotateCcw aria-hidden className="size-4" />
        Retornar item para leilão
      </button>
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        icon={<RotateCcw className="size-5" />}
        title="Retornar item para leilão?"
        description="O produto volta a ficar disponível para entrar num lote novo. A arrematação atual vira “Desistência” e o motivo fica nas observações. O histórico de lances continua guardado."
      >
        <form
          className="flex flex-col gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            void submit();
          }}
        >
          {error ? <Alert tone="error" title={error} /> : null}
          <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink">
            Motivo (opcional)
            <input className={inputClass} value={reason} maxLength={300} onChange={(e) => setReason(e.target.value)} placeholder="Ex.: o arrematante desistiu" data-autofocus />
          </label>
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" className={buttonClass("secondary")} onClick={() => setOpen(false)}>
              Cancelar
            </button>
            <button type="submit" className={buttonClass("primary")} disabled={busy}>
              {busy ? "Devolvendo…" : "Retornar para leilão"}
            </button>
          </div>
        </form>
      </Modal>
    </>
  );
}

/** Cada lote em que o produto entrou, com o resultado e os lances do item (mais novo primeiro). */
export function ProductHistory({ history }: { history: ProductHistoryEntry[] }) {
  if (history.length === 0) return null;
  return (
    <section aria-labelledby="historico" className="flex flex-col gap-3">
      <h2 id="historico" className="font-brand text-lg font-bold text-dl-ink">
        Histórico em leilões
      </h2>
      <ol className="flex flex-col gap-3">
        {history.map((entry) => (
          <li key={entry.item.id} className="rounded-card border border-dl-line bg-dl-card">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-dl-line p-4">
              <div className="min-w-0">
                <Link href={`/painel/leiloes/${entry.auction.id}`} className="font-semibold text-dl-ink hover:text-dl-copper">
                  {entry.auction.name}
                </Link>
                <p className="text-sm text-dl-ink-muted tabular">
                  {formatAuctionDate(entry.auction.starts_at)} · item {entry.item.position}
                </p>
              </div>
              <span className="flex items-center gap-2">
                <AuctionStatusBadge status={entry.auction.status} />
                <ItemStatusBadge status={entry.item.status} />
              </span>
            </div>
            <div className="grid gap-4 p-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
              <dl className="flex flex-col gap-2 text-sm">
                <Row label="Lance inicial" value={formatCents(entry.item.starting_price_cents)} />
                <Row label="Incremento" value={formatCents(entry.item.increment_cents)} />
                <Row label="Lances" value={String(entry.item.bids_count)} />
                {entry.award ? (
                  <>
                    <Row label="Arrematado por" value={formatCents(entry.award.amount_cents)} strong />
                    <Row label="Arrematante" value={entry.award.bidder.name} />
                    <Row label="Situação" value={entry.award.status_label} />
                  </>
                ) : null}
                {entry.award?.notes ? <p className="mt-1 rounded-field bg-dl-page px-3 py-2 whitespace-pre-line text-dl-ink">{entry.award.notes}</p> : null}
              </dl>
              {entry.bids.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[28rem] text-left text-sm">
                    <caption className="sr-only">Lances do item no leilão {entry.auction.name}</caption>
                    <thead>
                      <tr className="border-b border-dl-line text-xs tracking-wide text-dl-ink-muted uppercase">
                        <th scope="col" className="py-2 pr-3 font-medium">Hora</th>
                        <th scope="col" className="py-2 pr-3 font-medium">Participante</th>
                        <th scope="col" className="py-2 pr-3 text-right font-medium">Valor</th>
                      </tr>
                    </thead>
                    <tbody>
                      {entry.bids.map((bid, index) => (
                        <tr key={bid.id} className={`border-b border-dl-line/60 last:border-0 ${bid.voided ? "text-dl-ink-muted line-through" : index === 0 ? "font-semibold text-dl-ink" : "text-dl-ink"}`}>
                          <td className="py-2 pr-3 tabular">{clock.format(new Date(bid.placed_at))}</td>
                          <td className="py-2 pr-3">
                            {bid.bidder.name} <span className="text-dl-ink-muted">({bid.bidder.nickname})</span>
                            {bid.type === "auto" ? <Zap aria-label="lance automático" className="ml-1 inline size-3.5 text-dl-copper" /> : null}
                            {bid.voided ? <span className="ml-1 text-xs no-underline">anulado{bid.void_reason ? `: ${bid.void_reason}` : ""}</span> : null}
                          </td>
                          <td className="py-2 pr-3 text-right tabular">{formatCents(bid.amount_cents)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-sm text-dl-ink-muted">Nenhum lance neste leilão.</p>
              )}
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}

function Row({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-dl-ink-muted">{label}</dt>
      <dd className={`text-right text-dl-ink tabular ${strong ? "font-bold" : ""}`}>{value}</dd>
    </div>
  );
}
