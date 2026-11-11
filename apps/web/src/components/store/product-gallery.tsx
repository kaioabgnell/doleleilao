"use client";

import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, Pause, Play } from "lucide-react";
import type { ProductPhoto } from "@/lib/api-types";

interface Props {
  photos: ProductPhoto[];
  title: string;
  /** Miniaturas embaixo (padrão) ou, a partir do tamanho de computador, na lateral (modelo Eletrônicos). */
  thumbs?: "below" | "side";
  /** Carrossel: troca sozinho a cada `autoplayMs` (para no mouse, no foco, com "reduzir movimento" ou no botão de pausa). */
  autoplayMs?: number;
  /** Setas sobre a foto para passar as fotos. */
  arrows?: boolean;
}

/** Galeria do produto (RF-E02): foto grande (zoom 1600 px, AVIF/WebP) e miniaturas. */
export function ProductGallery({ photos, title, thumbs = "below", autoplayMs, arrows = false }: Props) {
  const ready = photos.filter((p) => p.status === "ready" && p.zoom.webp);
  const [current, setCurrent] = useState(0);
  const [hovering, setHovering] = useState(false);
  const [stopped, setStopped] = useState(false);
  const touchX = useRef<number | null>(null);
  const count = ready.length;
  const index = count > 0 ? Math.min(current, count - 1) : 0;
  const photo = ready[index];
  const carousel = count > 1 && (arrows || !!autoplayMs);
  const playing = !!autoplayMs && count > 1 && !hovering && !stopped;

  // Avança sozinho; cada troca (inclusive manual) reinicia a contagem.
  useEffect(() => {
    if (!playing || !autoplayMs) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const timer = setTimeout(() => {
      if (document.visibilityState === "visible") setCurrent((i) => (i + 1) % count);
    }, autoplayMs);
    return () => clearTimeout(timer);
  }, [playing, autoplayMs, count, index]);

  // As próximas fotos já ficam baixadas, para a troca não piscar.
  const urls = carousel ? ready.map((p) => p.zoom.webp!).join("|") : "";
  useEffect(() => {
    if (!urls) return;
    for (const url of urls.split("|")) new Image().src = url;
  }, [urls]);

  if (!photo) return <div className="aspect-square w-full rounded-card bg-store-border/40" />;
  const go = (step: number) => setCurrent((index + step + count) % count);

  return (
    <div className={`flex flex-col gap-3 ${thumbs === "side" ? "@5xl:flex-row-reverse" : ""}`}>
      <div
        className="relative min-w-0 flex-1"
        aria-roledescription={carousel ? "carrossel" : undefined}
        aria-label={carousel ? `Fotos de ${title}` : undefined}
        onMouseEnter={() => setHovering(true)}
        onMouseLeave={() => setHovering(false)}
        onFocus={() => setHovering(true)}
        onBlur={() => setHovering(false)}
        onTouchStart={(e) => {
          touchX.current = e.touches[0]?.clientX ?? null;
        }}
        onTouchEnd={(e) => {
          const start = touchX.current;
          const end = e.changedTouches[0]?.clientX;
          touchX.current = null;
          if (!carousel || start === null || end === undefined || Math.abs(end - start) < 40) return;
          go(end < start ? 1 : -1);
        }}
      >
        <picture key={photo.id} className="block motion-safe:animate-[dl-fade-in_280ms_ease-out]">
          {photo.zoom.avif ? <source type="image/avif" srcSet={photo.zoom.avif} /> : null}
          <img
            src={photo.zoom.webp!}
            alt={`${title}, foto ${index + 1} de ${count}`}
            width={photo.width ?? 1600}
            height={photo.height ?? 1600}
            fetchPriority={index === 0 ? "high" : undefined}
            className="aspect-square w-full rounded-card bg-store-surface object-contain"
          />
        </picture>

        {carousel && arrows ? (
          <>
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Foto anterior"
              className="absolute top-1/2 left-2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-md ring-1 ring-store-border transition-transform hover:scale-105 focus-visible:outline-2 focus-visible:outline-store-primary active:scale-95 motion-reduce:transition-none"
            >
              <ChevronLeft aria-hidden className="size-6" />
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Próxima foto"
              className="absolute top-1/2 right-2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-md ring-1 ring-store-border transition-transform hover:scale-105 focus-visible:outline-2 focus-visible:outline-store-primary active:scale-95 motion-reduce:transition-none"
            >
              <ChevronRight aria-hidden className="size-6" />
            </button>
          </>
        ) : null}

        {carousel ? (
          <div className="absolute inset-x-0 bottom-2 flex items-center justify-center gap-2">
            <span className="flex items-center gap-1.5 rounded-full bg-store-surface/90 px-2.5 py-1.5 shadow-sm ring-1 ring-store-border">
              {ready.map((p, i) => (
                <span key={p.id} aria-hidden className={`h-1.5 rounded-full transition-all duration-200 motion-reduce:transition-none ${i === index ? "w-4 bg-store-primary" : "w-1.5 bg-store-border"}`} />
              ))}
            </span>
            {autoplayMs ? (
              <button
                type="button"
                onClick={() => setStopped((v) => !v)}
                aria-label={stopped ? "Retomar a troca automática das fotos" : "Pausar a troca automática das fotos"}
                aria-pressed={stopped}
                className="flex size-8 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-sm ring-1 ring-store-border focus-visible:outline-2 focus-visible:outline-store-primary"
              >
                {stopped ? <Play aria-hidden className="size-3.5" /> : <Pause aria-hidden className="size-3.5" />}
              </button>
            ) : null}
          </div>
        ) : null}
      </div>

      {count > 1 ? (
        <ul className={`flex gap-2 overflow-x-auto ${thumbs === "side" ? "@5xl:max-h-[32rem] @5xl:flex-col @5xl:overflow-y-auto" : ""}`} aria-label="Fotos">
          {ready.map((p, i) => (
            <li key={p.id} className="shrink-0">
              <button
                type="button"
                onClick={() => setCurrent(i)}
                aria-label={`Ver foto ${i + 1}`}
                aria-current={i === index}
                className={`overflow-hidden rounded-field border-2 ${i === index ? "border-store-primary" : "border-transparent"} focus-visible:outline-2 focus-visible:outline-store-primary`}
              >
                {/* eslint-disable-next-line @next/next/no-img-element -- miniatura */}
                <img src={p.thumb ?? ""} alt="" width={64} height={64} loading="lazy" className="size-16 object-cover" />
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
