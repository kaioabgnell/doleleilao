"use client";

import { useEffect, useRef } from "react";
import { formatRemaining } from "@/lib/auction-time";

/**
 * Cronômetro desenhado no navegador a partir do `ends_at` do servidor + a diferença entre os relógios
 * (`offsetMs` = hora do servidor − hora do aparelho). Atualiza só o próprio texto a cada quadro, sem
 * renderizar a tela inteira (specs/04-arquitetura.md#frontend-nextjs).
 */
export function Countdown({
  endsAt,
  offsetMs,
  pausedMs = null,
  urgentBelowMs = 15_000,
  className = "",
}: {
  endsAt: string | null;
  offsetMs: number;
  pausedMs?: number | null;
  urgentBelowMs?: number;
  className?: string;
}) {
  const ref = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (pausedMs !== null) {
      el.textContent = formatRemaining(pausedMs);
      return;
    }
    if (!endsAt) {
      el.textContent = "--:--";
      return;
    }
    const end = Date.parse(endsAt);
    let frame = 0;
    let last = "";
    const tick = () => {
      const remaining = end - (Date.now() + offsetMs);
      const text = remaining <= 0 ? "00:00" : formatRemaining(remaining);
      if (text !== last) {
        el.textContent = text;
        el.dataset.urgent = remaining < urgentBelowMs ? "true" : "false";
        last = text;
      }
      if (remaining > 0) frame = requestAnimationFrame(tick);
    };
    tick();
    return () => cancelAnimationFrame(frame);
  }, [endsAt, offsetMs, pausedMs, urgentBelowMs]);

  return (
    <span
      ref={ref}
      role="timer"
      aria-live="off"
      className={`tabular data-[urgent=true]:text-state-outbid ${className}`}
      suppressHydrationWarning
    >
      --:--
    </span>
  );
}
