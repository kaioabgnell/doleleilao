"use client";

import { ImagePlus, X } from "lucide-react";
import { useState, type FormEvent } from "react";
import { aria, StoreField, StoreNotice, storeButton, storeInput } from "@/components/store/store-ui";
import { maskPhone } from "@/lib/format";

export function ConsignmentForm({ slug, defaults }: { slug: string; defaults: { name: string; email: string; phone: string } }) {
  const [phone, setPhone] = useState(defaults.phone);
  const [photos, setPhotos] = useState<{ file: File; url: string }[]>([]);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [status, setStatus] = useState<"idle" | "sending" | "sent">("idle");
  const [message, setMessage] = useState<string | null>(null);
  const err = (f: string) => errors[f]?.[0];

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const raw = new FormData(e.currentTarget);
    const form = new FormData();
    for (const key of ["name", "email", "description"]) form.append(key, String(raw.get(key) ?? ""));
    form.append("phone", phone);
    photos.forEach(({ file }) => form.append("photos[]", file));

    setStatus("sending");
    setMessage(null);
    const res = await fetch(`/${slug}/api/quero-leiloar`, { method: "POST", body: form }).catch(() => null);
    const body = (await res?.json().catch(() => null)) as { errors?: Record<string, string[]>; detail?: string } | null;
    if (!res?.ok) {
      setStatus("idle");
      setErrors(body?.errors ?? {});
      setMessage(body?.errors ? "Confira os campos destacados." : (body?.detail ?? "Não foi possível enviar. Tente de novo."));
      return;
    }
    setStatus("sent");
  }

  if (status === "sent") return <StoreNotice tone="success">Pedido enviado! O leiloeiro vai entrar em contato pelo e-mail ou telefone que você informou.</StoreNotice>;

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
      {message ? <StoreNotice tone="error">{message}</StoreNotice> : null}
      <StoreField id="name" label="Seu nome" error={err("name")}>
        <input id="name" name="name" defaultValue={defaults.name} autoComplete="name" required {...aria("name", err("name"))} className={storeInput} />
      </StoreField>
      <div className="grid gap-4 sm:grid-cols-2">
        <StoreField id="email" label="E-mail" error={err("email")}>
          <input id="email" name="email" type="email" defaultValue={defaults.email} autoComplete="email" inputMode="email" {...aria("email", err("email"))} className={storeInput} />
        </StoreField>
        <StoreField id="phone" label="WhatsApp" error={err("phone")}>
          <input id="phone" type="tel" value={phone} onChange={(e) => setPhone(maskPhone(e.target.value))} autoComplete="tel-national" inputMode="tel" placeholder="(11) 98765-4321" {...aria("phone", err("phone"))} className={storeInput} />
        </StoreField>
      </div>
      <StoreField id="description" label="O que você quer leiloar?" error={err("description")} hint="Marca, modelo, estado de conservação, ano… Pelo menos 20 letras.">
        <textarea id="description" name="description" rows={5} minLength={20} maxLength={2000} required {...aria("description", err("description"), true)} className={`${storeInput} py-2`} />
      </StoreField>
      <div className="flex flex-col gap-1.5">
        <span className="text-sm font-medium">Fotos (opcional, até 3)</span>
        <div className="flex gap-2">
          {photos.map((p, i) => (
            <span key={p.url} className="relative">
              {/* eslint-disable-next-line @next/next/no-img-element -- pré-visualização local */}
              <img src={p.url} alt="" className="size-20 rounded-field object-cover" />
              <button type="button" aria-label="Tirar foto" onClick={() => setPhotos((list) => list.filter((_, j) => j !== i))} className="absolute -top-2 -right-2 flex size-7 items-center justify-center rounded-full bg-store-text text-store-surface">
                <X aria-hidden className="size-4" />
              </button>
            </span>
          ))}
          {photos.length < 3 ? (
            <label className="flex size-20 cursor-pointer items-center justify-center rounded-field border border-dashed border-store-border text-store-text-muted hover:border-store-primary has-focus-visible:outline-2">
              <ImagePlus aria-hidden className="size-6" />
              <span className="sr-only">Adicionar foto</span>
              <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={(e) => {
                const files = Array.from(e.target.files ?? []).slice(0, 3 - photos.length);
                setPhotos((list) => [...list, ...files.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
                e.target.value = "";
              }} />
            </label>
          ) : null}
        </div>
        {err("photos") ? <p role="alert" className="text-sm font-medium text-state-outbid">{err("photos")}</p> : null}
      </div>
      <button type="submit" disabled={status === "sending"} className={storeButton}>{status === "sending" ? "Enviando…" : "Enviar pedido"}</button>
    </form>
  );
}
