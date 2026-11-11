"use client";

import { useSyncExternalStore, type CSSProperties } from "react";
import { Heart } from "lucide-react";
import { resolveStoreTheme, templates, type TemplateKey } from "@doleleilao/design-tokens";
import { StoreHeaderLayout } from "@/components/store/templates/header";
import { StoreHomeLayout } from "@/components/store/templates/home";
import { templateIllustrations } from "@/components/store/templates/illustrations";
import { sample } from "@/components/store/templates/sample";

const NAMES: Record<TemplateKey, string> = { watches: "Relojoaria Exemplo", fashion: "Brechó Exemplo", electronics: "Tech Exemplo" };
const noop = () => () => {};
let startsAt: string | null = null;
const getStartsAt = () => (startsAt ??= new Date(Date.now() + 26 * 3_600_000).toISOString());

export function ModelPreview({ template }: { template: TemplateKey }) {
  const when = useSyncExternalStore(noop, getStartsAt, () => "2026-12-01T23:00:00.000Z");
  const data = sample(template, when);
  const illustrations = templateIllustrations[template];
  const art = (id: string) => {
    const Art = Number(id.slice(1)) % 2 === 0 ? illustrations.featured : illustrations.next;
    return <Art />;
  };
  return (
    <div style={resolveStoreTheme({ template, ...templates[template].defaults }) as CSSProperties} className="@container flex min-h-dvh flex-col bg-store-bg text-store-text">
      <StoreHeaderLayout template={template} slug="sua-loja" name={NAMES[template]} logoUrl={null} preview categories={data.categories} account={<span className="px-3 text-sm font-medium">Entrar</span>} />
      <StoreHomeLayout
        template={template}
        slug="sua-loja"
        name={NAMES[template]}
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
    </div>
  );
}
