"use client";

import { Camera, Check, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useActionState, useState } from "react";
import { AddressDetails, PostalCodeField, useAddressForm } from "@/components/forms/address-fields";
import { AVATAR_KEYS, AvatarGlyph, BidderAvatar } from "@/components/store/bidder-avatar";
import { aria, StoreField, StoreNotice, StoreSubmit, storeButtonOutline, storeInput } from "@/components/store/store-ui";
import type { Bidder } from "@/lib/api-types";
import { maskPhone } from "@/lib/format";
import { resendBidderVerification, updateAlertMinutes, updateBidderProfile, type StoreFormState } from "../acoes";

export function EmailPending({ email }: { email: string }) {
  const [state, action] = useActionState<StoreFormState>(resendBidderVerification, {});
  return (
    <form action={action} className="flex flex-col gap-2 sm:flex-row sm:items-center">
      <p className="flex-1 text-sm text-store-text-muted" aria-live="polite">{state.success ?? state.message ?? `Mandamos um link para ${email}.`}</p>
      <StoreSubmit pending="Enviando…">Reenviar link</StoreSubmit>
    </form>
  );
}

export function ProfileForm({ slug, bidder }: { slug: string; bidder: Bidder }) {
  const [state, action] = useActionState<StoreFormState, FormData>(updateBidderProfile, {});
  const [phone, setPhone] = useState(bidder.phone);
  const address = useAddressForm(bidder.address);
  const err = (f: string) => state.errors?.[f]?.[0];
  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      <input type="hidden" name="slug" value={slug} />
      {state.success ? <StoreNotice tone="success">{state.success}</StoreNotice> : null}
      {state.message ? <StoreNotice tone="error">{state.message}</StoreNotice> : null}
      <StoreField id="name" label="Nome completo" error={err("name")}>
        <input id="name" name="name" defaultValue={bidder.name} autoComplete="name" {...aria("name", err("name"))} className={storeInput} />
      </StoreField>
      <StoreField id="nickname" label="Apelido" error={err("nickname")} hint="É assim que você aparece para os outros participantes.">
        <input id="nickname" name="nickname" defaultValue={bidder.nickname} maxLength={30} {...aria("nickname", err("nickname"), true)} className={storeInput} />
      </StoreField>
      <StoreField id="phone" label="Celular (WhatsApp)" error={err("phone")}>
        <input id="phone" name="phone" type="tel" value={phone} onChange={(e) => setPhone(maskPhone(e.target.value))} inputMode="tel" {...aria("phone", err("phone"))} className={storeInput} />
      </StoreField>
      <PostalCodeField
        form={address}
        skin="store"
        label="CEP de entrega (opcional)"
        hint="Para onde o leiloeiro envia o que você arrematar."
        error={err}
      />
      <AddressDetails form={address} skin="store" error={err} />
      <StoreSubmit pending="Salvando…">Salvar</StoreSubmit>
    </form>
  );
}

/** Foto (opcional) ou avatar da galeria de 12 (D-38). */
export function AvatarPicker({ slug, bidder }: { slug: string; bidder: Bidder }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function call(path: string, init: RequestInit) {
    setBusy(true);
    setError(null);
    const res = await fetch(`/${slug}/api/conta/${path}`, init).catch(() => null);
    setBusy(false);
    if (!res?.ok) {
      const body = (await res?.json().catch(() => null)) as { errors?: Record<string, string[]>; detail?: string } | null;
      setError(body?.errors ? Object.values(body.errors)[0]?.[0] ?? "Não foi possível salvar." : (body?.detail ?? "Não foi possível salvar."));
      return;
    }
    router.refresh();
  }

  return (
    <div className="flex flex-col gap-4">
      {error ? <StoreNotice tone="error">{error}</StoreNotice> : null}
      <div className="flex items-center gap-4">
        <BidderAvatar name={bidder.name} photoUrl={bidder.photo_url} avatarKey={bidder.avatar_key} className="size-16" />
        <div className="flex flex-wrap gap-2">
          <label className={`${storeButtonOutline} cursor-pointer has-focus-visible:outline-2`}>
            <Camera aria-hidden className="size-4" />
            {bidder.photo_url ? "Trocar foto" : "Enviar foto"}
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="sr-only"
              disabled={busy}
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (!file) return;
                const form = new FormData();
                form.append("photo", file);
                void call("photo", { method: "POST", body: form });
                e.target.value = "";
              }}
            />
          </label>
          {bidder.photo_url ? (
            <button type="button" disabled={busy} onClick={() => call("photo", { method: "DELETE" })} className="inline-flex min-h-11 items-center gap-2 px-3 text-sm font-medium text-store-text-muted hover:text-store-link">
              <Trash2 aria-hidden className="size-4" />
              Remover foto
            </button>
          ) : null}
        </div>
      </div>
      <fieldset>
        <legend className="mb-2 text-sm font-medium">{bidder.photo_url ? "Ou use um avatar (aparece se você remover a foto)" : "Ou escolha um avatar"}</legend>
        <div className="grid grid-cols-6 gap-2">
          {AVATAR_KEYS.map((key) => {
            const selected = bidder.avatar_key === key;
            return (
              <button
                key={key}
                type="button"
                disabled={busy}
                aria-pressed={selected}
                aria-label={`Avatar ${key.slice(-2)}`}
                onClick={() => call("avatar", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ avatar_key: selected ? null : key }) })}
                className={`relative flex aspect-square items-center justify-center rounded-full border-2 ${selected ? "border-store-text" : "border-transparent"} focus-visible:outline-2 focus-visible:outline-store-primary`}
              >
                <AvatarGlyph avatarKey={key} className="size-full" />
                {selected ? (
                  <span className="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-store-text text-store-surface">
                    <Check aria-hidden className="size-3" />
                  </span>
                ) : null}
              </button>
            );
          })}
        </div>
      </fieldset>
    </div>
  );
}

/** Alerta por e-mail antes dos leilões em que a pessoa vai participar ou tem favoritos (RF-E06). */
export function AlertsForm({ minutes }: { minutes: number | null }) {
  const [state, action] = useActionState<StoreFormState, FormData>(updateAlertMinutes, {});
  return (
    <form action={action} className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <StoreField id="alert_minutes" label="Avisar por e-mail" error={state.message}>
        <select id="alert_minutes" name="alert_minutes" defaultValue={minutes === null ? "" : String(minutes)} className={storeInput}>
          <option value="15">15 minutos antes</option>
          <option value="30">30 minutos antes</option>
          <option value="60">1 hora antes</option>
          <option value="120">2 horas antes</option>
          <option value="">Não quero alertas</option>
        </select>
      </StoreField>
      <StoreSubmit pending="Salvando…">Salvar</StoreSubmit>
      {state.success ? <p role="status" className="text-sm font-medium text-state-winning sm:pb-3">{state.success}</p> : null}
    </form>
  );
}
