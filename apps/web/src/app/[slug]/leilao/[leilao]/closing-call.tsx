"use client";

import { useEffect, useRef, useState } from "react";
import { Gavel } from "lucide-react";
import type { AuctionItem } from "@/lib/api-types";
import { formatCents } from "@/lib/money";

/** Cada chamada ("Dole 1", "Dole 2", "Dole 3") dura 1 s; o resultado fica 3 s na tela. */
export const CALL_STEP_MS = 1000;
export const CALL_RESULT_AT_MS = 3 * CALL_STEP_MS;
/** As chamadas começam 3 s antes do fim do tempo: dá para dar o último lance durante o "Dole". */
export const CALL_LEAD_MS = CALL_RESULT_AT_MS;
const RESULT_HOLD_MS = 3000;
/** Se o servidor demorar para fechar o item, o "Dole 3" espera até este limite e some. */
const WAIT_CLOSE_MS = 6000;

export interface Call {
  itemId: string;
  startedAt: number;
}

/**
 * Fechamento do item no auditório: nos 3 últimos segundos aparecem "Dole 1", "Dole 2", "Dole 3" numa
 * faixa no topo e, quando o servidor fecha, o vencedor. Não cobre a tela nem bloqueia toques: o botão
 * de lance continua livre, e um lance nesse instante prorroga o item e cancela as chamadas.
 */
export function ClosingCall({ call, item, me, onDone }: { call: Call; item: AuctionItem | undefined; me: string | null; onDone: () => void }) {
  const [now, setNow] = useState(call.startedAt);
  // Quando o fechamento chegou do servidor (hora local): o resultado fica 3 s a partir daí.
  const [closedSeenAt, setClosedSeenAt] = useState<number | null>(null);
  const closed = !!item && ["closed_sold", "closed_unsold"].includes(item.status);
  const closedRef = useRef(closed);
  useEffect(() => {
    closedRef.current = closed;
  });

  useEffect(() => {
    const timer = setInterval(() => {
      const t = Date.now();
      setNow(t);
      if (closedRef.current) setClosedSeenAt((seen) => seen ?? t);
    }, 100);
    return () => clearInterval(timer);
  }, []);

  const elapsed = now - call.startedAt;
  const showResult = closed && elapsed >= CALL_RESULT_AT_MS;
  const resultSince = Math.max(CALL_RESULT_AT_MS, closedSeenAt === null ? 0 : closedSeenAt - call.startedAt);
  const finished = (showResult && elapsed >= resultSince + RESULT_HOLD_MS) || (!closed && elapsed >= WAIT_CLOSE_MS) || item?.status === "skipped";

  useEffect(() => {
    if (finished) onDone();
  }, [finished, onDone]);

  if (finished || !item) return null;
  const step = Math.min(3, Math.floor(elapsed / CALL_STEP_MS) + 1);
  const mine = !!me && item.leader?.id === me;

  return (
    <div
      role="status"
      aria-live="assertive"
      className="pointer-events-none flex w-full max-w-md items-center gap-3 rounded-card bg-store-surface px-4 py-2.5 text-store-text shadow-[0_8px_24px_rgb(0_0_0/0.16)] ring-1 ring-store-border motion-safe:animate-[dl-call-in_360ms_cubic-bezier(0.2,0.9,0.3,1)]"
    >
      <span className={`flex size-10 shrink-0 items-center justify-center rounded-full ${showResult ? "bg-state-winning text-white" : "bg-store-primary text-store-on-primary"}`}>
        <Gavel aria-hidden className="size-5" />
      </span>
      {showResult ? (
        <div key="result" className="min-w-0 flex-1 motion-safe:animate-[dl-call-in_360ms_cubic-bezier(0.2,0.9,0.3,1)]">
          {item.status === "closed_sold" ? (
            <>
              <p className="font-store-heading text-lg leading-tight font-bold tracking-wide uppercase">Vencedor</p>
              <p className="truncate text-sm">
                <strong>{mine ? "Você" : (item.leader?.nickname ?? "")}</strong> · <span className="font-semibold tabular">{formatCents(item.current_price_cents ?? 0)}</span>
              </p>
            </>
          ) : (
            <>
              <p className="font-store-heading text-lg leading-tight font-bold tracking-wide uppercase">Sem lances</p>
              <p className="text-sm text-store-text-muted">O item fecha sem vencedor.</p>
            </>
          )}
        </div>
      ) : (
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <p key={step} className="font-store-heading text-2xl leading-none font-bold tracking-wide uppercase tabular motion-safe:animate-[dl-call-in_360ms_cubic-bezier(0.2,0.9,0.3,1)]">
            Dole {step}
          </p>
          <span aria-hidden className="flex gap-1.5">
            {[1, 2, 3].map((n) => (
              <span key={n} className={`size-2 rounded-full transition-colors duration-200 ${n <= step ? "bg-store-primary" : "bg-store-border"}`} />
            ))}
          </span>
          <p className="ml-auto hidden truncate text-xs text-store-text-muted sm:block">{item.title}</p>
        </div>
      )}
    </div>
  );
}
