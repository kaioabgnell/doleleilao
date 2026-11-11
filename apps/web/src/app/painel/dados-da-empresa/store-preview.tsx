"use client";

import { useEffect, useMemo, useRef, useState, type CSSProperties } from "react";
import { Heart, Monitor, Smartphone } from "lucide-react";
import { resolveStoreTheme } from "@doleleilao/design-tokens";
import { StoreHeaderLayout } from "@/components/store/templates/header";
import { StoreHomeLayout } from "@/components/store/templates/home";
import { sample } from "@/components/store/templates/sample";
import { templateIllustrations } from "@/components/store/templates/illustrations";
import type { ThemeChoice } from "./theme";

const DEVICES = {
  mobile: { width: 390, height: 760, label: "Celular", icon: Smartphone },
  desktop: { width: 1280, height: 820, label: "Computador", icon: Monitor },
} as const;

type Device = keyof typeof DEVICES;

/**
 * Pré-visualização da loja (RF-B09) com os componentes reais do modelo escolhido, numa moldura do
 * tamanho de um celular (390 px) ou de um computador (1.280 px), reduzida para caber no painel.
 * As cores e a fonte vêm das variáveis --store-*; é só para ver (sem foco e sem cliques dentro).
 */
export function StorePreview({ theme, displayName, logoUrl = null }: { theme: ThemeChoice | null; displayName: string; logoUrl?: string | null }) {
  const [device, setDevice] = useState<Device>("mobile");
  const [startsAt] = useState(() => new Date(Date.now() + 26 * 3_600_000).toISOString());
  const data = useMemo(() => (theme ? sample(theme.template, startsAt) : null), [theme, startsAt]);
  const outer = useRef<HTMLDivElement>(null);
  const inner = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(1);
  const [contentHeight, setContentHeight] = useState(0);
  const frame = DEVICES[device];

  useEffect(() => {
    const el = outer.current;
    const content = inner.current;
    if (!el || !content) return;
    const observer = new ResizeObserver(() => {
      setScale(Math.min(1, el.clientWidth / frame.width));
      setContentHeight(content.offsetHeight);
    });
    observer.observe(el);
    observer.observe(content);
    return () => observer.disconnect();
  }, [frame.width, theme]);

  if (!theme || !data) {
    return (
      <div className="flex min-h-64 items-center justify-center rounded-card border border-dashed border-dl-line bg-dl-page p-6 text-center text-sm text-dl-ink-muted">
        Escolha um modelo para ver como sua loja fica.
      </div>
    );
  }

  const vars = resolveStoreTheme(theme) as CSSProperties;
  const illustrations = templateIllustrations[theme.template];
  const art = (id: string) => {
    const Art = Number(id.slice(1)) % 2 === 0 ? illustrations.featured : illustrations.next;
    return <Art />;
  };

  return (
    <div className="flex flex-col gap-3">
      <div role="radiogroup" aria-label="Ver como fica no" className="inline-flex w-fit rounded-full bg-dl-page p-1 ring-1 ring-dl-line">
        {(Object.keys(DEVICES) as Device[]).map((key) => {
          const Icon = DEVICES[key].icon;
          const active = device === key;
          return (
            <button
              key={key}
              type="button"
              role="radio"
              aria-checked={active}
              onClick={() => setDevice(key)}
              className={`inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-dl-copper ${active ? "bg-dl-card text-dl-ink shadow-sm" : "text-dl-ink-muted hover:text-dl-ink"}`}
            >
              <Icon aria-hidden className="size-4" />
              {DEVICES[key].label}
            </button>
          );
        })}
      </div>

      <div
        className={`mx-auto w-full overflow-hidden border border-dl-line bg-dl-card shadow-sm ${device === "mobile" ? "max-w-[24.5rem] rounded-[1.75rem] p-1.5" : "rounded-card"}`}
        aria-label={`Pré-visualização da loja no ${frame.label.toLowerCase()}`}
        role="img"
      >
        {device === "desktop" ? (
          <div aria-hidden className="flex items-center gap-1.5 border-b border-dl-line px-3 py-2">
            <span className="size-2.5 rounded-full bg-dl-line" />
            <span className="size-2.5 rounded-full bg-dl-line" />
            <span className="size-2.5 rounded-full bg-dl-line" />
            <span className="ml-3 truncate rounded-full bg-dl-page px-3 py-0.5 text-[11px] text-dl-ink-muted">doleleilao.com.br/sua-loja</span>
          </div>
        ) : null}
        <div ref={outer} className={`relative overflow-x-hidden overflow-y-auto overscroll-contain ${device === "mobile" ? "rounded-[1.4rem]" : ""}`} style={{ height: frame.height * scale }}>
          <div style={{ height: contentHeight * scale }}>
            <div
              ref={inner}
              inert
              aria-hidden
              style={{ ...vars, width: frame.width, transform: `scale(${scale})`, transformOrigin: "top left" }}
              className="@container pointer-events-none flex flex-col bg-store-bg text-store-text select-none"
            >
              <StoreHeaderLayout
                template={theme.template}
                slug="sua-loja"
                name={displayName}
                logoUrl={logoUrl}
                preview
                categories={data.categories}
                account={
                  <span className="flex items-center gap-1 text-sm font-medium">
                    <span className="px-3">Entrar</span>
                    <span className="hidden rounded-field bg-store-primary px-4 py-2.5 font-semibold text-store-on-primary @xl:inline">Cadastre-se</span>
                  </span>
                }
              />
              <StoreHomeLayout
                template={theme.template}
                slug="sua-loja"
                name={displayName}
                banners={[]}
                categories={data.categories}
                featured={data.featured}
                others={data.others}
                products={data.products}
                q=""
                pagination={null}
                favorite={() => (
                  <span className="flex size-10 items-center justify-center rounded-full bg-store-surface/90 text-store-text-muted">
                    <Heart className="size-5" />
                  </span>
                )}
                art={art}
                preview
              />
              <p className="border-t border-store-border px-4 py-4 text-center text-xs text-store-text-muted">
                desenvolvido por <span className="text-store-link">Doleleilao.com.br</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
