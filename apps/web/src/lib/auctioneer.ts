import "server-only";
import { redirect } from "next/navigation";
import { cache } from "react";
import { api, type ApiResult } from "./api";
import type { Me } from "./api-types";
import { getToken } from "./session";

/**
 * Camada de acesso a dados do painel. Toda leitura confere a sessão na API.
 * Sessão vencida ou conta suspensa vão para /sair, que apaga o cookie (não dá para apagar durante a renderização).
 */
export async function panelApi<T>(
  path: string,
  init: { method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE"; body?: unknown } = {},
): Promise<ApiResult<T>> {
  const token = await getToken();
  if (!token) redirect("/entrar");

  const result = await api<T>(path, { ...init, token });
  if (!result.ok && result.status === 401) redirect("/sair?motivo=expirou");
  if (!result.ok && result.status === 403) redirect("/sair?motivo=suspensa");
  return result;
}

/** Quem está logado. Deduplicado por requisição. */
export const getMe = cache(async (): Promise<Me> => {
  const result = await panelApi<Me>("/auctioneer/me");
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar sua conta.");
  return result.data;
});
