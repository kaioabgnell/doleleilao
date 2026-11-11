import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import type { PublicStorefront } from "./api-types";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

export type StorefrontLookup =
  | { kind: "found"; storefront: PublicStorefront }
  | { kind: "moved"; slug: string }
  | { kind: "not-found" };

/**
 * Dados da loja pública, em cache com a tag `storefront:{slug}`. A API pede o descarte da tag
 * quando o leiloeiro salva algo (POST /api/revalidate). Sem dados do visitante aqui dentro.
 */
export async function getStorefront(slug: string): Promise<StorefrontLookup> {
  "use cache";
  cacheTag(`storefront:${slug}`);
  cacheLife("hours");

  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}`, {
    headers: { Accept: "application/json" },
    redirect: "manual",
  });

  if (response.status === 301) {
    const body = (await response.json()) as { redirect_to?: string };
    return body.redirect_to ? { kind: "moved", slug: body.redirect_to } : { kind: "not-found" };
  }
  if (response.status === 404) return { kind: "not-found" };
  if (!response.ok) throw new Error(`API da loja respondeu ${response.status}`);

  return { kind: "found", storefront: (await response.json()) as PublicStorefront };
}

/** Vitrine (página de produtos), no cache da loja. */
export async function getProducts(
  slug: string,
  filters: { category?: string; q?: string; page?: number } = {},
): Promise<import("./api-types").ProductPage | null> {
  "use cache";
  cacheTag(`storefront:${slug}`);
  cacheLife("hours");

  const query = new URLSearchParams();
  if (filters.category) query.set("category", filters.category);
  if (filters.q) query.set("q", filters.q);
  if (filters.page && filters.page > 1) query.set("page", String(filters.page));
  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/products?${query}`, {
    headers: { Accept: "application/json" },
  });
  if (response.status === 404) return null;
  if (!response.ok) throw new Error(`API respondeu ${response.status}`);
  return (await response.json()) as import("./api-types").ProductPage;
}

export async function getProduct(slug: string, productSlug: string): Promise<import("./api-types").Product | null> {
  "use cache";
  cacheTag(`storefront:${slug}`);
  cacheLife("hours");

  const response = await fetch(
    `${API_URL}/storefronts/${encodeURIComponent(slug)}/products/${encodeURIComponent(productSlug)}`,
    { headers: { Accept: "application/json" } },
  );
  if (response.status === 404) return null;
  if (!response.ok) throw new Error(`API respondeu ${response.status}`);
  return (await response.json()) as import("./api-types").Product;
}

export interface StoreTermsPage {
  store: string;
  terms: string;
  updated_at: string | null;
}

/** Termos de uso da loja (regras do leilão), no cache da loja: a API descarta a tag ao salvar. */
export async function getStoreTerms(slug: string): Promise<StoreTermsPage | null> {
  "use cache";
  cacheTag(`storefront:${slug}`);
  cacheLife("hours");

  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/terms`, { headers: { Accept: "application/json" }, redirect: "manual" });
  if (response.status === 404 || response.status === 301) return null;
  if (!response.ok) throw new Error(`API respondeu ${response.status}`);
  return (await response.json()) as StoreTermsPage;
}
