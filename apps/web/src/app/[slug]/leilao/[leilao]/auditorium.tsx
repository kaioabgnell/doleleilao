"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useReducer, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import { BellRing, Check, CirclePause, Gavel, ImageOff, Lock, MailCheck, MessageCircle, Trophy, Volume2, VolumeX, Zap } from "lucide-react";
import { channels, type BidderChannelEvents } from "@doleleilao/realtime-contract";
import { Countdown } from "@/components/auction/countdown";
import { ItemStatusBadge, LiveBadge } from "@/components/auction/status-badges";
import { HighlightFeed } from "@/components/chat/highlight-feed";
import { storeButton, storeButtonOutline, storeInput } from "@/components/store/store-ui";
import { YouTubeFacade } from "@/components/store/youtube-facade";
import type { AuctionItem, AuctionState, BidderAuctionSituation, PublicAuction } from "@/lib/api-types";
import { formatAuctionDate, formatRelativeStart } from "@/lib/auction-time";
import { fireConfetti, isMuted, playPlim, setMuted, unlockAudio } from "@/lib/celebration";
import { formatCents, maskMoney, parseMoney } from "@/lib/money";
import { measureOffset, useChannel, useRealtimeClient } from "@/lib/use-realtime";
import { ChatPanel, MobileChatDock, useAuditoriumChat } from "./auction-chat";
import { CALL_LEAD_MS, CALL_RESULT_AT_MS, ClosingCall, type Call } from "./closing-call";
import { initialState, reducer, runningItem } from "./auditorium-state";
import { ItemGallery } from "./item-gallery";

type Toast = { tone: "info" | "error" | "success"; text: string } | null;

const clock = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", hour: "2-digit", minute: "2-digit", second: "2-digit" });

const subscribeStorage = (onChange: () => void) => {
  window.addEventListener("storage", onChange);
  return () => window.removeEventListener("storage", onChange);
};

/** "Já entrou no auditório deste leilão" fica guardado no navegador: atualizar a página não pede de novo. */
const enteredKey = (auctionId: string, bidderId: string) => `doleleilao:auditorio:${auctionId}:${bidderId}`;

function readFlag(key: string | null): boolean {
  if (!key) return false;
  try {
    return localStorage.getItem(key) === "1";
  } catch {
    return false;
  }
}

/** Quanto falta (no relógio do servidor) para um horário. */
function msUntil(iso: string, offsetMs: number): number {
  return Date.parse(iso) - (Date.now() + offsetMs);
}

function newKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/** Auditório do leilão (RF-F01 a RF-F04, RF-F09 a RF-F11). O servidor é a fonte da verdade. */
export function Auditorium({
  slug,
  initial,
  situation: initialSituation,
  bidderEmail,
  whatsapp,
}: {
  slug: string;
  initial: PublicAuction;
  situation: BidderAuctionSituation | null;
  bidderEmail: string | null;
  /** WhatsApp da loja (`https://wa.me/55…`), para o ganhador falar com o leiloeiro. */
  whatsapp: string | null;
}) {
  const auction = initial.auction;
  const base = `/${slug}/api/leilao`;
  const here = `/${slug}/leilao/${auction.slug}`;
  const [state, dispatch] = useReducer(reducer, initial, initialState);
  const [situation, setSituation] = useState(initialSituation);
  const [offset, setOffset] = useState(() => Date.parse(initial.server_now) - Date.now());
  const storedKey = initialSituation ? enteredKey(auction.id, initialSituation.bidder_id) : null;
  const enteredBefore = useSyncExternalStore(subscribeStorage, () => readFlag(storedKey), () => false);
  const [enteredNow, setEnteredNow] = useState(false);
  const entered = enteredNow || enteredBefore;
  const storedMuted = useSyncExternalStore(subscribeStorage, isMuted, () => false);
  const [mutedChoice, setMutedState] = useState<boolean | null>(null);
  const muted = mutedChoice ?? storedMuted;
  const [toast, setToast] = useState<Toast>(null);
  const [won, setWon] = useState<{ itemId: string; title: string; amount: number } | null>(null);
  const [myItems, setMyItems] = useState<Set<string>>(() => new Set(Object.keys(initialSituation?.auto_bids ?? {})));
  const rootRef = useRef<HTMLDivElement>(null);
  const celebrated = useRef(new Set<string>());
  const me = situation?.bidder_id ?? null;

  const { client, state: connection } = useRealtimeClient(initialSituation ? `${base}/canal` : null);
  const chat = useAuditoriumChat(client, base, auction.slug);
  const { setPage: setChatPage, refresh: refreshChat } = chat;

  const sync = useCallback(async () => {
    const sentAt = Date.now();
    const response = await fetch(`${base}/estado/${auction.slug}`, { cache: "no-store" }).catch(() => null);
    if (!response?.ok) return;
    const data = (await response.json()) as AuctionState;
    setOffset(measureOffset(data.server_now, sentAt, Date.now()));
    dispatch({ type: "sync", state: data });
  }, [base, auction.slug]);

  // "Dole 1, Dole 2, Dole 3, Vencedor": uma vez por item, quando o tempo acaba ou o leiloeiro encerra.
  const [call, setCall] = useState<Call | null>(null);
  const callStarts = useRef(new Map<string, number>());
  const startCall = useCallback((itemId: string): number => {
    const known = callStarts.current.get(itemId);
    if (known !== undefined) return known;
    const startedAt = Date.now();
    callStarts.current.set(itemId, startedAt);
    setCall({ itemId, startedAt });
    return startedAt;
  }, []);
  const cancelCall = useCallback((itemId: string) => {
    if (!callStarts.current.delete(itemId)) return;
    setCall((c) => (c?.itemId === itemId ? null : c));
  }, []);
  const endCall = useCallback(() => setCall(null), []);

  const celebrate = useCallback(
    (itemId: string, title: string, amount: number) => {
      if (celebrated.current.has(itemId)) return;
      celebrated.current.add(itemId);
      // A festa entra junto com o "Vencedor", depois das três chamadas.
      const delay = Math.max(0, startCall(itemId) + CALL_RESULT_AT_MS - Date.now());
      setTimeout(() => {
        setWon({ itemId, title, amount });
        const styles = rootRef.current ? getComputedStyle(rootRef.current) : null;
        const color = (name: string, fallback: string) => styles?.getPropertyValue(name).trim() || fallback;
        fireConfetti([color("--store-primary", "#8A5A38"), color("--store-accent", "#B07A4F"), color("--state-winning", "#1F7A4D"), "#F2C94C"]);
        playPlim();
      }, delay);
    },
    [startCall],
  );

  // Último estado aplicado, para decidir fora do render se é preciso buscar tudo de novo.
  const stateRef = useRef(state);
  const offsetRef = useRef(offset);
  useEffect(() => {
    stateRef.current = state;
    offsetRef.current = offset;
  });

  const onEvent = useCallback(
    (name: string, data: unknown) => {
      dispatch({ type: "event", name, data });
      // Salto de sequência, item desconhecido ou lance anulado: busca o estado inteiro (contrato do tempo real).
      const seq = (data as { item_id?: string; sequence?: number }) ?? {};
      if (seq.item_id && typeof seq.sequence === "number") {
        const known = stateRef.current.items.find((i) => i.id === seq.item_id);
        if (!known || seq.sequence > known.sequence + 1 || name === "bid.voided") void sync();
      }
      // Garantia: se o canal privado falhar, o evento público também revela o vencedor.
      const e = data as { item_id?: string; result?: string; winner?: { id: string } | null; amount_cents?: number | null; ends_at?: string | null };
      if (name === "item.closed" && e.item_id && (e.result === "closed_sold" || e.result === "closed_unsold")) startCall(e.item_id);
      // Lance no último instante prorrogou o item: as chamadas param.
      if (name === "bid.accepted" && e.item_id && e.ends_at && msUntil(e.ends_at, offsetRef.current) > 500) cancelCall(e.item_id);
      if (name === "item.closed" && me && e.result === "closed_sold" && e.winner?.id === me && e.item_id) {
        const item = state.items.find((i) => i.id === e.item_id);
        celebrate(e.item_id, item?.title ?? "", e.amount_cents ?? 0);
      }
    },
    [me, state.items, celebrate, sync, startCall, cancelCall],
  );

  const onPrivate = useCallback(
    (name: string, data: unknown) => {
      if (name === "bidder.won") {
        const e = data as BidderChannelEvents["bidder.won"];
        celebrate(e.item_id, e.title, e.amount_cents);
      } else if (name === "bidder.outbid") {
        const e = data as BidderChannelEvents["bidder.outbid"];
        setMyItems((s) => new Set(s).add(e.item_id));
        if (e.item_id !== state.currentItemId) setToast({ tone: "error", text: `Você foi superado: ${formatCents(e.new_price_cents)}.` });
      } else if (name === "bidder.muted") {
        const e = data as BidderChannelEvents["bidder.muted"];
        if (e.auction_id !== auction.id) return;
        const message = "O leiloeiro silenciou você neste leilão.";
        setChatPage((p) => (p?.access ? { ...p, access: { ...p.access, can_write: false, reason: "muted", message, muted_until: e.until } } : p));
        setToast({ tone: "error", text: e.until ? `${message} Até ${clock.format(new Date(e.until)).slice(0, 5)}.` : message });
      } else if (name === "bidder.unmuted") {
        void refreshChat();
        setToast({ tone: "success", text: "Você pode escrever no chat de novo." });
      } else if (name === "bidder.banned") {
        setSituation((s) => (s ? { ...s, banned: true } : s));
        setChatPage((p) => (p?.access ? { ...p, access: { ...p.access, can_read: false, can_write: false, reason: "banned", message: "Sua conta está bloqueada nesta loja." } } : p));
        setToast({ tone: "error", text: "Sua conta foi bloqueada nesta loja pelo leiloeiro." });
      } else if (name === "bidder.autobid_exhausted") {
        const e = data as BidderChannelEvents["bidder.autobid_exhausted"];
        setSituation((s) => (s ? { ...s, auto_bids: Object.fromEntries(Object.entries(s.auto_bids).filter(([k]) => k !== e.item_id)) } : s));
        setToast({ tone: "error", text: `Seu lance automático de até ${formatCents(e.max_amount_cents)} foi superado.` });
      }
    },
    [celebrate, state.currentItemId, auction.id, setChatPage, refreshChat],
  );

  useChannel(client, channels.auction(auction.id), onEvent, () => void sync());
  useChannel(client, initialSituation ? channels.bidder(initialSituation.bidder_id) : null, onPrivate);

  // Relógio sincronizado a cada 30 s (spec 04); sem WebSocket, consulta a cada 5 s.
  const live = state.status === "live";
  useEffect(() => {
    if (state.status === "finished") return;
    const timer = setInterval(() => void sync(), live && connection !== "connected" ? 5_000 : 30_000);
    return () => clearInterval(timer);
  }, [sync, live, connection, state.status]);

  // Quem já tinha entrado não clica de novo; o som é liberado no primeiro toque ou tecla (regra dos navegadores).
  useEffect(() => {
    if (!enteredBefore) return;
    const unlock = () => unlockAudio();
    document.addEventListener("pointerdown", unlock, { once: true });
    document.addEventListener("keydown", unlock, { once: true });
    return () => {
      document.removeEventListener("pointerdown", unlock);
      document.removeEventListener("keydown", unlock);
    };
  }, [enteredBefore]);

  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(null), 5000);
    return () => clearTimeout(timer);
  }, [toast]);

  const current = state.items.find((i) => i.id === state.currentItemId) ?? runningItem(state.items) ?? null;
  const offline = live && connection !== "connected";

  const currentId = current?.id ?? null;
  const currentOpen = current?.status === "open";
  const currentEndsAt = current?.ends_at ?? null;
  useEffect(() => {
    if (!currentId || !currentOpen || !currentEndsAt) return;
    const wait = msUntil(currentEndsAt, offset);
    if (wait < -2000) return; // já tinha acabado quando a página abriu
    // As chamadas começam 3 s antes do fim: quem quiser ainda dá o último lance durante o "Dole".
    const timer = setTimeout(() => startCall(currentId), Math.max(0, wait - CALL_LEAD_MS));
    return () => clearTimeout(timer);
  }, [currentId, currentOpen, currentEndsAt, offset, startCall]);
  const meId = situation?.bidder_id ?? null;
  const currentClosed = !!current && ["closed_sold", "closed_unsold", "skipped"].includes(current.status);
  // No celular, o chat fica na barra do lance quando ela está fixa embaixo; senão, aparece no meio da página.
  const docked = live && !!current && !currentClosed && (!situation || (situation.eligible && !situation.banned));
  const chatAside = <ChatPanel chat={chat} slug={slug} here={here} meId={meId} className="hidden h-[26rem] lg:flex xl:h-auto xl:min-h-0 xl:flex-1" />;
  const chatInline = <ChatPanel chat={chat} slug={slug} here={here} meId={meId} className="h-[min(70dvh,30rem)]" />;
  // O que o leiloeiro fala fica separado da conversa: lista completa no computador, última fala no celular.
  const highlightAside = <HighlightFeed tone="store" messages={chat.messages} className="hidden shrink-0 lg:flex" />;
  const extendWindow = auction.settings.extend_window_s;
  const extendBy = auction.settings.extend_by_s;
  const extend = extendWindow && extendBy ? { window: extendWindow, by: extendBy } : null;
  const highlightCompact = <HighlightFeed tone="store" messages={chat.messages} compact className="lg:hidden" />;
  const canMute = live && entered && !!situation?.eligible && !situation.banned;
  // Altura da barra fixa de lance no celular: o conteúdo termina acima dela.
  const onBarHeight = useCallback((height: number) => rootRef.current?.style.setProperty("--bid-bar-h", `${height}px`), []);

  return (
    <div ref={rootRef} className="mx-auto flex w-full max-w-[80rem] flex-col gap-6 px-4 py-6 sm:px-6 pb-[calc(var(--bid-bar-h,0px)+1.5rem)] sm:py-8 lg:pb-8">
      <header className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2">
          {live && !offline ? <LiveBadge /> : null}
          {offline ? (
            <span role="status" className="inline-flex items-center gap-1.5 rounded-full bg-state-closed px-2.5 py-0.5 text-xs font-bold tracking-wide text-white uppercase">
              Reconectando…
            </span>
          ) : null}
          {state.status === "scheduled" ? <span className="rounded-full bg-state-winning/10 px-2.5 py-0.5 text-xs font-semibold text-state-winning">Em breve</span> : null}
          {state.status === "finished" ? <span className="rounded-full bg-state-closed/10 px-2.5 py-0.5 text-xs font-semibold text-state-closed">Encerrado</span> : null}
          <span className="text-sm text-store-text-muted tabular">{formatAuctionDate(auction.starts_at)}</span>
          {canMute ? (
            <button
              type="button"
              className="-my-2 ml-auto flex size-11 items-center justify-center rounded-field text-store-text-muted transition-colors hover:text-store-text focus-visible:outline-2 focus-visible:outline-store-primary"
              onClick={() => {
                setMuted(!muted);
                setMutedState(!muted);
              }}
              aria-label={muted ? "Ligar o som do leilão" : "Silenciar o som do leilão"}
              aria-pressed={muted}
            >
              {muted ? <VolumeX aria-hidden className="size-5" /> : <Volume2 aria-hidden className="size-5" />}
            </button>
          ) : null}
        </div>
        <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">{auction.name}</h1>
        {offline ? (
          <p className="text-sm text-store-text-muted">
            Sem conexão em tempo real. Última atualização às{" "}
            <span className="tabular" suppressHydrationWarning>
              {clock.format(new Date(state.updatedAt))}
            </span>
            .
          </p>
        ) : null}
      </header>

      {/* Faixa do topo: as chamadas de fechamento e os avisos, sem cobrir a tela nem o botão de lance. */}
      {call || toast ? (
        <div className="pointer-events-none fixed inset-x-0 top-0 z-50 flex flex-col items-center gap-2 p-3 sm:p-4">
          {call ? <ClosingCall key={call.itemId} call={call} item={state.items.find((i) => i.id === call.itemId)} me={me} onDone={endCall} /> : null}
          {toast ? (
            <div
              role={toast.tone === "error" ? "alert" : "status"}
              className={`pointer-events-auto w-full max-w-md rounded-card px-4 py-3 text-sm font-semibold shadow-lg motion-safe:animate-toast-in ${toast.tone === "error" ? "bg-state-outbid text-white" : toast.tone === "success" ? "bg-state-winning text-white" : "bg-store-surface text-store-text ring-1 ring-store-border"}`}
            >
              {toast.text}
            </div>
          ) : null}
        </div>
      ) : null}

      {state.status === "scheduled" ? (
        <BeforeStart
          slug={slug}
          here={here}
          base={base}
          auction={auction}
          situation={situation}
          setSituation={setSituation}
          bidderEmail={bidderEmail}
          offset={offset}
          chat={
            <>
              {highlightAside}
              {chatAside}
            </>
          }
        />
      ) : null}

      {live && current ? (
        <LiveItem
          item={current}
          total={state.items.length}
          offset={offset}
          me={me}
          wasMine={myItems.has(current.id)}
          recentBids={state.recentBids}
          chat={chatAside}
          highlight={highlightAside}
          highlightCompact={highlightCompact}
          extend={extend}
          bidPanel={
            <BidPanel
              slug={slug}
              here={here}
              base={base}
              item={current}
              situation={situation}
              setSituation={setSituation}
              bidderEmail={bidderEmail}
              entered={entered}
              onEnter={() => {
                unlockAudio();
                setEnteredNow(true);
                try {
                  if (storedKey) localStorage.setItem(storedKey, "1");
                } catch {
                  // navegador sem armazenamento: vale só nesta visita
                }
              }}
              disabled={offline}
              onBid={(itemId, patch) => {
                setMyItems((s) => new Set(s).add(itemId));
                dispatch({ type: "local", itemId, patch });
              }}
              onToast={setToast}
              onBarHeight={onBarHeight}
              dock={<MobileChatDock chat={chat} slug={slug} here={here} meId={meId} />}
            />
          }
        />
      ) : null}

      {live && !current ? (
        <div className="flex items-center gap-3 rounded-card border border-store-border bg-store-surface p-4">
          <CirclePause aria-hidden className="size-6 shrink-0 text-store-link" />
          <p className="font-medium">Intervalo entre itens. O próximo abre em instantes.</p>
        </div>
      ) : null}

      {live ? <NextItems items={state.items} currentId={current?.id ?? null} /> : null}

      {/* Chat no meio da página: celular sem a barra de lance fixa, e intervalo/fim no computador. */}
      {!docked && (state.status === "scheduled" || (live && current)) ? (
        <div className="flex flex-col gap-4 lg:hidden">
          {state.status === "scheduled" ? <HighlightFeed tone="store" messages={chat.messages} /> : null}
          {chatInline}
        </div>
      ) : null}
      {/* Fim do leilão: primeiro o resultado, depois a conversa (que fica 12 h na loja). */}
      {state.status === "finished" ? (
        <ItemList items={state.items} currentId={null} me={me} finished whatsapp={whatsapp} auctionName={auction.name} nickname={situation?.nickname ?? null} slug={slug} />
      ) : null}
      {(live && !current) || state.status === "finished" ? (
        <div className="flex flex-col gap-4">
          <HighlightFeed tone="store" messages={chat.messages} />
          {chatInline}
        </div>
      ) : null}

      {state.status === "live" ? (
        <ItemList items={state.items} currentId={current?.id ?? null} me={me} finished={false} whatsapp={whatsapp} auctionName={auction.name} nickname={situation?.nickname ?? null} slug={slug} />
      ) : null}

      {won ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="won-title">
          <div className="flex w-full max-w-sm flex-col items-center gap-3 rounded-card bg-store-surface p-6 text-center text-store-text shadow-2xl">
            <span className="flex size-16 items-center justify-center rounded-full bg-state-winning/15 text-state-winning">
              <Trophy aria-hidden className="size-8" />
            </span>
            <h2 id="won-title" className="font-store-heading text-2xl font-bold">
              Você é o ganhador!
            </h2>
            <p className="text-store-text-muted">
              Você arrematou <strong className="text-store-text">{won.title}</strong> por <strong className="text-store-text tabular">{formatCents(won.amount)}</strong>.{" "}
              {whatsapp ? "Combine o pagamento e a entrega direto com o leiloeiro." : "O leiloeiro vai entrar em contato."}
            </p>
            {whatsapp ? (
              <WhatsAppButton
                href={winnerWhatsApp(whatsapp, { title: won.title, amount: won.amount, auctionName: auction.name, nickname: situation?.nickname ?? null })!}
                className="mt-2 w-full px-3"
              />
            ) : null}
            <button type="button" className={`${whatsapp ? storeButtonOutline : storeButton} w-full ${whatsapp ? "" : "mt-2"}`} onClick={() => setWon(null)} autoFocus>
              Continuar no leilão
            </button>
            <Link href={`/${slug}/minha-conta/arrematacoes`} className="text-sm font-semibold text-store-link underline-offset-4 hover:underline">
              Ver minhas arrematações
            </Link>
          </div>
        </div>
      ) : null}
    </div>
  );
}

/* ---------- Antes de começar ---------- */

function BeforeStart({
  slug,
  here,
  base,
  auction,
  situation,
  setSituation,
  bidderEmail,
  offset,
  chat,
}: {
  chat: React.ReactNode;
  slug: string;
  here: string;
  base: string;
  auction: PublicAuction["auction"];
  situation: BidderAuctionSituation | null;
  setSituation: (s: BidderAuctionSituation) => void;
  bidderEmail: string | null;
  offset: number;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [soon] = useState(() => Date.parse(auction.starts_at) - Date.now() < 86_400_000);

  async function toggle() {
    if (!situation) return;
    setBusy(true);
    setError(null);
    const response = await fetch(`${base}/participar/${auction.slug}`, { method: situation.participating ? "DELETE" : "PUT" }).catch(() => null);
    setBusy(false);
    const body = (await response?.json().catch(() => null)) as (BidderAuctionSituation & { detail?: string }) | null;
    if (response?.ok && body) setSituation(body);
    else setError(body?.detail ?? "Não foi possível salvar. Tente de novo.");
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <div className="flex flex-col gap-6">
        {auction.description ? <p className="whitespace-pre-line text-store-text-muted">{auction.description}</p> : null}
        <section className="flex flex-col gap-3">
          <h2 className="font-store-heading text-xl font-bold">Itens deste leilão</h2>
          <ul className="grid gap-3 sm:grid-cols-2">
            {(auction.items ?? []).map((item) => (
              <li key={item.id} className="flex gap-3 rounded-card border border-store-border bg-store-surface p-3">
                <ItemThumb item={item} />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                  <span className="text-xs text-store-text-muted tabular">Item {item.position}</span>
                  <span className="line-clamp-2 font-semibold">{item.title}</span>
                  <span className="mt-auto text-sm text-store-text-muted">
                    Lance inicial <strong className="text-store-text tabular">{formatCents(item.starting_price_cents)}</strong>
                  </span>
                  {item.product ? (
                    <Link href={`/${slug}/produto/${item.product.slug}`} className="text-sm font-medium text-store-link underline-offset-4 hover:underline">
                      Ver detalhes
                    </Link>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <aside className="flex flex-col gap-4 lg:sticky lg:top-20 lg:self-start">
        <div className="flex flex-col gap-3 rounded-card border border-store-border bg-store-surface p-5">
          <p className="text-sm font-medium text-store-text-muted">Começa {formatRelativeStart(auction.starts_at)}</p>
          {soon ? (
            <p className="font-store-heading text-4xl font-bold">
              <Countdown endsAt={auction.starts_at} offsetMs={offset} urgentBelowMs={0} />
            </p>
          ) : null}

          {!situation ? (
            <>
              <p className="text-sm text-store-text-muted">Entre na sua conta para marcar presença e receber o aviso na véspera e no dia.</p>
              <Link href={`/${slug}/entrar?voltar=${encodeURIComponent(here)}`} className={storeButton}>
                Entrar para participar
              </Link>
              <Link href={`/${slug}/cadastro?voltar=${encodeURIComponent(here)}`} className={storeButtonOutline}>
                Criar minha conta
              </Link>
            </>
          ) : (
            <>
              <button type="button" className={situation.participating ? storeButtonOutline : storeButton} onClick={() => void toggle()} disabled={busy} aria-pressed={situation.participating}>
                {situation.participating ? <Check aria-hidden className="size-5 text-state-winning" /> : <BellRing aria-hidden className="size-5" />}
                {situation.participating ? "Você vai participar" : "Quero participar"}
              </button>
              {situation.participating ? (
                <p className="text-sm text-store-text-muted">Vamos avisar na véspera e no dia. Toque de novo para desmarcar.</p>
              ) : null}
              {!situation.eligible ? <CompleteProfile slug={slug} situation={situation} email={bidderEmail} /> : null}
              {error ? <p role="alert" className="text-sm font-medium text-state-outbid">{error}</p> : null}
            </>
          )}
        </div>
        {chat}
      </aside>
    </div>
  );
}

/* ---------- Ao vivo ---------- */

function LiveItem({
  item,
  total,
  offset,
  me,
  wasMine,
  recentBids,
  bidPanel,
  chat,
  highlight,
  highlightCompact,
  extend,
}: {
  chat: ReactNode;
  highlight: ReactNode;
  highlightCompact: ReactNode;
  item: AuctionItem;
  total: number;
  offset: number;
  me: string | null;
  wasMine: boolean;
  recentBids: PublicAuction["recent_bids"];
  bidPanel: ReactNode;
  /** Prorrogação: lance nos últimos `window` segundos leva o tempo para `by` segundos. */
  extend: { window: number; by: number } | null;
}) {
  const leading = !!me && item.leader?.id === me;
  const outbid = !!me && !leading && wasMine && item.bids_count > 0;
  const paused = item.status === "paused";
  const closed = item.status === "closed_sold" || item.status === "closed_unsold" || item.status === "skipped";
  const product = item.product;
  const label = "text-xs font-semibold tracking-wide text-store-text-muted uppercase";

  return (
    /*
     * Celular: item e fotos, pregão (a barra de lance fica fixa embaixo), última fala do leiloeiro,
     * detalhes. Computador médio: item | pregão, e destaque e chat embaixo. Computador largo:
     * item | pregão | destaque e chat, como um palco no meio.
     */
    <div className="grid gap-4 lg:grid-cols-2 lg:items-start xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)_22rem] xl:grid-rows-[auto_1fr] xl:gap-5">
      {/* O item: título e fotos. */}
      <section className="flex min-w-0 flex-col gap-3 rounded-card border border-store-border bg-store-surface p-3 sm:p-4 lg:col-start-1 lg:row-start-1" aria-labelledby="live-item-title">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-sm text-store-text-muted tabular">
              Item {item.position} de {total}
            </p>
            <h2 id="live-item-title" className="font-store-heading text-xl leading-tight font-bold text-balance sm:text-2xl">
              {item.title}
            </h2>
          </div>
          <ItemStatusBadge status={item.status} />
        </div>
        {product ? <ItemGallery key={item.id} photos={product.photos} title={item.title} /> : null}
      </section>

      {/* O pregão: cronômetro, preço, situação, botão de lance e últimos lances. */}
      <section
        className={`flex min-w-0 flex-col gap-4 rounded-card border-2 bg-store-surface p-4 sm:p-5 lg:col-start-2 lg:row-span-2 lg:row-start-1 xl:p-6 ${leading ? "border-state-winning" : outbid ? "border-state-outbid" : "border-store-border"}`}
        aria-label="Pregão do item"
      >
        <div className="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-x-4 gap-y-5 lg:grid-cols-1 lg:items-center lg:text-center" aria-live="polite">
          <div className="flex min-w-0 flex-col lg:order-2">
            <p className={label}>{closed ? "Resultado" : item.bids_count > 0 ? "Lance atual" : "Lance inicial"}</p>
            <p className="text-3xl leading-tight font-bold tabular sm:text-4xl xl:text-5xl">{formatCents(item.current_price_cents ?? item.starting_price_cents)}</p>
            <p className="mt-1 text-sm text-store-text-muted">
              {item.leader ? (
                <>
                  {closed && item.status === "closed_sold" ? "Arrematado por " : "por "}
                  <strong className="text-store-text">{leading ? "você" : item.leader.nickname}</strong>
                </>
              ) : closed ? (
                "Encerrado sem lances"
              ) : (
                "Seja o primeiro a dar lance"
              )}
              {" · "}
              <span className="tabular">
                {item.bids_count} {item.bids_count === 1 ? "lance" : "lances"}
              </span>
            </p>
          </div>
          <div className="flex flex-col items-end lg:order-1 lg:items-center">
            <p className={label}>{paused ? "Pausado" : closed ? "Encerrado" : "Tempo"}</p>
            {closed ? (
              <p className="font-store-heading text-3xl leading-tight font-bold tabular sm:text-4xl lg:text-7xl">00:00</p>
            ) : (
              <Countdown
                endsAt={item.ends_at}
                pausedMs={paused ? item.paused_remaining_ms : null}
                offsetMs={offset}
                className="font-store-heading text-3xl leading-tight font-bold sm:text-4xl lg:text-7xl lg:leading-none"
              />
            )}
            {extend && !closed ? (
              <span className="mt-2 hidden rounded-full border border-store-border px-2.5 py-0.5 text-xs font-medium text-store-text-muted lg:inline-block">
                Lance nos últimos {extend.window} s prorroga o item
              </span>
            ) : null}
          </div>
        </div>

        {leading ? (
          <p className="rounded-field bg-state-winning px-3 py-2 text-center font-bold text-white">{closed ? "Você arrematou!" : "Você está ganhando"}</p>
        ) : outbid && !closed ? (
          <p className="rounded-field bg-state-outbid px-3 py-2 text-center font-bold text-white">Você foi superado</p>
        ) : null}
        {paused ? <p className="rounded-field bg-store-bg px-3 py-2 text-center text-sm font-medium">O leiloeiro pausou o pregão. O tempo volta de onde parou.</p> : null}

        {!closed ? bidPanel : null}

        <div className="border-t border-store-border pt-3">
          <div className="mb-1.5 flex items-baseline justify-between gap-3">
            <h3 className={label}>Últimos lances</h3>
            <span className="text-xs text-store-text-muted tabular">{item.bids_count} no total</span>
          </div>
          {recentBids.length === 0 ? (
            <p className="py-2 text-sm text-store-text-muted">Nenhum lance ainda.</p>
          ) : (
            <ol className="flex flex-col">
              {recentBids.slice(0, 6).map((bid, index) => (
                <li
                  key={bid.id}
                  className={`flex items-baseline gap-3 border-b border-store-border/60 py-2 text-sm last:border-0 ${index === 0 ? "font-semibold" : "text-store-text-muted"} ${index === 0 ? "motion-safe:animate-row-in" : ""}`}
                >
                  <span className="min-w-0 flex-1 truncate">
                    {me && bid.bidder.id === me ? "Você" : bid.bidder.nickname}
                    {bid.type === "auto" ? <Zap aria-label="automático" className="ml-1 inline size-3.5 align-[-2px]" /> : null}
                  </span>
                  <time dateTime={bid.placed_at} className="text-xs font-normal text-store-text-muted tabular">
                    {clock.format(new Date(bid.placed_at))}
                  </time>
                  <span className={`tabular ${index === 0 ? "text-store-link" : ""}`}>{formatCents(bid.amount_cents)}</span>
                </li>
              ))}
            </ol>
          )}
        </div>
      </section>

      {/* Celular: a última fala do leiloeiro, logo abaixo do pregão. */}
      {highlightCompact}

      {/* Detalhes do item. */}
      <section className="flex min-w-0 flex-col gap-4 lg:col-start-1 lg:row-start-2" aria-label="Detalhes do item">
        <dl className="grid grid-cols-2 gap-2">
          <div className="rounded-field border border-store-border bg-store-surface px-3 py-2.5">
            <dt className="text-xs text-store-text-muted">Lance inicial</dt>
            <dd className="font-semibold tabular">{formatCents(item.starting_price_cents)}</dd>
          </div>
          <div className="rounded-field border border-store-border bg-store-surface px-3 py-2.5">
            <dt className="text-xs text-store-text-muted">Incremento</dt>
            <dd className="font-semibold tabular">{formatCents(item.increment_cents)}</dd>
          </div>
        </dl>
        {product?.description ? <p className="text-sm leading-relaxed whitespace-pre-line text-store-text-muted">{product.description}</p> : null}
        {product && product.attributes.length > 0 ? (
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 rounded-card border border-store-border bg-store-surface p-4 text-sm">
            {product.attributes.map((a) => (
              <div key={a.name} className="contents">
                <dt className="text-store-text-muted">{a.name}</dt>
                <dd className="font-medium">{a.value}</dd>
              </div>
            ))}
          </dl>
        ) : null}
        {product?.youtube_video_id ? <YouTubeFacade videoId={product.youtube_video_id} title={item.title} /> : null}
      </section>

      {/* Computador: destaque do leiloeiro e chat na lateral (embaixo, lado a lado, no médio). */}
      <aside className="hidden min-w-0 gap-4 lg:col-span-2 lg:row-start-3 lg:grid lg:grid-cols-2 lg:items-start xl:sticky xl:top-28 xl:col-span-1 xl:col-start-3 xl:row-span-2 xl:row-start-1 xl:flex xl:h-[calc(100dvh-8rem)] xl:flex-col">
        {highlight}
        {chat}
      </aside>
    </div>
  );
}

/**
 * Barra de lance: fixa embaixo no celular (o conteúdo da página termina acima dela) e dentro da
 * coluna lateral no computador.
 */
function BottomBar({ children, onHeight }: { children: ReactNode; onHeight: (height: number) => void }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const measure = () => onHeight(window.matchMedia("(min-width: 64rem)").matches ? 0 : el.offsetHeight);
    const observer = new ResizeObserver(measure);
    observer.observe(el);
    window.addEventListener("resize", measure);
    return () => {
      observer.disconnect();
      window.removeEventListener("resize", measure);
      onHeight(0);
    };
  }, [onHeight]);
  return (
    <div ref={ref} className="fixed inset-x-0 bottom-0 z-40 flex flex-col gap-3 border-t border-store-border bg-store-surface p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgb(0_0_0/0.08)] lg:static lg:border-0 lg:p-0 lg:shadow-none">
      {children}
    </div>
  );
}

function BidPanel({
  slug,
  here,
  base,
  item,
  situation,
  setSituation,
  bidderEmail,
  entered,
  onEnter,
  disabled,
  onBid,
  onToast,
  onBarHeight,
  dock,
}: {
  dock: ReactNode;
  slug: string;
  here: string;
  base: string;
  item: AuctionItem;
  situation: BidderAuctionSituation | null;
  setSituation: React.Dispatch<React.SetStateAction<BidderAuctionSituation | null>>;
  bidderEmail: string | null;
  entered: boolean;
  onEnter: () => void;
  disabled: boolean;
  onBid: (itemId: string, patch: Partial<AuctionItem>) => void;
  onToast: (t: Toast) => void;
  onBarHeight: (height: number) => void;
}) {
  const router = useRouter();
  const [sending, setSending] = useState(false);
  const [custom, setCustom] = useState("");
  const [autoOpen, setAutoOpen] = useState(false);
  const leading = !!situation && item.leader?.id === situation.bidder_id;
  const current = item.current_price_cents;
  const suggestions = current === null ? [item.next_minimum_cents] : [item.next_minimum_cents, current + 2 * item.increment_cents, current + 5 * item.increment_cents];

  if (!situation) {
    return (
      <BottomBar onHeight={onBarHeight}>
        {dock}
        <Link href={`/${slug}/entrar?voltar=${encodeURIComponent(here)}`} className={storeButton}>
          Entrar para dar lances
        </Link>
        <Link href={`/${slug}/cadastro?voltar=${encodeURIComponent(here)}`} className="inline-flex min-h-11 items-center justify-center text-sm font-medium text-store-link underline-offset-4 hover:underline">
          Ainda não tem conta? Cadastre-se
        </Link>
      </BottomBar>
    );
  }
  if (situation.banned) {
    return (
      <p className="flex items-center gap-2 text-sm font-medium text-state-outbid">
        <Lock aria-hidden className="size-4" />
        Sua conta está bloqueada nesta loja.
      </p>
    );
  }
  if (!situation.eligible) return <CompleteProfile slug={slug} situation={situation} email={bidderEmail} />;

  if (!entered) {
    return (
      <BottomBar onHeight={onBarHeight}>
        {dock}
        <button type="button" className={`${storeButton} w-full text-lg`} onClick={onEnter}>
          <Gavel aria-hidden className="size-5" />
          Entrar no auditório
        </button>
      </BottomBar>
    );
  }

  async function bid(amount: number) {
    if (sending) return;
    setSending(true);
    const response = await fetch(`${base}/lances/${item.id}`, {
      method: "POST",
      headers: { "Content-Type": "application/json", "Idempotency-Key": newKey() },
      body: JSON.stringify({ amount_cents: amount }),
    }).catch(() => null);
    setSending(false);
    const body = (await response?.json().catch(() => null)) as
      | { item?: { current_price_cents: number; next_minimum_cents: number; bids_count: number; ends_at: string | null; is_leading: boolean }; detail?: string; minimum_cents?: number; reason?: string }
      | null;

    if (response?.ok && body?.item) {
      setCustom("");
      onBid(item.id, {});
      if (navigator.vibrate) navigator.vibrate(30);
      return;
    }
    if (response?.status === 403 && body?.reason === "incomplete") setSituation((s) => (s ? { ...s, eligible: false } : s));
    if (response?.status === 403 && body?.reason === "banned") setSituation((s) => (s ? { ...s, banned: true } : s));
    if (response?.status === 401) router.push(`/${slug}/entrar?voltar=${encodeURIComponent(here)}`);
    if (body?.minimum_cents) onBid(item.id, { next_minimum_cents: body.minimum_cents });
    onToast({ tone: "error", text: body?.detail ?? "Sem conexão. Seu lance não foi enviado." });
  }

  const customCents = parseMoney(custom);
  const autoMax = situation.auto_bids[item.id];

  const open = item.status === "open";

  return (
    <BottomBar onHeight={onBarHeight}>
      {dock}
      <button
        type="button"
        className={`${storeButton} w-full text-lg transition-[transform,background-color] duration-150 ease-ui active:scale-[0.985]`}
        disabled={disabled || sending || leading || !open}
        onClick={() => void bid(item.next_minimum_cents)}
      >
        {leading ? "Você está na frente" : sending ? "Enviando…" : (
          <>
            Dar lance <span className="tabular">{formatCents(item.next_minimum_cents)}</span>
          </>
        )}
      </button>
      {!leading ? (
        <div className="-mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:px-0" aria-label="Outros valores">
          {suggestions.slice(1).map((amount) => (
            <button key={amount} type="button" className={`${storeButtonOutline} shrink-0 px-3 text-sm tabular`} disabled={disabled || sending || !open} onClick={() => void bid(amount)}>
              {formatCents(amount)}
            </button>
          ))}
        </div>
      ) : null}
      {/* Lance livre e automático lado a lado: o valor que a pessoa quiser, ou o limite que ela topa. */}
      <div className="flex gap-2">
        <form
          className={`flex min-w-0 flex-1 items-stretch overflow-hidden rounded-field border border-store-border bg-store-surface transition-colors focus-within:border-store-primary ${leading ? "opacity-60" : ""}`}
          onSubmit={(e) => {
            e.preventDefault();
            if (customCents) void bid(customCents);
          }}
        >
          <label htmlFor="custom-bid" className="sr-only">
            Lance livre (R$)
          </label>
          <input
            id="custom-bid"
            inputMode="numeric"
            placeholder="Lance livre"
            className="min-h-11 min-w-0 flex-1 bg-transparent px-3 text-base text-store-text tabular placeholder:text-store-text-muted focus:outline-none"
            value={custom}
            onChange={(e) => setCustom(maskMoney(e.target.value))}
            disabled={leading || !open}
          />
          <button type="submit" className="min-h-11 shrink-0 px-3 text-sm font-semibold text-store-link transition-colors hover:bg-store-bg disabled:opacity-50" disabled={!customCents || disabled || sending || leading || !open}>
            Ofertar
          </button>
        </form>
        <button type="button" className={`${storeButtonOutline} shrink-0 px-3 text-sm`} onClick={() => setAutoOpen(true)} aria-haspopup="dialog">
          <Zap aria-hidden className={`size-4 ${autoMax ? "text-state-winning" : ""}`} />
          {autoMax ? <>Até <span className="tabular">{formatCents(autoMax)}</span></> : "Automático"}
        </button>
      </div>
      {autoOpen ? (
        <AutoBidDialog
          base={base}
          item={item}
          current={autoMax ?? null}
          onClose={() => setAutoOpen(false)}
          onSaved={(max) => {
            setSituation((s) => (s ? { ...s, auto_bids: max === null ? Object.fromEntries(Object.entries(s.auto_bids).filter(([k]) => k !== item.id)) : { ...s.auto_bids, [item.id]: max } } : s));
            if (max !== null) onBid(item.id, {});
            onToast({ tone: "success", text: max === null ? "Lance automático desligado." : `Lance automático de até ${formatCents(max)} ativo.` });
            setAutoOpen(false);
          }}
        />
      ) : null}
    </BottomBar>
  );
}

function AutoBidDialog({ base, item, current, onClose, onSaved }: { base: string; item: AuctionItem; current: number | null; onClose: () => void; onSaved: (max: number | null) => void }) {
  const [value, setValue] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const cents = parseMoney(value);

  async function submit(remove = false) {
    setBusy(true);
    setError(null);
    const response = await fetch(`${base}/automatico/${item.id}`, {
      method: remove ? "DELETE" : "PUT",
      headers: { "Content-Type": "application/json" },
      body: remove ? undefined : JSON.stringify({ max_amount_cents: cents }),
    }).catch(() => null);
    setBusy(false);
    const body = (await response?.json().catch(() => null)) as { detail?: string } | null;
    if (response?.ok) onSaved(remove ? null : cents);
    else setError(body?.detail ?? "Não foi possível salvar.");
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="auto-title">
      <form
        className="flex w-full max-w-md flex-col gap-4 rounded-t-card bg-store-surface p-5 text-store-text shadow-xl sm:rounded-card"
        onSubmit={(e) => {
          e.preventDefault();
          if (cents) void submit();
        }}
      >
        <h2 id="auto-title" className="font-store-heading text-lg font-bold">
          Lance automático
        </h2>
        <p className="text-sm text-store-text-muted">
          Diga até quanto você topa pagar. O sistema cobre os outros lances por você, de incremento em incremento ({formatCents(item.increment_cents)}), só até esse valor.
        </p>
        {current ? (
          <p className="text-sm">
            Ativo até <strong className="tabular">{formatCents(current)}</strong>. Para mudar, informe um valor maior.
          </p>
        ) : null}
        <label className="flex flex-col gap-1.5 text-sm font-medium">
          Valor máximo (R$)
          <input inputMode="numeric" className={`${storeInput} tabular`} value={value} onChange={(e) => setValue(maskMoney(e.target.value))} placeholder={formatCents(item.next_minimum_cents)} autoFocus />
        </label>
        {error ? <p role="alert" className="text-sm font-medium text-state-outbid">{error}</p> : null}
        <div className="flex flex-wrap justify-end gap-2">
          {current ? (
            <button type="button" className={`${storeButtonOutline} mr-auto`} onClick={() => void submit(true)} disabled={busy}>
              Desligar
            </button>
          ) : null}
          <button type="button" className={storeButtonOutline} onClick={onClose}>
            Voltar
          </button>
          <button type="submit" className={storeButton} disabled={busy || !cents}>
            {busy ? "Salvando…" : "Ativar"}
          </button>
        </div>
      </form>
    </div>
  );
}

/** RF-D04: mostra o que falta e resolve sem sair do auditório (na prática, confirmar o e-mail). */
function CompleteProfile({ slug, situation, email }: { slug: string; situation: BidderAuctionSituation; email: string | null }) {
  const [sent, setSent] = useState(false);
  const onlyEmail = situation.missing.length === 1 && situation.missing[0] === "email";

  async function resend() {
    const response = await fetch(`/${slug}/api/conta/email/verification`, { method: "POST" }).catch(() => null);
    if (response?.ok || response?.status === 204) setSent(true);
  }

  return (
    <div className="flex flex-col gap-2 rounded-field border border-store-border p-3 text-sm">
      <p className="flex items-center gap-2 font-semibold">
        <MailCheck aria-hidden className="size-4 text-store-link" />
        {onlyEmail ? "Confirme seu e-mail para dar lances" : "Complete seu cadastro para dar lances"}
      </p>
      {onlyEmail ? (
        <>
          <p className="text-store-text-muted">Enviamos um link para {email ?? "o seu e-mail"}. Depois de confirmar, volte aqui.</p>
          {sent ? (
            <p className="font-medium text-state-winning">Link reenviado.</p>
          ) : (
            <button type="button" className="w-fit font-semibold text-store-link underline-offset-4 hover:underline" onClick={() => void resend()}>
              Reenviar o link
            </button>
          )}
        </>
      ) : (
        <Link href={`/${slug}/minha-conta/dados`} className="w-fit font-semibold text-store-link underline-offset-4 hover:underline">
          Completar cadastro
        </Link>
      )}
    </div>
  );
}

/* ---------- Fila e resultados ---------- */

/** Os próximos itens da fila, na ordem em que vão a pregão: uma faixa que rola de lado. */
function NextItems({ items, currentId }: { items: AuctionItem[]; currentId: string | null }) {
  const queued = items.filter((i) => i.status === "queued" && i.id !== currentId).sort((a, b) => a.position - b.position);
  if (queued.length === 0) return null;
  return (
    <section className="flex flex-col gap-3" aria-labelledby="next-items">
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="next-items" className="font-store-heading text-xl font-bold">
          Próximos itens
        </h2>
        <span className="text-sm text-store-text-muted tabular">
          {queued.length} na fila
        </span>
      </div>
      <ol className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:thin] lg:mx-0 lg:px-0">
        {queued.map((item, index) => (
          <li key={item.id} className="flex w-40 shrink-0 snap-start flex-col gap-2 rounded-card border border-store-border bg-store-surface p-3 sm:w-48 sm:flex-row sm:items-center sm:gap-3 lg:w-auto lg:min-w-0 lg:flex-1">
            <ItemThumb item={item} small />
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
              <span className="text-xs text-store-text-muted tabular">{index === 0 ? "A seguir" : `Item ${item.position}`}</span>
              <span className="line-clamp-2 text-sm leading-snug font-semibold">{item.title}</span>
              <span className="text-sm text-store-text-muted tabular">
                A partir de <strong className="text-store-text">{formatCents(item.starting_price_cents)}</strong>
              </span>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}

/** Link do WhatsApp do leiloeiro com a mensagem do arremate pronta (D-25: sem API paga). */
function winnerWhatsApp(base: string | null, { title, amount, auctionName, nickname }: { title: string; amount: number; auctionName: string; nickname: string | null }): string | null {
  if (!base) return null;
  const text = `Olá! ${nickname ? `Sou ${nickname} e arrematei` : "Arrematei"} "${title}" por ${formatCents(amount)} no leilão "${auctionName}". Como seguimos com o pagamento e a entrega?`;
  return `${base}?text=${encodeURIComponent(text)}`;
}

/** Botão "Falar com o leiloeiro no WhatsApp": abre o WhatsApp do aparelho com o texto pronto. */
function WhatsAppButton({ href, className = "" }: { href: string; className?: string }) {
  return (
    <a href={href} target="_blank" rel="noreferrer" className={`${storeButton} ${className}`}>
      <MessageCircle aria-hidden className="size-5" />
      Falar com o leiloeiro no WhatsApp
    </a>
  );
}

/**
 * Resultado do leilão: quais itens foram arrematados, por quem e por quanto. Ao vivo, mostra os que já
 * passaram ("Itens encerrados"); no fim, todos. O que a própria pessoa arrematou ganha destaque e o
 * botão do WhatsApp do leiloeiro.
 */
function ItemList({
  items,
  currentId,
  me,
  finished,
  whatsapp,
  auctionName,
  nickname,
  slug,
}: {
  items: AuctionItem[];
  currentId: string | null;
  me: string | null;
  finished: boolean;
  whatsapp: string | null;
  auctionName: string;
  nickname: string | null;
  slug: string;
}) {
  // Ao vivo, os itens na fila já aparecem em "Próximos itens"; aqui ficam os que já passaram.
  const others = items.filter((i) => i.id !== currentId && (finished || i.status !== "queued")).sort((a, b) => a.position - b.position);
  if (others.length === 0) return null;
  const sold = others.filter((i) => i.status === "closed_sold");
  const mine = sold.filter((i) => !!me && i.leader?.id === me);

  return (
    <section className="flex flex-col gap-3" aria-labelledby="results-title">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="results-title" className="font-store-heading text-xl font-bold">
          {finished ? "Resultado do leilão" : "Itens encerrados"}
        </h2>
        <p className="text-sm text-store-text-muted tabular">
          {sold.length} de {finished ? items.length : others.length} {sold.length === 1 ? "item arrematado" : "itens arrematados"}
          {mine.length > 0 ? (
            <>
              {` · ${mine.length} por você · `}
              <Link href={`/${slug}/minha-conta/arrematacoes`} className="font-semibold text-store-link underline-offset-4 hover:underline">
                Ver minhas arrematações
              </Link>
            </>
          ) : null}
        </p>
      </div>
      <ol className="flex flex-col overflow-hidden rounded-card border border-store-border bg-store-surface">
        {others.map((item) => {
          const won = item.status === "closed_sold" && !!me && item.leader?.id === me;
          const link = won ? winnerWhatsApp(whatsapp, { title: item.title, amount: item.current_price_cents ?? 0, auctionName, nickname }) : null;
          return (
            <li key={item.id} className={`flex flex-col gap-3 border-b border-store-border p-3 last:border-0 sm:flex-row sm:items-center sm:gap-4 sm:px-4 ${won ? "bg-state-winning/5" : ""}`}>
              <div className="flex min-w-0 flex-1 items-center gap-3">
                <span className="w-6 shrink-0 text-center text-sm text-store-text-muted tabular">{item.position}</span>
                <ItemThumb item={item} small />
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span className="line-clamp-2 font-semibold">{item.title}</span>
                  {item.status === "closed_sold" ? (
                    <span className="text-sm text-store-text-muted">
                      {won ? (
                        <strong className="font-semibold text-state-winning">Você arrematou</strong>
                      ) : (
                        <>
                          Arrematado por <strong className="font-semibold text-store-text">{item.leader?.nickname ?? "participante"}</strong>
                        </>
                      )}
                    </span>
                  ) : (
                    <span className="text-sm text-store-text-muted">
                      {item.status === "queued" ? `Não foi a pregão · lance inicial ${formatCents(item.starting_price_cents)}` : item.status === "skipped" ? "Retirado do pregão" : "Encerrado sem lances"}
                    </span>
                  )}
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                  {item.status === "closed_sold" ? <span className="font-bold tabular">{formatCents(item.current_price_cents ?? 0)}</span> : null}
                  <ItemStatusBadge status={item.status} />
                </div>
              </div>
              {link ? <WhatsAppButton href={link} className="w-full px-4 text-sm whitespace-nowrap sm:w-auto sm:shrink-0" /> : null}
            </li>
          );
        })}
      </ol>
    </section>
  );
}

function ItemThumb({ item, small = false }: { item: AuctionItem; small?: boolean }) {
  const thumb = item.product?.photos.find((p) => p.status === "ready")?.thumb;
  const size = small ? "size-16" : "size-20";
  return thumb ? (
    // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
    <img src={thumb} alt="" className={`${size} shrink-0 rounded-field object-cover`} loading="lazy" />
  ) : (
    <span className={`flex ${size} shrink-0 items-center justify-center rounded-field bg-store-bg text-store-text-muted`}>
      <ImageOff aria-hidden className="size-5" />
    </span>
  );
}
