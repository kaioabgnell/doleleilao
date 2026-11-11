"use client";

/**
 * Quadros da demonstração da tela de entrada (D-53), copiados das telas reais: o pregão no painel
 * do leiloeiro (`painel/leiloes/[id]/pregao`) e o auditório no celular (`[slug]/leilao/[leilao]`).
 * Só desenham o `Frame` do roteiro; não têm estado próprio.
 */

import Image from "next/image";
import { useLayoutEffect, useRef, useState, type CSSProperties, type RefObject } from "react";
import {
  Bell,
  Gavel,
  LayoutDashboard,
  MousePointer2,
  Package,
  Pause,
  SkipForward,
  Square,
  Trophy,
  Undo2,
  Zap,
} from "lucide-react";
import { formatCents } from "@/lib/money";
import { AUTO_BID, clock, ITEM, PHONE_USER, type Frame } from "./story";

const BID_ROW = 26;
const PHONE_BID_ROW = 18;
const CHAT_ROW = 40;

function initials(name: string): string {
  const parts = name.split(/[._\s]+/).filter(Boolean);
  return (parts.length > 1 ? parts[0]![0]! + parts[1]![0]! : name.slice(0, 2)).toUpperCase();
}

/** Valor que "rola" para cima quando muda (a chave remonta o elemento). */
function Rolling({ value, className = "" }: { value: string; className?: string }) {
  return (
    <span className={`relative inline-block overflow-hidden align-bottom ${className}`}>
      <span key={value} className="inline-block animate-roll-in tabular">
        {value}
      </span>
    </span>
  );
}

/** Ponto parado com um anel que cresce e some (sinal de "ao vivo"). */
export function LiveDot({ className = "bg-state-live" }: { className?: string }) {
  return (
    <span className="relative inline-flex size-1.5 shrink-0">
      <span className={`absolute inset-0 animate-live-ring rounded-full ${className}`} />
      <span className={`relative size-1.5 rounded-full ${className}`} />
    </span>
  );
}

const statusChip: Record<Frame["status"], { label: string; className: string }> = {
  live: { label: "Em pregão", className: "bg-state-live/10 text-state-live" },
  closing: { label: "Encerrando", className: "bg-dl-copper/10 text-dl-copper" },
  sold: { label: "Vendido", className: "bg-state-winning/10 text-state-winning" },
};

/* ----------------------------------------------------------------------------------------------
 * Painel do leiloeiro: pregão ao vivo
 * ---------------------------------------------------------------------------------------------- */

/** Posição de `el` dentro de `root`, pelo layout (não muda com a escala do palco). */
function offsetWithin(el: HTMLElement, root: HTMLElement): { x: number; y: number } {
  let x = el.offsetWidth / 2;
  let y = el.offsetHeight / 2;
  let node: HTMLElement | null = el;
  while (node && node !== root) {
    x += node.offsetLeft;
    y += node.offsetTop;
    node = node.offsetParent as HTMLElement | null;
  }
  return { x, y };
}

function useTargets(root: RefObject<HTMLElement | null>, targets: RefObject<HTMLElement | null>[]) {
  const [points, setPoints] = useState<{ x: number; y: number }[] | null>(null);
  useLayoutEffect(() => {
    const r = root.current;
    if (!r) return;
    const measure = () => {
      const next = targets.map((t) => (t.current ? offsetWithin(t.current, r) : { x: 0, y: 0 }));
      setPoints(next);
    };
    measure();
    // As fontes chegam depois da primeira pintura e mudam a largura dos botões.
    void document.fonts?.ready.then(measure);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return points;
}

export function PanelFrame({ frame }: { frame: Frame }) {
  const rootRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef<HTMLSpanElement>(null);
  const confirmRef = useRef<HTMLSpanElement>(null);
  const points = useTargets(rootRef, [closeRef, confirmRef]);
  const chip = statusChip[frame.status];
  const pressingClose = frame.cursor === "pressClose";
  const pressingConfirm = frame.cursor === "pressConfirm";

  return (
    <div ref={rootRef} className="relative w-[590px] overflow-hidden rounded-[14px] bg-dl-card font-sans text-dl-ink shadow-2xl shadow-black/50 ring-1 ring-black/5">
      <div className="flex h-8 items-center gap-3 border-b border-dl-line bg-dl-page px-3">
        <span className="flex gap-1.5">
          <i className="size-2 rounded-full bg-dl-line" />
          <i className="size-2 rounded-full bg-dl-line" />
          <i className="size-2 rounded-full bg-dl-line" />
        </span>
        <span className="font-code text-[11px] text-dl-ink-muted">
          Painel do leiloeiro · <b className="font-medium text-dl-ink">Pregão</b>
        </span>
      </div>

      <div className="flex">
        <nav className="flex w-11 shrink-0 flex-col items-center gap-2 border-r border-dl-line bg-dl-page py-3 text-dl-ink-muted">
          <LayoutDashboard className="size-4" />
          <Package className="size-4" />
          <span className="flex size-7 items-center justify-center rounded-[7px] bg-dl-copper text-white">
            <Gavel className="size-4" />
          </span>
          <Trophy className="size-4" />
          <Bell className="size-4" />
        </nav>

        <div className="flex min-w-0 flex-1 flex-col gap-3 p-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="flex items-center gap-2 text-[15px] font-semibold">
                Leilão de Sexta
                <span className="inline-flex items-center gap-1 rounded-full bg-state-live px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">
                  <LiveDot className="bg-white" />
                  Ao vivo
                </span>
              </p>
              <p className="mt-0.5 text-[11px] text-dl-ink-muted">8 itens · 37 marcaram “Quero participar”</p>
            </div>
            <span className="inline-flex items-center gap-1.5 text-[11px] text-dl-ink-muted">
              <span className="size-1.5 rounded-full bg-state-winning" />
              Tempo real conectado
            </span>
          </div>

          <div className="flex gap-3 rounded-[10px] border border-dl-line p-3">
            <Image src={ITEM.thumb} alt="" width={240} height={160} sizes="96px" className="size-24 shrink-0 rounded-[8px] object-cover" />
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
              <div className="flex items-center justify-between gap-2 text-[11px] text-dl-ink-muted">
                <span>{ITEM.position}</span>
                <span key={frame.status} className={`inline-flex animate-roll-in items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ${chip.className}`}>
                  {frame.status === "live" ? <LiveDot /> : null}
                  {chip.label}
                </span>
              </div>
              <p className="text-[14px] font-semibold">{ITEM.title}</p>
              <dl className="grid grid-cols-[1.3fr_1fr_1fr] gap-2">
                <div>
                  <dt className="font-code text-[9.5px] tracking-wider text-dl-ink-muted uppercase">Lance atual</dt>
                  <dd className="text-[17px] font-semibold">
                    <Rolling value={formatCents(frame.priceCents)} />
                  </dd>
                </div>
                <div>
                  <dt className="flex items-center gap-1.5 font-code text-[9.5px] tracking-wider text-dl-ink-muted uppercase">
                    Tempo
                    {frame.extensions > 0 && frame.status === "live" ? (
                      <span key={frame.extensions} className="animate-roll-in rounded-full bg-dl-copper/10 px-1.5 font-sans text-[9px] font-semibold tracking-normal text-dl-copper normal-case">
                        +15 s
                      </span>
                    ) : null}
                  </dt>
                  <dd className={`font-code text-[17px] font-medium tabular ${frame.seconds <= 5 && frame.status === "live" ? "text-state-live" : ""}`}>
                    {clock(frame.seconds)}
                  </dd>
                </div>
                <div className="min-w-0">
                  <dt className="font-code text-[9.5px] tracking-wider text-dl-ink-muted uppercase">Ganhando</dt>
                  <dd className="truncate text-[15px] font-semibold">
                    <Rolling value={frame.leader} />
                  </dd>
                </div>
              </dl>
              <p className="text-[11px] text-dl-ink-muted">
                {frame.status === "sold" ? (
                  <>Arrematado por {frame.leader} · {frame.bidCount} lances</>
                ) : (
                  <>
                    Próximo lance mínimo <span className="tabular">{formatCents(frame.nextMinimumCents)}</span> · {frame.bidCount} lances
                  </>
                )}
              </p>
            </div>
          </div>

          <div className={`flex gap-2 text-[12px] font-medium transition-opacity duration-300 ${frame.status === "live" ? "" : "opacity-50"}`}>
            <span className="inline-flex h-8 items-center gap-1.5 rounded-[6px] border border-dl-line px-2.5">
              <Pause className="size-3.5" /> Pausar
            </span>
            <span
              ref={closeRef}
              className={`inline-flex h-8 items-center gap-1.5 rounded-[6px] border px-2.5 transition-[scale,background-color,border-color] duration-150 ${
                pressingClose ? "scale-[.96] border-dl-copper bg-dl-page" : frame.cursor === "toClose" ? "border-dl-copper text-dl-copper" : "border-dl-line"
              }`}
            >
              <Square className="size-3.5" /> Encerrar agora
            </span>
            <span className="inline-flex h-8 items-center gap-1.5 rounded-[6px] border border-dl-line px-2.5">
              <SkipForward className="size-3.5" /> Pular
            </span>
            <span className="inline-flex h-8 items-center gap-1.5 rounded-[6px] border border-state-outbid/30 px-2.5 text-state-outbid">
              <Undo2 className="size-3.5" /> Anular último lance
            </span>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-[10px] border border-dl-line p-3">
              <p className="text-[11px] font-semibold">Últimos lances</p>
              <ol className="relative mt-2 overflow-hidden" style={{ height: BID_ROW * 4 }}>
                {frame.bids.map((bid, index) => (
                  <li
                    key={bid.id}
                    className="absolute inset-x-0 top-0 transition-transform duration-300 ease-enter"
                    style={{ transform: `translateY(${index * BID_ROW}px)`, height: BID_ROW }}
                  >
                    <div className={`flex h-full animate-row-in items-center justify-between gap-2 text-[12px] ${index === 0 ? "font-semibold" : "text-dl-ink-muted"}`}>
                      <span className="truncate">
                        {bid.who}
                        {bid.auto ? <span className="ml-1 text-[10.5px] font-normal">(automático)</span> : null}
                      </span>
                      <span className="tabular">{formatCents(bid.cents)}</span>
                    </div>
                  </li>
                ))}
              </ol>
            </div>
            <div className="rounded-[10px] border border-dl-line p-3">
              <p className="text-[11px] font-semibold">Chat do leilão</p>
              <ol className="relative mt-2 overflow-hidden" style={{ height: CHAT_ROW * 2 + 4 }}>
                {frame.chat.map((msg, index) => {
                  const fromBottom = frame.chat.length - 1 - index;
                  return (
                    <li
                      key={msg.id}
                      className="absolute inset-x-0 bottom-0 transition-transform duration-300 ease-enter"
                      style={{ transform: `translateY(${-fromBottom * (CHAT_ROW + 4)}px)`, height: CHAT_ROW }}
                    >
                      <div className="flex h-full animate-row-in items-center gap-2">
                        <span
                          className={`flex size-6 shrink-0 items-center justify-center rounded-full text-[9px] font-bold ${
                            msg.staff ? "bg-dl-copper text-white" : "bg-dl-ink text-white"
                          }`}
                        >
                          {initials(msg.who)}
                        </span>
                        <span className="min-w-0">
                          <span className="block text-[10px] text-dl-ink-muted">{msg.who} · 20h05</span>
                          <span className="block truncate text-[12px]">{msg.text}</span>
                        </span>
                      </div>
                    </li>
                  );
                })}
              </ol>
            </div>
          </div>
        </div>
      </div>

      {/* Confirmação do "Encerrar agora" (a mesma do painel: useConfirm). Sempre montada, para medir o botão. */}
      <div
        className={`absolute inset-0 flex items-center justify-center bg-dl-ink/25 transition-opacity duration-200 ${frame.confirmOpen ? "opacity-100" : "pointer-events-none opacity-0"}`}
      >
        <div className={`w-[300px] rounded-[12px] bg-dl-card p-4 shadow-2xl shadow-black/30 ${frame.confirmOpen ? "animate-[dl-dialog-in_200ms_var(--ease-enter)_both]" : ""}`}>
          <span className="flex size-8 items-center justify-center rounded-full bg-dl-copper/10 text-dl-copper">
            <Square className="size-4" />
          </span>
          <p className="mt-2.5 text-[14px] font-semibold">Encerrar e vender para {frame.leader}?</p>
          <p className="mt-1 text-[11.5px] text-dl-ink-muted">
            O item é arrematado agora por <span className="tabular">{formatCents(frame.priceCents)}</span>, sem esperar o tempo acabar.
          </p>
          <div className="mt-3 flex justify-end gap-2 text-[12px] font-semibold">
            <span className="inline-flex h-8 items-center rounded-[6px] border border-dl-line px-3">Cancelar</span>
            <span
              ref={confirmRef}
              className={`inline-flex h-8 items-center rounded-[6px] px-3 text-white transition-[scale,background-color] duration-150 ${pressingConfirm ? "scale-[.96] bg-dl-copper-hover" : "bg-dl-copper"}`}
            >
              Encerrar e vender
            </span>
          </div>
        </div>
      </div>

      {points ? <Cursor frame={frame} close={points[0]!} confirm={points[1]!} /> : null}
    </div>
  );
}

/** Ponteiro do leiloeiro: desliza até o botão, aperta (anel de 28 px) e some. */
function Cursor({ frame, close, confirm }: { frame: Frame; close: { x: number; y: number }; confirm: { x: number; y: number } }) {
  const phase = frame.cursor;
  const start = { x: close.x + 220, y: close.y + 150 };
  const at = phase === "hidden" ? start : phase === "toClose" || phase === "pressClose" ? close : confirm;
  const pressing = phase === "pressClose" || phase === "pressConfirm";
  const travel = phase === "toClose" ? "900ms" : phase === "toConfirm" ? "450ms" : "0ms";

  return (
    <div
      aria-hidden
      className="pointer-events-none absolute top-0 left-0 z-10 transition-[translate,opacity] ease-travel"
      style={{
        translate: `${at.x}px ${at.y}px`,
        opacity: phase === "hidden" || phase === "leaving" ? 0 : 1,
        transitionDuration: `${travel}, 200ms`,
      }}
    >
      {pressing ? <span className="absolute -top-3.5 -left-3.5 size-7 animate-tap-ring rounded-full bg-dl-copper/40" /> : null}
      <MousePointer2 className={`size-5 fill-dl-ink stroke-white transition-[scale] duration-100 ${pressing ? "scale-90" : ""}`} strokeWidth={1.5} />
    </div>
  );
}

/* ----------------------------------------------------------------------------------------------
 * Celular do participante: auditório
 * ---------------------------------------------------------------------------------------------- */

const CONFETTI: { left: number; delay: number; x: number; r: number; tone: string }[] = Array.from({ length: 18 }, (_, i) => ({
  left: (i * 37) % 100,
  delay: (i * 53) % 420,
  x: ((i * 29) % 70) - 35,
  r: 120 + ((i * 67) % 300),
  tone: ["bg-dl-copper", "bg-state-winning", "bg-dl-lime", "bg-dl-copper-glow"][i % 4]!,
}));

export function PhoneFrame({ frame, celebrate }: { frame: Frame; celebrate: boolean }) {
  const mine = frame.leader === PHONE_USER;
  const sold = frame.status === "sold";
  const pill = sold
    ? { text: "Você arrematou!", className: "bg-state-winning" }
    : mine
      ? { text: "Você está ganhando", className: "bg-state-winning" }
      : { text: "Você foi superado", className: "bg-state-outbid" };

  return (
    <div className="w-[212px] rounded-[36px] bg-dl-stage-raised p-[7px] shadow-2xl shadow-black/60 ring-1 ring-white/10">
      <div className="relative flex h-[422px] flex-col overflow-hidden rounded-[29px] bg-store-bg font-sans text-store-text">
        <div className="flex h-7 shrink-0 items-center justify-between px-5 text-[10px] font-semibold">
          <span className="tabular">20:05</span>
          <span className="h-[14px] w-[52px] rounded-full bg-dl-stage" />
          <span className="flex h-2.5 w-5 items-center rounded-[3px] border border-current p-px">
            <span className="h-full w-3/4 rounded-[1px] bg-current" />
          </span>
        </div>
        <div className="flex items-center justify-between px-3 pb-2">
          <span className="text-[11px] font-semibold">Leilão de Sexta</span>
          <span className="inline-flex items-center gap-1 rounded-full bg-state-live px-1.5 py-px text-[9px] font-bold text-white uppercase">
            <LiveDot className="bg-white" />
            Ao vivo
          </span>
        </div>
        <Image src={ITEM.photo} alt="" width={500} height={333} sizes="200px" className="h-[104px] w-full shrink-0 object-cover" />
        <div className="flex flex-1 flex-col gap-2 px-3 pt-2.5 pb-3">
          <div>
            <p className="text-[10px] text-store-text-muted">{ITEM.position}</p>
            <p className="text-[13px] leading-tight font-semibold">{ITEM.title}</p>
          </div>
          <div className="flex items-end justify-between">
            <div>
              <p className="text-[9px] tracking-wider text-store-text-muted uppercase">Lance atual</p>
              <p className="text-[18px] leading-none font-bold">
                <Rolling value={formatCents(frame.priceCents)} />
              </p>
            </div>
            <p className={`font-code text-[15px] leading-none font-medium tabular ${frame.seconds <= 5 && !sold ? "text-state-live" : ""}`}>{clock(frame.seconds)}</p>
          </div>
          <p key={pill.text} className={`animate-roll-in rounded-[8px] py-1.5 text-center text-[11px] font-bold text-white ${pill.className}`}>
            {pill.text}
          </p>
          <ol className="relative overflow-hidden" style={{ height: PHONE_BID_ROW * 2 }}>
            {frame.bids.slice(0, 3).map((bid, index) => (
              <li
                key={bid.id}
                className="absolute inset-x-0 top-0 transition-transform duration-300 ease-enter"
                style={{ transform: `translateY(${index * PHONE_BID_ROW}px)`, height: PHONE_BID_ROW }}
              >
                <div className={`flex h-full animate-row-in items-center justify-between text-[10.5px] ${index === 0 ? "font-semibold" : "text-store-text-muted"}`}>
                  <span>{bid.who === PHONE_USER ? "Você" : bid.who}</span>
                  <span className="tabular">{formatCents(bid.cents)}</span>
                </div>
              </li>
            ))}
          </ol>
          <div className="relative mt-auto">
            <span
              className={`flex h-10 items-center justify-center gap-1 rounded-[10px] bg-store-primary text-[13px] font-bold text-store-on-primary transition-[scale] duration-150 ${frame.tap ? "scale-[.96]" : ""}`}
            >
              Dar lance <span className="tabular">{formatCents(frame.nextMinimumCents)}</span>
            </span>
            {frame.tap ? <span key={frame.tap} className="absolute top-1/2 left-1/2 -mt-3.5 -ml-3.5 size-7 animate-tap-ring rounded-full bg-white/70" /> : null}
          </div>
          <span className="flex h-8 items-center justify-center rounded-[10px] border border-store-border text-[12px] font-semibold">Lance automático</span>
        </div>

        {frame.call ? (
          <div className="absolute inset-0 flex items-center justify-center bg-black/40 p-4">
            <div className="flex w-full flex-col items-center gap-2 rounded-[14px] bg-store-surface px-4 py-5 text-center">
              <p key={frame.call} className="animate-call-in text-[30px] leading-none font-bold tracking-wide uppercase tabular">
                Dole {frame.call}
              </p>
              <span className="flex gap-1.5">
                {[1, 2, 3].map((n) => (
                  <span key={n} className={`size-2 rounded-full transition-colors duration-200 ${n <= frame.call ? "bg-store-primary" : "bg-store-border"}`} />
                ))}
              </span>
              <p className="text-[10px] text-store-text-muted">{ITEM.title}</p>
            </div>
          </div>
        ) : null}

        {sold ? (
          <div className="absolute inset-0 flex items-center justify-center bg-black/45 p-3">
            {celebrate ? (
              <div aria-hidden className="pointer-events-none absolute inset-x-0 top-0 h-full overflow-hidden">
                {CONFETTI.map((c, i) => (
                  <i
                    key={i}
                    className={`absolute top-0 h-2 w-1.5 rounded-[1px] opacity-0 ${c.tone}`}
                    style={
                      {
                        left: `${c.left}%`,
                        animation: `dl-confetti-fall 1.6s ${c.delay}ms cubic-bezier(.2,.8,.2,1) both`,
                        "--x": `${c.x}px`,
                        "--r": `${c.r}deg`,
                      } as CSSProperties
                    }
                  />
                ))}
              </div>
            ) : null}
            <div className="relative flex w-full animate-call-in flex-col items-center gap-1.5 rounded-[14px] bg-store-surface px-3 py-4 text-center">
              <span className="flex size-10 items-center justify-center rounded-full bg-state-winning text-white">
                <Trophy className="size-5" />
              </span>
              <p className="text-[15px] font-bold">Você é o ganhador!</p>
              <p className="text-[11px] leading-snug text-store-text-muted">
                Você arrematou o relógio por <b className="text-store-text tabular">{formatCents(frame.priceCents)}</b>. O leiloeiro vai entrar em contato.
              </p>
              <span className="mt-1 flex h-8 w-full items-center justify-center rounded-[8px] bg-store-primary text-[11px] font-bold text-store-on-primary">
                Continuar no leilão
              </span>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

/* ----------------------------------------------------------------------------------------------
 * Cartões flutuantes: lance automático e aviso do sino
 * ---------------------------------------------------------------------------------------------- */

const autoChip: Record<Frame["autoBid"], { label: string; className: string; text: (f: Frame) => string }> = {
  active: {
    label: "Ativo",
    className: "bg-state-winning/10 text-state-winning",
    text: () => "Cobre cada lance com o mínimo necessário.",
  },
  covered: {
    label: "Cobriu",
    className: "bg-dl-copper/10 text-dl-copper",
    text: () => `Cobriu ${PHONE_USER} com ${formatCents(AUTO_BID.maxCents)}.`,
  },
  exceeded: {
    label: "Superado",
    className: "bg-state-outbid/10 text-state-outbid",
    text: () => `Máximo superado. ${AUTO_BID.who} recebeu o aviso.`,
  },
};

export function AutoBidCard({ frame, narrow = false }: { frame: Frame; narrow?: boolean }) {
  const chip = autoChip[frame.autoBid];
  return (
    <div className={`rounded-[14px] bg-dl-card p-3.5 font-sans text-dl-ink shadow-2xl shadow-black/40 ring-1 ring-black/5 ${narrow ? "w-[196px]" : "w-[290px]"}`}>
      <div className={`flex gap-2 ${narrow ? "flex-col items-start" : "items-center justify-between"}`}>
        <span className="flex items-center gap-2 text-[13px] font-semibold">
          <span className="flex size-7 items-center justify-center rounded-[8px] bg-dl-copper/10 text-dl-copper">
            <Zap className="size-4" />
          </span>
          Lance automático
        </span>
        <span key={frame.autoBid} className={`animate-roll-in rounded-full px-2 py-0.5 text-[10px] font-semibold ${chip.className}`}>
          {chip.label}
        </span>
      </div>
      <p className="mt-2.5 text-[12px]">
        {AUTO_BID.who} · até <span className="tabular">{formatCents(AUTO_BID.maxCents)}</span>
      </p>
      <span className="mt-2 block h-1.5 overflow-hidden rounded-full bg-dl-line">
        <span
          className={`block h-full origin-left rounded-full transition-[scale,background-color] duration-300 ease-enter ${frame.autoBid === "exceeded" ? "bg-state-outbid" : "bg-dl-copper"}`}
          style={{ scale: `${frame.autoBidUsed} 1` }}
        />
      </span>
      <p key={frame.autoBid} className="mt-2 min-h-[2lh] animate-row-in text-[11px] leading-snug text-dl-ink-muted">
        {chip.text(frame)}
      </p>
    </div>
  );
}

export function WinToast({ frame, narrow = false }: { frame: Frame; narrow?: boolean }) {
  if (!frame.toast) return null;
  return (
    <div
      role="presentation"
      className={`flex animate-toast-in gap-3 rounded-[14px] bg-dl-card p-3 font-sans text-dl-ink shadow-2xl shadow-black/40 ring-1 ring-black/5 ${narrow ? "w-[196px]" : "w-[300px]"}`}
    >
      <span className="relative flex size-8 shrink-0 items-center justify-center rounded-full bg-dl-ink text-white">
        <Bell className="size-4" />
        <span className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full border-2 border-dl-card bg-dl-copper" />
      </span>
      <span className="min-w-0">
        <span className="flex items-baseline justify-between gap-2">
          <b className="text-[13px] font-semibold">Item arrematado</b>
          {narrow ? null : <span className="text-[10px] text-dl-ink-muted">agora</span>}
        </span>
        <span className="mt-0.5 block text-[11.5px] leading-snug text-dl-ink-muted">
          {ITEM.title} por <span className="tabular">{formatCents(frame.priceCents)}</span> para {frame.leader}.
        </span>
      </span>
    </div>
  );
}
