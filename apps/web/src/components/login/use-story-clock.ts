"use client";

import { useEffect, useState, useSyncExternalStore } from "react";
import { CYCLE_MS, FINAL_MS } from "./story";

const REDUCED = "(prefers-reduced-motion: reduce)";
/** Espera a entrada da tela (cerca de 1,1 s) antes de rodar a história. */
const START_DELAY_MS = 1_200;
const TICK_MS = 100;

function subscribeReduced(onChange: () => void) {
  const query = window.matchMedia(REDUCED);
  query.addEventListener("change", onChange);
  return () => query.removeEventListener("change", onChange);
}

export function usePrefersReducedMotion(): boolean {
  return useSyncExternalStore(
    subscribeReduced,
    () => window.matchMedia(REDUCED).matches,
    () => false,
  );
}

function subscribeVisibility(onChange: () => void) {
  document.addEventListener("visibilitychange", onChange);
  return () => document.removeEventListener("visibilitychange", onChange);
}

function usePageVisible(): boolean {
  return useSyncExternalStore(
    subscribeVisibility,
    () => document.visibilityState === "visible",
    () => true,
  );
}

/**
 * Relógio da demonstração. Começa no quadro final (o mesmo que o servidor desenha), segura um
 * instante e segue o ciclo. Para quando a pessoa pausa, quando a aba fica oculta e com
 * "reduzir movimento" (aí fica no quadro final).
 */
export function useStoryClock(paused: boolean): { t: number; cycle: number; reduced: boolean } {
  const reduced = usePrefersReducedMotion();
  const visible = usePageVisible();
  const [started, setStarted] = useState(false);
  const [clock, setClock] = useState({ t: FINAL_MS, cycle: 0 });

  useEffect(() => {
    const id = setTimeout(() => setStarted(true), START_DELAY_MS);
    return () => clearTimeout(id);
  }, []);

  const running = started && !paused && !reduced && visible;

  useEffect(() => {
    if (!running) return;
    let last = performance.now();
    const id = setInterval(() => {
      const now = performance.now();
      // Um travamento longo (aba em segundo plano, depurador) não pula a história inteira.
      const dt = Math.min(now - last, 250);
      last = now;
      setClock(({ t, cycle }) => (t + dt >= CYCLE_MS ? { t: t + dt - CYCLE_MS, cycle: cycle + 1 } : { t: t + dt, cycle }));
    }, TICK_MS);
    return () => clearInterval(id);
  }, [running]);

  return { t: reduced ? FINAL_MS : clock.t, cycle: reduced ? 0 : clock.cycle, reduced };
}
