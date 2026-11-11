import type { Metadata } from "next";
import type { CSSProperties } from "react";
import { Suspense } from "react";
import { notFound, permanentRedirect } from "next/navigation";
import {
  resolveStoreTheme,
  type BackgroundKey,
  type StoreColorKey,
  type StoreFontKey,
  type TemplateKey,
} from "@doleleilao/design-tokens";
import { StoreFooter } from "@/components/store/store-footer";
import { StoreHeader } from "@/components/store/store-header";
import { getStorefront } from "@/lib/storefront";

export async function generateMetadata({ params }: LayoutProps<"/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const lookup = await getStorefront(slug.toLowerCase());
  if (lookup.kind !== "found") return { title: "Loja não encontrada" };
  const name = lookup.storefront.display_name;
  return {
    title: { default: `${name} · Leilões online`, template: `%s · ${name}` },
    description: `Leilões online de ${name}, com lances ao vivo.`,
  };
}

/**
 * Loja do leiloeiro (`/{slug}`): o tema dele vira variáveis --store-* neste trecho da página
 * (specs/04 frontend). Modelo "base" provisório; os 3 modelos definitivos entram na Fase 7.
 */
export default function StoreLayout({ children, params }: LayoutProps<"/[slug]">) {
  return (
    <Suspense fallback={<div className="flex-1 animate-pulse bg-dl-page" aria-busy="true" />}>
      <StoreFrame params={params}>{children}</StoreFrame>
    </Suspense>
  );
}

async function StoreFrame({ params, children }: Pick<LayoutProps<"/[slug]">, "params" | "children">) {
  const { slug } = await params;
  const lookup = await getStorefront(slug.toLowerCase());

  if (lookup.kind === "moved") permanentRedirect(`/${lookup.slug}`);
  if (lookup.kind === "not-found") notFound();
  if (slug !== lookup.storefront.slug) permanentRedirect(`/${lookup.storefront.slug}`);

  const { storefront } = lookup;
  const vars = resolveStoreTheme({
    template: storefront.theme.template as TemplateKey,
    primary: storefront.theme.primary_color as StoreColorKey,
    accent: storefront.theme.accent_color as StoreColorKey,
    background: storefront.theme.background as BackgroundKey,
    headingFont: storefront.theme.heading_font as StoreFontKey,
  }) as CSSProperties;

  return (
    <div style={vars} data-template={storefront.theme.template} className="@container flex flex-1 flex-col bg-store-bg text-store-text">
      <StoreHeader storefront={storefront} />
      <div className="flex-1">{children}</div>
      <StoreFooter storefront={storefront} />
    </div>
  );
}
