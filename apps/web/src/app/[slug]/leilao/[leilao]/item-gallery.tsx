"use client";

import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, Expand, ImageOff, Pause, Play, X } from "lucide-react";
import type { ProductPhoto } from "@/lib/api-types";

/**
 * Fotos do item no auditório: foto contida (não toma a tela) com as miniaturas na vertical ao lado.
 * Carrossel automático de 4 s (T-35: para no mouse, no foco, na tela cheia, com "reduzir movimento"
 * ou no botão de pausa). Tocar na foto abre a tela cheia, com setas, miniaturas e X para voltar.
 */
export function ItemGallery({ photos, title, autoplayMs = 4000 }: { photos: ProductPhoto[]; title: string; autoplayMs?: number }) {
  const ready = photos.filter((p) => p.status === "ready" && p.zoom.webp);
  const [current, setCurrent] = useState(0);
  const [hovering, setHovering] = useState(false);
  const [stopped, setStopped] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const touchX = useRef<number | null>(null);
  const count = ready.length;
  const index = count > 0 ? Math.min(current, count - 1) : 0;
  const photo = ready[index];
  const playing = count > 1 && !hovering && !stopped && !expanded;

  useEffect(() => {
    if (!playing) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const timer = setTimeout(() => {
      if (document.visibilityState === "visible") setCurrent((i) => (i + 1) % count);
    }, autoplayMs);
    return () => clearTimeout(timer);
  }, [playing, autoplayMs, count, index]);

  // As próximas fotos já ficam baixadas, para a troca não piscar.
  const urls = ready.map((p) => p.zoom.webp!).join("|");
  useEffect(() => {
    if (count < 2) return;
    for (const url of urls.split("|")) new Image().src = url;
  }, [urls, count]);

  if (!photo) {
    return (
      <div className="flex aspect-[4/3] w-full items-center justify-center rounded-card bg-store-bg text-store-text-muted">
        <ImageOff aria-hidden className="size-8" />
      </div>
    );
  }

  const go = (step: number) => setCurrent((index + step + count) % count);
  const swipe = {
    onTouchStart: (e: React.TouchEvent) => {
      touchX.current = e.touches[0]?.clientX ?? null;
    },
    onTouchEnd: (e: React.TouchEvent) => {
      const start = touchX.current;
      const end = e.changedTouches[0]?.clientX;
      touchX.current = null;
      if (count < 2 || start === null || end === undefined || Math.abs(end - start) < 40) return;
      go(end < start ? 1 : -1);
    },
  };

  return (
    <div
      className={`grid gap-2 ${count > 1 ? "grid-cols-[3.5rem_minmax(0,1fr)] sm:grid-cols-[4rem_minmax(0,1fr)]" : ""}`}
      onMouseEnter={() => setHovering(true)}
      onMouseLeave={() => setHovering(false)}
      onFocus={() => setHovering(true)}
      onBlur={() => setHovering(false)}
    >
      {count > 1 ? (
        // A coluna das miniaturas tem a altura da foto e rola por dentro.
        <div className="relative">
          <ul className="absolute inset-0 flex flex-col gap-2 overflow-y-auto overscroll-contain [scrollbar-width:none]" aria-label="Fotos">
            {ready.map((p, i) => (
              <li key={p.id} className="shrink-0">
                <Thumb photo={p} active={i === index} label={`Ver foto ${i + 1}`} onClick={() => setCurrent(i)} />
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <div className="group relative min-w-0" aria-roledescription={count > 1 ? "carrossel" : undefined} aria-label={count > 1 ? `Fotos de ${title}` : undefined} {...swipe}>
        <button
          type="button"
          onClick={() => setExpanded(true)}
          className="block w-full cursor-zoom-in overflow-hidden rounded-card bg-store-surface ring-1 ring-store-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-store-primary"
          aria-label={`Ampliar a foto ${index + 1} de ${count}`}
        >
          <picture key={photo.id} className="block motion-safe:animate-[dl-fade-in_280ms_ease-out]">
            {photo.zoom.avif ? <source type="image/avif" srcSet={photo.zoom.avif} /> : null}
            { }
            <img
              src={photo.zoom.webp!}
              alt={`${title}, foto ${index + 1} de ${count}`}
              width={photo.width ?? 1600}
              height={photo.height ?? 1600}
              fetchPriority={index === 0 ? "high" : undefined}
              className="aspect-square w-full object-contain transition-transform duration-300 ease-ui group-hover:scale-[1.02] motion-reduce:transition-none"
            />
          </picture>
        </button>

        <span aria-hidden className="pointer-events-none absolute top-2 right-2 flex size-9 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-sm ring-1 ring-store-border">
          <Expand className="size-4" />
        </span>

        {count > 1 ? (
          <div className="pointer-events-none absolute inset-x-0 bottom-2 flex items-center justify-center gap-2">
            <span className="flex items-center gap-1.5 rounded-full bg-store-surface/90 px-2.5 py-1.5 shadow-sm ring-1 ring-store-border">
              {ready.map((p, i) => (
                <span key={p.id} aria-hidden className={`h-1.5 rounded-full transition-all duration-200 motion-reduce:transition-none ${i === index ? "w-4 bg-store-primary" : "w-1.5 bg-store-border"}`} />
              ))}
            </span>
            <button
              type="button"
              onClick={() => setStopped((v) => !v)}
              aria-label={stopped ? "Retomar a troca automática das fotos" : "Pausar a troca automática das fotos"}
              aria-pressed={stopped}
              className="pointer-events-auto flex size-9 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-sm ring-1 ring-store-border focus-visible:outline-2 focus-visible:outline-store-primary"
            >
              {stopped ? <Play aria-hidden className="size-3.5" /> : <Pause aria-hidden className="size-3.5" />}
            </button>
          </div>
        ) : null}
      </div>

      {expanded ? <Lightbox photos={ready} index={index} title={title} onIndex={setCurrent} onClose={() => setExpanded(false)} /> : null}
    </div>
  );
}

function Thumb({ photo, active, label, onClick, dark = false }: { photo: ProductPhoto; active: boolean; label: string; onClick: () => void; dark?: boolean }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      aria-current={active}
      className={`block overflow-hidden rounded-field border-2 transition-[border-color,opacity] duration-150 focus-visible:outline-2 focus-visible:outline-store-primary ${
        active ? "border-store-primary" : dark ? "border-transparent opacity-60 hover:opacity-100" : "border-store-border hover:border-store-text-muted"
      }`}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- miniatura */}
      <img src={photo.thumb ?? photo.zoom.webp ?? ""} alt="" width={64} height={64} loading="lazy" className="aspect-square w-full bg-store-surface object-cover" />
    </button>
  );
}

/** Foto em tela cheia: setas e teclado passam as fotos; X, Esc ou tocar fora voltam ao auditório. */
function Lightbox({ photos, index, title, onIndex, onClose }: { photos: ProductPhoto[]; index: number; title: string; onIndex: (i: number) => void; onClose: () => void }) {
  const ref = useRef<HTMLDialogElement>(null);
  const touchX = useRef<number | null>(null);
  const count = photos.length;
  const photo = photos[index];
  const go = (step: number) => onIndex((index + step + count) % count);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const opener = document.activeElement as HTMLElement | null;
    dialog.showModal();
    const root = document.documentElement;
    const overflow = root.style.overflow;
    root.style.overflow = "hidden";
    return () => {
      root.style.overflow = overflow;
      opener?.focus();
    };
  }, []);

  return (
    <dialog
      ref={ref}
      aria-label={`Fotos de ${title}`}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      onKeyDown={(e) => {
        if (count < 2) return;
        if (e.key === "ArrowRight") go(1);
        if (e.key === "ArrowLeft") go(-1);
      }}
      className="fixed inset-0 m-0 flex h-dvh max-h-none w-screen max-w-none flex-col bg-transparent p-0 text-white backdrop:bg-black/90 motion-safe:animate-[dl-fade-in_180ms_ease-out]"
    >
      <div className="flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2">
        <p className="min-w-0 truncate text-sm font-medium text-white/80">
          {title} <span className="tabular text-white/60">· {index + 1} de {count}</span>
        </p>
        <button
          type="button"
          onClick={onClose}
          autoFocus
          className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white"
          aria-label="Fechar e voltar ao leilão"
        >
          <X aria-hidden className="size-6" />
        </button>
      </div>

      <div
        className="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-16"
        onClick={(e) => {
          if (e.target === e.currentTarget) onClose();
        }}
        onTouchStart={(e) => {
          touchX.current = e.touches[0]?.clientX ?? null;
        }}
        onTouchEnd={(e) => {
          const start = touchX.current;
          const end = e.changedTouches[0]?.clientX;
          touchX.current = null;
          if (count < 2 || start === null || end === undefined || Math.abs(end - start) < 40) return;
          go(end < start ? 1 : -1);
        }}
      >
        <picture key={photo.id} className="contents">
          {photo.zoom.avif ? <source type="image/avif" srcSet={photo.zoom.avif} /> : null}
          { }
          <img src={photo.zoom.webp!} alt={`${title}, foto ${index + 1} de ${count}`} className="max-h-full max-w-full rounded-field object-contain motion-safe:animate-[dl-fade-in_200ms_ease-out]" />
        </picture>
        {count > 1 ? (
          <>
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Foto anterior"
              className="absolute top-1/2 left-2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white sm:left-4"
            >
              <ChevronLeft aria-hidden className="size-7" />
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Próxima foto"
              className="absolute top-1/2 right-2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-white sm:right-4"
            >
              <ChevronRight aria-hidden className="size-7" />
            </button>
          </>
        ) : null}
      </div>

      {count > 1 ? (
        <ul className="flex justify-center gap-2 overflow-x-auto px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]" aria-label="Fotos">
          {photos.map((p, i) => (
            <li key={p.id} className="w-14 shrink-0 sm:w-16">
              <Thumb photo={p} active={i === index} label={`Ver foto ${i + 1}`} onClick={() => onIndex(i)} dark />
            </li>
          ))}
        </ul>
      ) : null}
    </dialog>
  );
}
