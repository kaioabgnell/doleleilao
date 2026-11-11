"use server";

import { refresh } from "next/cache";
import { redirect } from "next/navigation";
import { addressFromForm } from "@/lib/address";
import { api } from "@/lib/api";
import type { Auctioneer, FieldErrors, Storefront } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { clearSession, getToken } from "@/lib/session";

export interface PanelFormState {
  errors?: FieldErrors;
  message?: string;
  success?: string;
}

function text(form: FormData, key: string): string {
  const value = form.get(key);
  return typeof value === "string" ? value.trim() : "";
}

const SOCIALS = ["instagram", "facebook", "tiktok", "youtube", "website"] as const;

/** Tela "Dados da empresa" (passo 1): contato, endereço de envio e aparência. Cada Server Action confere a sessão na API. */
export async function saveCompanyData(_: PanelFormState, form: FormData): Promise<PanelFormState> {
  const template = text(form, "template");
  const body: Record<string, unknown> = {
    contact_phone: text(form, "contact_phone") || null,
    contact_email: text(form, "contact_email") || null,
    socials: Object.fromEntries(SOCIALS.map((key) => [key, text(form, `socials.${key}`) || null])),
    address: addressFromForm(form),
  };
  if (template) {
    Object.assign(body, {
      template,
      primary_color: text(form, "primary_color"),
      accent_color: text(form, "accent_color"),
      background: text(form, "background"),
      heading_font: text(form, "heading_font"),
    });
  }

  const result = await panelApi<Storefront>("/auctioneer/storefront", { method: "PATCH", body });
  if (!result.ok) {
    return { errors: result.problem.errors, message: result.problem.errors ? "Confira os campos destacados." : result.problem.detail };
  }

  refresh();
  return { success: "Dados salvos." };
}

export async function changeSlug(_: PanelFormState, form: FormData): Promise<PanelFormState> {
  const result = await panelApi<Auctioneer>("/auctioneer/storefront/slug", {
    method: "PUT",
    body: { slug: text(form, "slug") },
  });
  if (!result.ok) {
    return { errors: result.problem.errors, message: result.problem.errors?.slug?.[0] ?? result.problem.detail };
  }

  refresh();
  return { success: `Pronto! Sua loja agora está em ${result.data.store_url.replace(/^https?:\/\//, "")}.` };
}

export async function resendVerification(): Promise<PanelFormState> {
  const result = await panelApi<{ message: string }>("/auctioneer/email/verification", { method: "POST" });
  if (!result.ok) return { message: result.problem.detail };
  return { success: result.data.message };
}

export async function dismissOnboarding(): Promise<void> {
  await panelApi("/auctioneer/onboarding/dismiss", { method: "POST" });
  refresh();
}

export async function logout(): Promise<void> {
  const token = await getToken();
  if (token) await api("/auctioneer/sessions/current", { method: "DELETE", token });
  await clearSession();
  redirect("/entrar?motivo=saiu");
}
