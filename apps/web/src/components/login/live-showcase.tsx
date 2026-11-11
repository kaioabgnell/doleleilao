"use client";

import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from "react";
import { Pause, Play } from "lucide-react";
import { AutoBidCard, LiveDot, PanelFrame, PhoneFrame, WinToast } from "./frames";
import { DIM_FROM_MS, frameAt } from "./story";
import { useStoryClock } from "./use-story-clock";

/**
 * Palco da tela de entrada (D-53): a história de um item no pregão, do lance pelo celular até o
 * aviso de arrematação, desenhada com as telas reais. Uma história só (um relógio), com botão de
 * pausa; com "reduzir movimento" fica parada no quadro final.
 */
export function LiveShowcase() {
  const [paused, setPaused] = useState(false);
  const { t, cycle, reduced } = useStoryClock(paused);
  const frame = frameAt(t);
  const dim = t >= DIM_FROM_MS;
  const celebrate = cycle > 0;

  return (
    <aside aria-labelledby="palco-h" className="p-3 lg:sticky lg:top-0 lg:h-dvh">
      <div
        data-paused={paused || undefined}
        className="dl-stage relative isolate flex h-full flex-col overflow-hidden rounded-[28px] bg-dl-stage text-dl-on-stage ring-1 ring-white/5 ring-inset"
      >
        <div aria-hidden className="dl-stage-glow absolute inset-0 -z-10" />
        <div aria-hidden className="dl-stage-grid absolute inset-0 -z-10" />

        <div className="px-6 pt-8 sm:px-10 sm:pt-10">
          <div className="flex items-center justify-between gap-4">
            <p className="flex animate-rise items-center gap-2.5 font-code text-[12px] tracking-[0.14em] text-dl-on-stage-muted uppercase [animation-delay:120ms]">
              <LiveDot className="bg-dl-lime" />
              Pregão ao vivo
            </p>
            {reduced ? null : (
              <button
                type="button"
                onClick={() => setPaused((p) => !p)}
                aria-pressed={paused}
                aria-label={paused ? "Continuar a demonstração" : "Pausar a demonstração"}
                className="group -my-2 -mr-1.5 flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-dl-lime"
              >
                <span className="flex size-8 items-center justify-center rounded-full bg-dl-on-stage text-dl-stage transition-[scale,background-color] duration-150 group-hover:bg-dl-lime group-active:scale-[.94]">
                  {paused ? <Play className="size-3.5 fill-current" /> : <Pause className="size-3.5 fill-current" />}
                </span>
              </button>
            )}
          </div>
          <h2 id="palco-h" className="mt-4 max-w-[20ch] animate-rise text-[clamp(1.75rem,1.1rem+1.6vw,2.5rem)] leading-[1.08] font-medium tracking-[-0.035em] text-balance [animation-delay:180ms]">
            O pregão ao vivo, do primeiro lance à arrematação
          </h2>
          <p className="mt-3 max-w-[52ch] animate-rise text-[15px] leading-relaxed text-dl-on-stage-muted [animation-delay:240ms]">
            Lances em tempo real, lance automático, prorrogação nos últimos segundos e o vencedor avisado na hora.
          </p>
        </div>

        <div className="@container relative flex min-h-0 flex-1 flex-col px-4 pt-6 pb-6 sm:px-10 sm:pb-10">
          <div
            role="img"
            aria-label="Demonstração do pregão: uma participante dá lance pelo celular, o tempo do item é prorrogado, o lance automático cobre e depois é superado, o leiloeiro encerra a venda pelo painel, o celular mostra Dole 1, 2, 3 e Você é o ganhador, e o sino avisa a arrematação."
            className="relative aspect-[400/440] w-full transition-opacity duration-300 @min-[600px]:aspect-[760/600] lg:aspect-auto lg:min-h-[320px] lg:flex-1"
            style={{ opacity: dim ? 0.5 : 1 }}
          >
            <ScaledCanvas width={760} height={600} className="hidden @min-[600px]:block">
              <div className="absolute top-[34px] left-0">
                <PanelFrame frame={frame} />
              </div>
              <div className="absolute top-[452px] left-[28px]">
                <AutoBidCard frame={frame} />
              </div>
              <div className="absolute top-[150px] left-[548px]">
                <PhoneFrame frame={frame} celebrate={celebrate} />
              </div>
              <div className="absolute top-0 left-[404px]">
                <WinToast frame={frame} />
              </div>
            </ScaledCanvas>

            <ScaledCanvas width={400} height={440} className="@min-[600px]:hidden">
              <div className="absolute top-0 left-0">
                <PhoneFrame frame={frame} celebrate={celebrate} />
              </div>
              <div className="absolute top-[40px] left-[204px]">
                <AutoBidCard frame={frame} narrow />
              </div>
              <div className="absolute top-[262px] left-[204px]">
                <WinToast frame={frame} narrow />
              </div>
            </ScaledCanvas>
          </div>
        </div>

      </div>
    </aside>
  );
}

/**
 * Desenha os quadros num tamanho fixo e escala o conjunto para caber na área (sem cortar). A escala
 * vem de um ResizeObserver; antes dele, o CSS usa um valor aproximado por largura (.dl-canvas).
 */
function ScaledCanvas({ width, height, className = "", children }: { width: number; height: number; className?: string; children: ReactNode }) {
  const outer = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState<number | null>(null);

  useEffect(() => {
    const el = outer.current;
    if (!el) return;
    const observer = new ResizeObserver(([entry]) => {
      if (!entry) return;
      const { width: w, height: h } = entry.contentRect;
      if (w === 0 || h === 0) return;
      setScale(Math.min(w / width, h / height, 1.2));
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, [width, height]);

  return (
    <div ref={outer} className={`absolute inset-0 ${className}`}>
      <div className="dl-canvas" style={{ width, height, ...(scale ? ({ "--s": scale } as CSSProperties) : {}) }}>
        <div className="relative size-full animate-settle">{children}</div>
      </div>
    </div>
  );
}
