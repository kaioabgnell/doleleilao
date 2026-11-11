"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { Ban, ChevronRight, CirclePause, CirclePlay, ImageOff, Play, SkipForward, Square, Wifi, WifiOff } from "lucide-react";
import { channels } from "@doleleilao/realtime-contract";
import { Countdown } from "@/components/auction/countdown";
import { AuctionStatusBadge, ItemStatusBadge } from "@/components/auction/status-badges";
import { Alert } from "@/components/ui/alert";
import { dangerButton, Modal, useConfirm, type ConfirmOptions } from "@/components/ui/dialog";
import { buttonClass } from "@/components/ui/button-styles";
import { inputClass } from "@/components/ui/field";
import type { AuctionItem, AuctionLive } from "@/lib/api-types";
import { formatRelativeStart } from "@/lib/auction-time";
import { formatCents } from "@/lib/money";
import { panelRequest } from "@/lib/panel-client";
import { measureOffset, useChannel, useRealtimeClient } from "@/lib/use-realtime";
import { PanelChat } from "./panel-chat";

const LOG_LABELS: Record<string, string> = {
  auction_started: "Lote iniciado",
  item_opened: "Item aberto",
  item_closed: "Item encerrado pelo tempo",
  item_closed_manually: "Item encerrado pelo leiloeiro",
  item_skipped: "Item pulado",
  item_paused: "Pregão pausado",
  item_resumed: "Pregão retomado",
  bid_voided: "Lance anulado",
  auction_finished: "Lote encerrado",
};

const time = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", hour: "2-digit", minute: "2-digit", second: "2-digit" });

const card = "rounded-card border border-dl-line bg-dl-card";

/**
 * Painel do pregão ao vivo: o estado vem da API e é recarregado a cada evento do WebSocket.
 * Celular: item em pregão e controles, destaque e chat, fila de itens, lances e registro.
 * Computador: fila à esquerda, pregão no centro, destaque e chat à direita.
 */
export function LiveControl({ initial }: { initial: AuctionLive }) {
  const confirm = useConfirm();
  const [live, setLive] = useState(initial);
  const [offset, setOffset] = useState(() => Date.parse(initial.server_now) - Date.now());
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [voiding, setVoiding] = useState(false);
  const pending = useRef<ReturnType<typeof setTimeout> | null>(null);
  const auctionId = initial.auction.id;

  const refresh = useCallback(async () => {
    const sentAt = Date.now();
    const result = await panelRequest<AuctionLive>(`auctions/${auctionId}/live`);
    if (result.ok && result.data) {
      setOffset(measureOffset(result.data.server_now, sentAt, Date.now()));
      setLive(result.data);
    }
  }, [auctionId]);

  // Vários eventos seguidos (lance + automático) viram uma só leitura.
  const scheduleRefresh = useCallback(() => {
    if (pending.current) return;
    pending.current = setTimeout(() => {
      pending.current = null;
      void refresh();
    }, 250);
  }, [refresh]);

  const { client, state: connection } = useRealtimeClient("/api/painel/conteudo/broadcasting/auth");
  useChannel(client, channels.auction(auctionId), scheduleRefresh, scheduleRefresh);

  // Sem WebSocket, consulta a cada 3 s; com ele, sincroniza o relógio a cada 30 s.
  useEffect(() => {
    const timer = setInterval(() => void refresh(), connection === "connected" ? 30_000 : 3_000);
    return () => clearInterval(timer);
  }, [refresh, connection]);

  async function act(key: string, path: string, body?: object, ask?: ConfirmOptions) {
    if (ask && !(await confirm(ask))) return false;
    setBusy(key);
    setError(null);
    const result = await panelRequest<AuctionLive>(path, { method: "POST", body });
    setBusy(null);
    if (!result.ok || !result.data) {
      setError(result.message ?? "Não foi possível concluir.");
      return false;
    }
    setLive(result.data);
    return true;
  }

  const { auction, recent_bids: bids, log } = live;
  const items = auction.items ?? [];
  const running = items.find((i) => i.id === live.running_item_id) ?? null;
  const sold = items.filter((i) => i.status === "closed_sold");
  const lastClosed = [...items].filter((i) => i.closed_at).sort((a, b) => Date.parse(b.closed_at!) - Date.parse(a.closed_at!))[0];
  const nextQueued = items.find((i) => i.status === "queued");
  const gapEndsAt =
    !running && auction.status === "live" && lastClosed?.closed_at && nextQueued
      ? new Date(Date.parse(lastClosed.closed_at) + (lastClosed.status === "skipped" ? 0 : (auction.settings.gap_s ?? 0) * 1000)).toISOString()
      : null;
  const showChat = auction.status !== "draft" && auction.status !== "cancelled";

  return (
    <div className="flex flex-col gap-4">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">{auction.name}</h1>
            <AuctionStatusBadge status={auction.status} />
          </div>
          <p className="mt-1 text-sm text-dl-ink-muted tabular">
            {items.length} itens · {auction.participants_count ?? 0} marcaram &ldquo;Quero participar&rdquo;
          </p>
        </div>
        <span
          className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${connection === "connected" ? "bg-state-winning/10 text-state-winning" : "bg-dl-card text-dl-ink-muted ring-1 ring-dl-line"}`}
          role="status"
        >
          {connection === "connected" ? <Wifi aria-hidden className="size-3.5" /> : <WifiOff aria-hidden className="size-3.5" />}
          {connection === "connected" ? "Tempo real conectado" : "Reconectando…"}
        </span>
      </header>

      {error ? <Alert tone="error" title={error} /> : null}

      {/*
        Celular: pregão, destaque e chat, fila, lances e registro (o que se usa durante o leilão vem primeiro).
        Computador médio: pregão, lances e fila à esquerda; destaque e chat à direita.
        Computador largo: fila | pregão e lances | destaque e chat.
      */}
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_auto_1fr] lg:items-start xl:grid-cols-[16rem_minmax(0,1fr)_22rem] xl:grid-rows-[auto_1fr] 2xl:grid-cols-[17rem_minmax(0,1fr)_24rem]">
        {/* Centro, em cima: o pregão. */}
        <div className="order-1 flex min-w-0 flex-col gap-4 lg:order-none lg:col-start-1 lg:row-start-1 xl:col-start-2">
          {auction.status === "scheduled" ? (
            <section className={`flex flex-col items-start gap-3 p-4 sm:p-6 ${card}`}>
              <h2 className="font-brand text-lg font-bold text-dl-ink">Começa {formatRelativeStart(auction.starts_at)}</h2>
              <p className="text-dl-ink-muted">O pregão começa sozinho na hora marcada. Se quiser, comece antes.</p>
              <button
                type="button"
                className={buttonClass("primary")}
                disabled={!!busy}
                onClick={() =>
                  void act("start", `auctions/${auctionId}/start`, undefined, {
                    title: "Iniciar o lote agora?",
                    description: "O primeiro item abre em seguida e quem está no auditório já pode dar lances. O horário marcado deixa de valer.",
                    confirmLabel: "Iniciar agora",
                    icon: <Play className="size-5" />,
                  })
                }
              >
                <Play aria-hidden className="size-5" />
                {busy === "start" ? "Iniciando…" : "Iniciar agora"}
              </button>
            </section>
          ) : null}

          {auction.status === "live" && running ? (
            <CurrentItem
              item={running}
              total={items.length}
              offset={offset}
              busy={busy}
              onPause={() => void act("pause", `auctions/${auctionId}/pause`)}
              onResume={() => void act("resume", `auctions/${auctionId}/resume`)}
              onClose={() =>
                void act(
                  "close",
                  `items/${running.id}/close`,
                  undefined,
                  running.leader
                    ? {
                        title: `Encerrar e vender para ${running.leader.nickname}?`,
                        description: `O item é arrematado agora por ${formatCents(running.current_price_cents ?? 0)}, sem esperar o tempo acabar.`,
                        confirmLabel: "Encerrar e vender",
                        icon: <Square className="size-5" />,
                      }
                    : { title: "Encerrar o item sem lances?", description: "O item fecha sem venda e o próximo abre depois do intervalo.", confirmLabel: "Encerrar agora", icon: <Square className="size-5" /> },
                )
              }
              onSkip={() =>
                void act("skip", `items/${running.id}/skip`, undefined, {
                  title: "Pular este item?",
                  description: "Ele sai do pregão sem arrematação, mesmo que já tenha lances, e o produto volta a ficar disponível no catálogo.",
                  confirmLabel: "Pular item",
                  tone: "danger",
                })
              }
              onVoid={() => setVoiding(true)}
              leaderName={bids[0]?.bidder.name}
            />
          ) : null}

          {auction.status === "live" && !running ? (
            <section className={`flex flex-wrap items-center gap-3 p-4 sm:p-6 ${card}`}>
              <CirclePause aria-hidden className="size-6 text-dl-copper-icon" />
              <p className="font-medium text-dl-ink">
                {nextQueued ? (
                  <>
                    Intervalo. Próximo item, <strong>{nextQueued.title}</strong>, abre em{" "}
                    <Countdown endsAt={gapEndsAt} offsetMs={offset} urgentBelowMs={0} className="font-bold" />
                  </>
                ) : (
                  "Encerrando o lote…"
                )}
              </p>
            </section>
          ) : null}

          {auction.status === "finished" ? (
            <Alert tone="success" title="Lote encerrado">
              {sold.length} de {items.length} itens arrematados, somando{" "}
              <strong className="tabular">{formatCents(sold.reduce((sum, i) => sum + (i.current_price_cents ?? 0), 0))}</strong>.
            </Alert>
          ) : null}
        </div>

        {/* Centro, embaixo: lances do item e registro. */}
        <div className="order-4 flex min-w-0 flex-col gap-4 lg:order-none lg:col-start-1 lg:row-start-2 xl:col-start-2">
          {running ? (
            <section className={`p-4 ${card}`}>
              <div className="mb-2 flex items-baseline justify-between gap-2">
                <h2 className="font-brand text-base font-bold text-dl-ink">Lances do item</h2>
                <span className="text-xs text-dl-ink-muted tabular">{running.bids_count} no total</span>
              </div>
              {bids.length === 0 ? (
                <p className="text-sm text-dl-ink-muted">Nenhum lance ainda.</p>
              ) : (
                <ol className="flex flex-col" aria-live="polite">
                  {bids.map((bid, index) => (
                    <li key={bid.id} className={`flex items-baseline justify-between gap-3 border-b border-dl-line py-1.5 text-sm last:border-0 ${index === 0 ? "font-semibold text-dl-ink" : "text-dl-ink-muted"}`}>
                      <span className="min-w-0 flex-1 truncate">
                        <span className={index === 0 ? "text-dl-copper" : ""}>{bid.bidder.nickname}</span>
                        {bid.bidder.name && bid.bidder.name !== bid.bidder.nickname ? <span className="ml-1.5 text-xs font-normal">{bid.bidder.name}</span> : null}
                        {bid.type === "auto" ? <span className="ml-1.5 text-xs font-normal">automático</span> : null}
                      </span>
                      <span className="text-xs font-normal tabular">{time.format(new Date(bid.placed_at))}</span>
                      <span className="tabular">{formatCents(bid.amount_cents)}</span>
                    </li>
                  ))}
                </ol>
              )}
            </section>
          ) : null}

          <section className={`p-4 ${card}`}>
            <h2 className="mb-2 font-brand text-base font-bold text-dl-ink">Registro</h2>
            {log.length === 0 ? (
              <p className="text-sm text-dl-ink-muted">Nada ainda.</p>
            ) : (
              <ol className="flex max-h-80 flex-col gap-2 overflow-y-auto text-sm">
                {log.map((entry, index) => (
                  <li key={index} className="flex gap-2">
                    <span className="shrink-0 text-dl-ink-muted tabular">{time.format(new Date(entry.at))}</span>
                    <span className="text-dl-ink">
                      {LOG_LABELS[entry.action] ?? entry.action}
                      {entry.item_title ? `: ${entry.item_title}` : ""}
                      {entry.action === "bid_voided" && typeof entry.payload.reason === "string" ? ` (${entry.payload.reason})` : ""}
                      {entry.payload.reason === "realtime_offline" ? " (o tempo real caiu)" : ""}
                      {entry.actor === "system" ? <span className="text-dl-ink-muted"> · automático</span> : null}
                    </span>
                  </li>
                ))}
              </ol>
            )}
          </section>
        </div>

        {/* Direita: o que o leiloeiro fala e o que o público conversa. */}
        {showChat ? (
          <div className="order-2 flex min-w-0 flex-col gap-4 lg:order-none lg:col-start-2 lg:row-span-3 lg:row-start-1 xl:col-start-3 xl:row-span-2">
            <PanelChat
              client={client}
              auctionId={auctionId}
              onError={setError}
              context={{
                nextMinimum: running && running.status === "open" ? formatCents(running.next_minimum_cents) : null,
                nextItemTitle: nextQueued?.title ?? null,
                lastItem: !!running && !nextQueued,
              }}
            />
          </div>
        ) : null}

        {/* Esquerda: a fila do lote. */}
        <section className={`order-3 min-w-0 p-4 lg:order-none lg:col-start-1 lg:row-start-3 xl:sticky xl:top-20 xl:col-start-1 xl:row-span-2 xl:row-start-1 xl:self-start ${card}`}>
          <div className="mb-2 flex items-baseline justify-between gap-2">
            <h2 className="font-brand text-base font-bold text-dl-ink">Itens</h2>
            <span className="text-xs text-dl-ink-muted tabular">
              {sold.length} de {items.length} arrematados
            </span>
          </div>
          <ol className="flex flex-col divide-y divide-dl-line">
            {items.map((item) => {
              const current = item.id === running?.id;
              const row = (
                <>
                  <span className={`w-5 shrink-0 text-center text-sm tabular ${current ? "font-semibold text-state-live" : "text-dl-ink-muted"}`}>{item.position}</span>
                  <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className={`truncate ${current ? "font-semibold text-dl-ink" : "text-dl-ink"}`}>{item.title}</span>
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-dl-ink-muted">
                      <span className="tabular whitespace-nowrap">
                        {item.status === "closed_sold" && item.leader
                          ? `${formatCents(item.current_price_cents ?? 0)} · ${item.leader.nickname}`
                          : item.current_price_cents
                            ? formatCents(item.current_price_cents)
                            : `A partir de ${formatCents(item.starting_price_cents)}`}
                      </span>
                      <ItemStatusBadge status={item.status} />
                    </span>
                  </span>
                </>
              );
              // Item encerrado abre a página do produto: quem arrematou, contato, valor, data e lances.
              const closed = ["closed_sold", "closed_unsold", "skipped"].includes(item.status);
              return (
                <li key={item.id} className={current ? "-mx-2 rounded-field bg-state-live/5 px-2" : ""}>
                  {closed && item.product ? (
                    <Link
                      href={`/painel/produtos/${item.product.id}?pregao=${auction.id}`}
                      className="-mx-2 flex min-h-11 items-center gap-3 rounded-field px-2 py-2 transition-colors hover:bg-dl-page focus-visible:outline-2 focus-visible:outline-dl-copper"
                      aria-label={`${item.title}: ${item.status === "closed_sold" ? "ver quem arrematou" : "ver o histórico do produto"}`}
                    >
                      {row}
                      <ChevronRight aria-hidden className="size-4 shrink-0 text-dl-ink-muted" />
                    </Link>
                  ) : (
                    <div className="flex min-h-11 items-center gap-3 py-2" aria-current={current ? "true" : undefined}>
                      {row}
                    </div>
                  )}
                </li>
              );
            })}
          </ol>
        </section>
      </div>

      {voiding && running ? (
        <VoidDialog
          bid={bids[0] ? `${bids[0].bidder.name ?? bids[0].bidder.nickname} — ${formatCents(bids[0].amount_cents)}` : null}
          busy={busy === "void"}
          onCancel={() => setVoiding(false)}
          onConfirm={async (reason) => {
            if (await act("void", `items/${running.id}/void-last-bid`, { reason })) setVoiding(false);
          }}
        />
      ) : null}
    </div>
  );
}

/** O item em pregão: cronômetro e preço grandes, quem está ganhando e os controles do leiloeiro. */
function CurrentItem({
  item,
  total,
  offset,
  busy,
  leaderName,
  onPause,
  onResume,
  onClose,
  onSkip,
  onVoid,
}: {
  item: AuctionItem;
  total: number;
  offset: number;
  busy: string | null;
  leaderName?: string;
  onPause: () => void;
  onResume: () => void;
  onClose: () => void;
  onSkip: () => void;
  onVoid: () => void;
}) {
  const photo = item.product?.photos[0];
  const paused = item.status === "paused";

  return (
    <section className="flex flex-col gap-4 rounded-card border-2 border-state-live/40 bg-dl-card p-4 sm:p-5" aria-labelledby="current-item">
      <div className="flex items-start gap-3">
        {photo?.card.webp ? (
          // eslint-disable-next-line @next/next/no-img-element -- foto já processada
          <img src={photo.card.webp} alt="" className="size-16 shrink-0 rounded-field object-cover sm:size-20" />
        ) : (
          <span className="flex size-16 shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted sm:size-20">
            <ImageOff aria-hidden className="size-6" />
          </span>
        )}
        <div className="min-w-0 flex-1">
          <p className="text-sm text-dl-ink-muted tabular">
            Item {item.position} de {total}
          </p>
          <h2 id="current-item" className="font-brand text-xl leading-tight font-bold text-dl-ink sm:text-2xl">
            {item.title}
          </h2>
        </div>
        <ItemStatusBadge status={item.status} />
      </div>

      {/* Cronômetro e preço grandes; lado a lado quando cabem, um sobre o outro na coluna estreita. */}
      <div className="flex flex-wrap items-end gap-x-10 gap-y-4 rounded-field bg-dl-page p-4">
        <div className="flex min-w-44 flex-col">
          <span className="text-xs font-medium text-dl-ink-muted uppercase">{paused ? "Pausado em" : "Tempo"}</span>
          <Countdown endsAt={item.ends_at} pausedMs={paused ? item.paused_remaining_ms : null} offsetMs={offset} className="font-brand text-5xl leading-none font-bold text-dl-ink" />
          <span className="mt-1.5 text-sm text-dl-ink-muted">{paused ? "O tempo volta de onde parou." : "Lance no fim prorroga o item."}</span>
        </div>
        <div className="flex min-w-0 flex-col">
          <span className="text-xs font-medium text-dl-ink-muted uppercase">{item.current_price_cents ? "Lance atual" : "Lance inicial"}</span>
          <span className="font-brand text-3xl leading-none font-bold text-dl-ink tabular sm:text-4xl">{formatCents(item.current_price_cents ?? item.starting_price_cents)}</span>
          <span className="mt-1.5 text-sm text-dl-ink-muted">
            {item.leader ? (
              <>
                Ganhando: <strong className="text-dl-ink">{leaderName ?? item.leader.nickname}</strong>
              </>
            ) : (
              "Nenhum lance ainda"
            )}
          </span>
        </div>
        <p className="basis-full text-sm text-dl-ink-muted tabular">
          Próximo lance mínimo <strong className="text-dl-ink">{formatCents(item.next_minimum_cents)}</strong> · {item.bids_count} {item.bids_count === 1 ? "lance" : "lances"}
        </p>
      </div>

      <div className="grid grid-cols-2 gap-2">
        {paused ? (
          <button type="button" className={buttonClass("primary")} onClick={onResume} disabled={!!busy}>
            <CirclePlay aria-hidden className="size-5" />
            Retomar
          </button>
        ) : (
          <button type="button" className={buttonClass("secondary")} onClick={onPause} disabled={!!busy}>
            <CirclePause aria-hidden className="size-5" />
            Pausar
          </button>
        )}
        <button type="button" className={buttonClass("secondary")} onClick={onSkip} disabled={!!busy}>
          <SkipForward aria-hidden className="size-4" />
          Pular
        </button>
        <button type="button" className={buttonClass("ghost", "col-span-2 text-state-outbid")} onClick={onVoid} disabled={!!busy || item.bids_count === 0}>
          <Ban aria-hidden className="size-4" />
          Anular último lance
        </button>
        <button type="button" className={buttonClass(item.leader ? "primary" : "secondary", "col-span-2 text-lg")} onClick={onClose} disabled={!!busy}>
          <Square aria-hidden className="size-4" />
          {item.leader ? "Encerrar e vender" : "Encerrar agora"}
        </button>
      </div>
    </section>
  );
}

function VoidDialog({ bid, busy, onCancel, onConfirm }: { bid: string | null; busy: boolean; onCancel: () => void; onConfirm: (reason: string) => void }) {
  const [reason, setReason] = useState("");
  return (
    <Modal
      open
      onClose={onCancel}
      tone="danger"
      icon={<Ban className="size-5" />}
      title="Anular o último lance"
      description="O preço volta ao lance anterior e o motivo fica no registro. O lance automático de quem teve o lance anulado é desligado."
    >
      <form
        id="void-form"
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          onConfirm(reason);
        }}
      >
        {bid ? <p className="rounded-field bg-dl-page px-3 py-2 font-semibold text-dl-ink tabular">{bid}</p> : null}
        <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink">
          Motivo
          <input className={inputClass} value={reason} onChange={(e) => setReason(e.target.value)} minLength={3} maxLength={300} required data-autofocus />
        </label>
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" className={buttonClass("secondary")} onClick={onCancel}>
            Voltar
          </button>
          <button type="submit" className={dangerButton} disabled={busy || reason.trim().length < 3}>
            {busy ? "Anulando…" : "Anular lance"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
