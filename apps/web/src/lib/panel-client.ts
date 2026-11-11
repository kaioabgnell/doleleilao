/** Chamadas do painel feitas no navegador, via BFF (/api/painel/conteudo/…). */
import type { FieldErrors } from "./api-types";

export interface ClientResult<T> {
  ok: boolean;
  status: number;
  data?: T;
  errors?: FieldErrors;
  message?: string;
}

export async function panelRequest<T>(
  path: string,
  init: { method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE"; body?: FormData | object } = {},
): Promise<ClientResult<T>> {
  const isForm = init.body instanceof FormData;
  try {
    const res = await fetch(`/api/painel/conteudo/${path}`, {
      method: init.method ?? "GET",
      headers: init.body && !isForm ? { "Content-Type": "application/json" } : undefined,
      body: init.body ? (isForm ? (init.body as FormData) : JSON.stringify(init.body)) : undefined,
    });
    if (res.status === 401) {
      // Quem chamou recarrega a página (router.refresh), e a camada de dados leva ao login.
      return { ok: false, status: 401, message: "Sua sessão terminou. Entre de novo." };
    }
    if (res.status === 204) return { ok: true, status: 204 };
    const json = (await res.json().catch(() => null)) as (T & { errors?: FieldErrors; detail?: string }) | null;
    if (res.ok) return { ok: true, status: res.status, data: json as T };
    return {
      ok: false,
      status: res.status,
      errors: json?.errors,
      message: res.status === 413 ? "Arquivo grande demais." : (json?.detail ?? "Algo deu errado. Tente de novo."),
    };
  } catch {
    return { ok: false, status: 0, message: "Sem conexão. Confira a internet e tente de novo." };
  }
}
