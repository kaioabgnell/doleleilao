"use server";

import { redirect } from "next/navigation";
import { api } from "@/lib/api";
import type { FieldErrors, SessionResponse } from "@/lib/api-types";
import { setSession } from "@/lib/session";

export interface FormState {
  errors?: FieldErrors;
  message?: string;
  success?: string;
  values?: Record<string, string>;
}

function text(form: FormData, key: string): string {
  const value = form.get(key);
  return typeof value === "string" ? value : "";
}

/** Só aceita voltar para dentro do painel (evita redirecionamento aberto). */
function safeReturn(value: string): string {
  return value.startsWith("/painel") && !value.startsWith("//") ? value : "/painel";
}

export async function register(_: FormState, form: FormData): Promise<FormState> {
  const body = {
    name: text(form, "name"),
    email: text(form, "email"),
    phone: text(form, "phone"),
    password: text(form, "password"),
    document_type: text(form, "document_type"),
    document: text(form, "document"),
    company_name: text(form, "company_name"),
    slug: text(form, "slug"),
    display_name: text(form, "display_name") || null,
    accept_terms: form.get("accept_terms") === "on",
  };

  const result = await api<SessionResponse>("/auctioneers", { method: "POST", body });
  if (!result.ok) {
    // Devolve o que foi digitado (menos a senha) para o formulário não perder os dados.
    return {
      errors: result.problem.errors,
      message: result.problem.errors ? "Confira os campos destacados." : result.problem.detail,
      values: {
        name: body.name,
        email: body.email,
        phone: body.phone,
        document_type: body.document_type,
        document: body.document,
        company_name: body.company_name,
        slug: body.slug,
        display_name: body.display_name ?? "",
        accept_terms: String(body.accept_terms),
      },
    };
  }

  await setSession(result.data.token, result.data.expires_at);
  redirect("/painel?bem-vindo=1");
}

export async function login(_: FormState, form: FormData): Promise<FormState> {
  const email = text(form, "email");
  const result = await api<SessionResponse>("/auctioneer/sessions", {
    method: "POST",
    body: { email, password: text(form, "password") },
  });

  if (!result.ok) {
    return {
      errors: result.problem.errors,
      message: result.problem.errors ? undefined : result.problem.detail,
      values: { email },
    };
  }

  await setSession(result.data.token, result.data.expires_at);
  redirect(safeReturn(text(form, "voltar")));
}

export async function forgotPassword(_: FormState, form: FormData): Promise<FormState> {
  const email = text(form, "email");
  const result = await api<{ message: string }>("/auctioneer/password/forgot", { method: "POST", body: { email } });

  if (!result.ok) {
    return { errors: result.problem.errors, message: result.problem.detail, values: { email } };
  }
  return { success: result.data.message, values: { email } };
}

export async function resetPassword(_: FormState, form: FormData): Promise<FormState> {
  const result = await api<{ message: string }>("/auctioneer/password/reset", {
    method: "POST",
    body: { token: text(form, "token"), email: text(form, "email"), password: text(form, "password") },
  });

  if (!result.ok) {
    return { errors: result.problem.errors, message: result.problem.errors?.token?.[0] ?? result.problem.detail };
  }
  redirect("/entrar?senha=alterada");
}
