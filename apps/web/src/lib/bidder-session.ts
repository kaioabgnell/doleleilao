import "server-only";
import { cookies } from "next/headers";
import { cache } from "react";
import { api, type ApiResult } from "./api";
import type { Bidder } from "./api-types";

/**
 * Sessão do participante: cookie httpOnly com caminho `/{slug}`, então só é enviado para a loja
 * onde ele se cadastrou (contas por loja, D-11). O navegador nunca vê o token.
 */
const COOKIE = "dl_participante";

export async function getBidderToken(): Promise<string | null> {
  return (await cookies()).get(COOKIE)?.value ?? null;
}

export async function setBidderSession(slug: string, token: string, expiresAt: string): Promise<void> {
  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: `/${slug}`,
    expires: new Date(expiresAt),
  });
}

export async function clearBidderSession(slug: string): Promise<void> {
  (await cookies()).set(COOKIE, "", { path: `/${slug}`, maxAge: 0 });
}

export async function bidderApi<T>(
  path: string,
  init: { method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE"; body?: unknown } = {},
): Promise<ApiResult<T>> {
  return api<T>(path, { ...init, token: await getBidderToken() });
}

/** Participante logado nesta loja, ou null. Deduplicado por requisição. */
export const getBidder = cache(async (): Promise<Bidder | null> => {
  if (!(await getBidderToken())) return null;
  const result = await bidderApi<Bidder>("/bidders/me");
  return result.ok ? result.data : null;
});

/** IDs dos produtos favoritos (para os corações da vitrine). */
export const getFavoriteIds = cache(async (): Promise<string[]> => {
  if (!(await getBidderToken())) return [];
  const result = await bidderApi<{ ids: string[] }>("/bidders/me/favorites");
  return result.ok ? result.data.ids : [];
});
