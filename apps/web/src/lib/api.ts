import "server-only";
import { headers } from "next/headers";
import type { Problem } from "./api-types";

/** A API Laravel. Em produção roda na mesma máquina (specs/04 hospedagem). */
const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

export type ApiResult<T> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; problem: Problem };

interface ApiInit {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  body?: unknown;
  token?: string | null;
}

/**
 * Chamada do BFF para a API. Repassa o IP do visitante (os limites de requisição da API
 * contam por IP) e nunca expõe o token ao navegador.
 */
export async function api<T>(path: string, init: ApiInit = {}): Promise<ApiResult<T>> {
  const requestHeaders = new Headers({ Accept: "application/json" });
  if (init.body !== undefined) requestHeaders.set("Content-Type", "application/json");
  if (init.token) requestHeaders.set("Authorization", `Bearer ${init.token}`);
  const ip = await visitorIp();
  if (ip) requestHeaders.set("X-Forwarded-For", ip);

  let response: Response;
  try {
    response = await fetch(`${API_URL}${path}`, {
      method: init.method ?? "GET",
      headers: requestHeaders,
      body: init.body === undefined ? undefined : JSON.stringify(init.body),
      cache: "no-store",
    });
  } catch {
    return {
      ok: false,
      status: 503,
      problem: {
        title: "Serviço indisponível",
        status: 503,
        detail: "Não conseguimos falar com o servidor. Tente de novo em instantes.",
      },
    };
  }

  if (response.status === 204) return { ok: true, status: 204, data: undefined as T };

  const json: unknown = await response.json().catch(() => null);
  if (response.ok) return { ok: true, status: response.status, data: json as T };

  const problem =
    json && typeof json === "object" && "status" in json
      ? (json as Problem)
      : { title: "Erro", status: response.status, detail: "Algo deu errado. Tente de novo." };
  return { ok: false, status: response.status, problem };
}

async function visitorIp(): Promise<string | null> {
  try {
    const h = await headers();
    return (
      h.get("cf-connecting-ip") ??
      h.get("x-forwarded-for")?.split(",")[0]?.trim() ??
      h.get("x-real-ip") ??
      null
    );
  } catch {
    return null; // fora de uma requisição (build)
  }
}
