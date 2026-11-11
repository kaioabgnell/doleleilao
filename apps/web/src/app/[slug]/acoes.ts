"use server";

import { refresh } from "next/cache";
import { redirect } from "next/navigation";
import { addressFromForm } from "@/lib/address";
import { api } from "@/lib/api";
import type { BidderSession, FieldErrors } from "@/lib/api-types";
import { bidderApi, clearBidderSession, setBidderSession } from "@/lib/bidder-session";

export interface StoreFormState {
  errors?: FieldErrors;
  message?: string;
  success?: string;
  values?: Record<string, string>;
}

const text = (form: FormData, key: string) => {
  const v = form.get(key);
  return typeof v === "string" ? v.trim() : "";
};
const slugOf = (form: FormData) => text(form, "slug").replace(/[^a-z0-9-]/g, "");

/** Volta só para dentro da própria loja. */
function backTo(slug: string, voltar: string): string {
  return voltar.startsWith(`/${slug}`) && !voltar.startsWith("//") ? voltar : `/${slug}/minha-conta`;
}

/** Favorito pedido antes do login (RF-E05): aplicado logo depois de entrar. */
async function applyPendingFavorite(form: FormData) {
  const product = text(form, "favoritar");
  if (/^[0-9a-z]{26}$/.test(product)) await bidderApi(`/bidders/me/favorites/${product}`, { method: "PUT" });
}

export async function registerBidder(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const slug = slugOf(form);
  const body = {
    name: text(form, "name"),
    email: text(form, "email"),
    phone: text(form, "phone"),
    cpf: text(form, "cpf"),
    password: typeof form.get("password") === "string" ? String(form.get("password")) : "",
    accept_terms: form.get("accept_terms") === "on",
  };
  const result = await api<BidderSession>(`/storefronts/${slug}/bidders`, { method: "POST", body });
  if (!result.ok) {
    return {
      errors: result.problem.errors,
      message: result.problem.errors ? "Confira os campos destacados." : result.problem.detail,
      values: { name: body.name, email: body.email, phone: body.phone, cpf: body.cpf },
    };
  }
  await setBidderSession(slug, result.data.token, result.data.expires_at);
  // O token acabou de ser gravado no cookie: o favorito pendente usa a chamada direta.
  const product = text(form, "favoritar");
  if (/^[0-9a-z]{26}$/.test(product)) await api(`/bidders/me/favorites/${product}`, { method: "PUT", token: result.data.token });
  redirect(`${backTo(slug, text(form, "voltar"))}${text(form, "voltar").includes("?") ? "&" : "?"}bem-vindo=1`);
}

export async function loginBidder(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const slug = slugOf(form);
  const email = text(form, "email");
  const result = await api<BidderSession>(`/storefronts/${slug}/sessions`, {
    method: "POST",
    body: { email, password: String(form.get("password") ?? "") },
  });
  if (!result.ok) return { errors: result.problem.errors, message: result.problem.errors ? undefined : result.problem.detail, values: { email } };

  await setBidderSession(slug, result.data.token, result.data.expires_at);
  const product = text(form, "favoritar");
  if (/^[0-9a-z]{26}$/.test(product)) await api(`/bidders/me/favorites/${product}`, { method: "PUT", token: result.data.token });
  redirect(backTo(slug, text(form, "voltar")));
}

export async function logoutBidder(form: FormData): Promise<void> {
  const slug = slugOf(form);
  await bidderApi("/bidders/me/sessions/current", { method: "DELETE" });
  await clearBidderSession(slug);
  redirect(`/${slug}`);
}

export async function forgotBidderPassword(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const email = text(form, "email");
  const result = await api<{ message: string }>(`/storefronts/${slugOf(form)}/password/forgot`, { method: "POST", body: { email } });
  return result.ok ? { success: result.data.message } : { errors: result.problem.errors, message: result.problem.detail, values: { email } };
}

export async function resetBidderPassword(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const slug = slugOf(form);
  const result = await api(`/storefronts/${slug}/password/reset`, {
    method: "POST",
    body: { email: text(form, "email"), token: text(form, "token"), password: String(form.get("password") ?? "") },
  });
  if (!result.ok) return { errors: result.problem.errors, message: result.problem.errors?.token?.[0] ?? result.problem.detail };
  redirect(`/${slug}/entrar?senha=alterada`);
}

export async function updateBidderProfile(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const result = await bidderApi("/bidders/me", {
    method: "PATCH",
    body: { name: text(form, "name"), nickname: text(form, "nickname"), phone: text(form, "phone"), address: addressFromForm(form) },
  });
  if (!result.ok) return { errors: result.problem.errors, message: result.problem.errors ? "Confira os campos destacados." : result.problem.detail };
  refresh();
  return { success: "Dados salvos." };
}

/** Alertas por e-mail antes dos leilões (RF-E06): 15, 30, 60 ou 120 minutos, ou nenhum. */
export async function updateAlertMinutes(_: StoreFormState, form: FormData): Promise<StoreFormState> {
  const raw = text(form, "alert_minutes");
  const result = await bidderApi("/bidders/me", { method: "PATCH", body: { alert_minutes: raw === "" ? null : Number(raw) } });
  if (!result.ok) return { message: result.problem.errors?.alert_minutes?.[0] ?? result.problem.detail };
  refresh();
  return { success: raw === "" ? "Pronto: você não vai receber alertas por e-mail." : "Alerta salvo." };
}

export async function resendBidderVerification(): Promise<StoreFormState> {
  const result = await bidderApi<{ message: string }>("/bidders/me/email/verification", { method: "POST" });
  return result.ok ? { success: result.data.message } : { message: result.problem.detail };
}

export async function applyFavoriteAfterLogin(form: FormData): Promise<void> {
  await applyPendingFavorite(form);
}
