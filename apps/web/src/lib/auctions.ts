import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import type { Auction, PublicAuction } from "./api-types";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** Lista de leilões da loja, no cache da loja (a API descarta ao agendar, começar, encerrar). */
export async function getAuctions(slug: string, status: "upcoming" | "finished"): Promise<Auction[]> {
  "use cache";
  cacheTag(`storefront:${slug}`);
  cacheLife("hours");

  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/auctions?status=${status}`, {
    headers: { Accept: "application/json" },
  });
  if (response.status === 404) return [];
  if (!response.ok) throw new Error(`API respondeu ${response.status}`);
  return ((await response.json()) as { data: Auction[] }).data;
}

/** Leilão com o estado do pregão: sempre fresco (sem cache). */
export async function getAuction(slug: string, auction: string): Promise<PublicAuction | null> {
  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/auctions/${encodeURIComponent(auction)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  if (response.status === 404) return null;
  if (!response.ok) throw new Error(`API respondeu ${response.status}`);
  return (await response.json()) as PublicAuction;
}
