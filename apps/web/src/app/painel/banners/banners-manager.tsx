"use client";

import { ArrowDown, ArrowUp, ImagePlus, Pencil, Plus, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { FieldErrors, StoreBanner } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";
import { useConfirm } from "@/components/ui/dialog";

const MAX = 10;

function status(b: StoreBanner): { label: string; tone: string } {
  const now = Date.now();
  if (!b.active) return { label: "Desativado", tone: "bg-dl-page text-dl-ink-muted" };
  if (b.starts_at && new Date(b.starts_at).getTime() > now) return { label: "Agendado", tone: "bg-dl-page text-dl-copper" };
  if (b.ends_at && new Date(b.ends_at).getTime() <= now) return { label: "Encerrado", tone: "bg-dl-page text-dl-ink-muted" };
  return { label: "No ar", tone: "bg-state-winning/10 text-state-winning" };
}

const dateTime = new Intl.DateTimeFormat("pt-BR", { dateStyle: "short", timeStyle: "short", timeZone: "America/Sao_Paulo" });

/** ISO (UTC) → valor do <input type="datetime-local"> no horário do navegador. */
function toLocalInput(iso: string | null): string {
  if (!iso) return "";
  const d = new Date(iso);
  return new Date(d.getTime() - d.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

export function BannersManager({ banners }: { banners: StoreBanner[] }) {
  const confirm = useConfirm();
  const router = useRouter();
  const [editing, setEditing] = useState<StoreBanner | "new" | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function run(promise: ReturnType<typeof panelRequest>) {
    setError(null);
    const result = await promise;
    if (!result.ok) {
      if (result.status === 401) router.refresh();
      setError(result.message ?? "Não foi possível salvar.");
    }
    router.refresh();
  }

  function move(index: number, delta: number) {
    const ids = banners.map((b) => b.id);
    const [moved] = ids.splice(index, 1);
    ids.splice(index + delta, 0, moved);
    void run(panelRequest("banners/order", { method: "PUT", body: { ids } }));
  }

  return (
    <div className="flex flex-col gap-4">
      {error ? <Alert tone="error" title={error} /> : null}

      {editing ? (
        <BannerForm
          banner={editing === "new" ? null : editing}
          onDone={() => {
            setEditing(null);
            router.refresh();
          }}
        />
      ) : (
        <button
          type="button"
          onClick={() => setEditing("new")}
          disabled={banners.length >= MAX}
          className={buttonClass("primary", "self-start")}
        >
          <Plus aria-hidden className="size-5" />
          Novo banner
        </button>
      )}
      {banners.length >= MAX ? <p className="text-sm text-dl-ink-muted">Você chegou ao máximo de {MAX} banners.</p> : null}

      {banners.length === 0 && !editing ? (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-10 text-center">
          <ImagePlus aria-hidden className="size-8 text-dl-copper-icon" />
          <p className="font-semibold text-dl-ink">Nenhum banner ainda</p>
          <p className="max-w-sm text-sm text-dl-ink-muted">
            Use banners para anunciar o próximo leilão ou uma novidade. A imagem do celular é obrigatória.
          </p>
        </div>
      ) : (
        <ol className="flex flex-col gap-3">
          {banners.map((banner, index) => {
            const s = status(banner);
            return (
              <li key={banner.id} className="flex gap-4 rounded-card border border-dl-line bg-dl-card p-3 sm:p-4">
                {/* eslint-disable-next-line @next/next/no-img-element -- imagem já processada pela API */}
                <img src={banner.image_mobile_url} alt="" className="aspect-[4/5] w-16 shrink-0 rounded-field object-cover sm:w-20" />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-semibold text-dl-ink">{banner.title}</p>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${s.tone}`}>{s.label}</span>
                  </div>
                  <p className="text-sm text-dl-ink-muted">
                    {banner.starts_at || banner.ends_at
                      ? `${banner.starts_at ? `De ${dateTime.format(new Date(banner.starts_at))}` : "Já"} ${banner.ends_at ? `até ${dateTime.format(new Date(banner.ends_at))}` : "sem data de fim"}`
                      : "Sem período definido"}
                  </p>
                  {banner.link_url ? <p className="truncate text-sm text-dl-ink-muted">Link: {banner.link_url}</p> : null}
                  <div className="mt-1 flex flex-wrap gap-1">
                    <IconButton label="Subir" disabled={index === 0} onClick={() => move(index, -1)} icon={<ArrowUp className="size-4" />} />
                    <IconButton label="Descer" disabled={index === banners.length - 1} onClick={() => move(index, 1)} icon={<ArrowDown className="size-4" />} />
                    <IconButton label="Editar" onClick={() => setEditing(banner)} icon={<Pencil className="size-4" />} />
                    <IconButton
                      label="Excluir"
                      onClick={async () => {
                        const ok = await confirm({ title: `Excluir o banner “${banner.title}”?`, description: "Ele sai da loja na hora. Não dá para desfazer.", confirmLabel: "Excluir banner", tone: "danger" });
                        if (ok) void run(panelRequest(`banners/${banner.id}`, { method: "DELETE" }));
                      }}
                      icon={<Trash2 className="size-4" />}
                    />
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      )}
    </div>
  );
}

function IconButton({ label, icon, onClick, disabled }: { label: string; icon: React.ReactNode; onClick: () => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className="flex size-11 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper disabled:opacity-30"
    >
      {icon}
    </button>
  );
}

function BannerForm({ banner, onDone }: { banner: StoreBanner | null; onDone: () => void }) {
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [mobilePreview, setMobilePreview] = useState<string | null>(banner?.image_mobile_url ?? null);
  const [desktopPreview, setDesktopPreview] = useState<string | null>(banner?.image_desktop_url ?? null);
  const [removeDesktop, setRemoveDesktop] = useState(false);
  const err = (f: string) => errors[f]?.[0];

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const raw = new FormData(e.currentTarget);
    const form = new FormData();
    form.append("title", String(raw.get("title") ?? ""));
    form.append("link_url", String(raw.get("link_url") ?? ""));
    for (const key of ["starts_at", "ends_at"] as const) {
      const value = String(raw.get(key) ?? "");
      form.append(key, value ? new Date(value).toISOString() : "");
    }
    form.append("active", raw.get("active") === "on" ? "1" : "0");
    for (const key of ["image_mobile", "image_desktop"] as const) {
      const file = raw.get(key);
      if (file instanceof File && file.size > 0) form.append(key, file);
    }
    if (removeDesktop) form.append("remove_image_desktop", "1");

    setBusy(true);
    setMessage(null);
    const result = await panelRequest<StoreBanner>(banner ? `banners/${banner.id}` : "banners", { method: "POST", body: form });
    setBusy(false);
    if (!result.ok) {
      setErrors(result.errors ?? {});
      setMessage(result.errors ? "Confira os campos destacados." : (result.message ?? "Não foi possível salvar."));
      return;
    }
    onDone();
  }

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-4 rounded-card border border-dl-copper bg-dl-card p-5 sm:p-6">
      <h2 className="font-brand text-lg font-bold text-dl-ink">{banner ? "Editar banner" : "Novo banner"}</h2>
      {message ? <Alert tone="error" title={message} /> : null}

      <div className="grid gap-4 sm:grid-cols-[auto_1fr]">
        <ImageField
          id="image_mobile"
          label="Imagem do celular"
          hint="Obrigatória. Vertical 4:5, ideal 1080 × 1350."
          preview={mobilePreview}
          aspect="aspect-[4/5] w-32"
          error={err("image_mobile")}
          required={!banner}
          onPreview={setMobilePreview}
        />
        <ImageField
          id="image_desktop"
          label="Imagem do computador"
          hint="Opcional. Horizontal 3:1, ideal 1920 × 640."
          preview={removeDesktop ? null : desktopPreview}
          aspect="aspect-[3/1] w-full max-w-sm"
          error={err("image_desktop")}
          onPreview={(url) => {
            setDesktopPreview(url);
            setRemoveDesktop(false);
          }}
          onRemove={banner?.image_desktop_url && !removeDesktop ? () => setRemoveDesktop(true) : undefined}
        />
      </div>

      <Field id="title" label="Texto do banner" error={err("title")} hint="Descreve a imagem para quem usa leitor de tela. Ex.: Leilão de Natal, dia 20.">
        <input id="title" name="title" defaultValue={banner?.title} maxLength={120} required aria-invalid={err("title") ? true : undefined} aria-describedby={describedBy("title", err("title"), true)} className={inputClass} />
      </Field>
      <Field id="link_url" label="Link (opcional)" error={err("link_url")} hint="Endereço com https:// ou uma página da sua loja, começando com /.">
        <input id="link_url" name="link_url" defaultValue={banner?.link_url ?? ""} inputMode="url" placeholder="https://" aria-invalid={err("link_url") ? true : undefined} aria-describedby={describedBy("link_url", err("link_url"), true)} className={inputClass} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field id="starts_at" label="Mostrar a partir de (opcional)" error={err("starts_at")}>
          <input id="starts_at" name="starts_at" type="datetime-local" defaultValue={toLocalInput(banner?.starts_at ?? null)} className={inputClass} />
        </Field>
        <Field id="ends_at" label="Mostrar até (opcional)" error={err("ends_at")}>
          <input id="ends_at" name="ends_at" type="datetime-local" defaultValue={toLocalInput(banner?.ends_at ?? null)} aria-invalid={err("ends_at") ? true : undefined} className={inputClass} />
        </Field>
      </div>
      <label className="flex min-h-11 items-center gap-3 text-sm text-dl-ink">
        <input type="checkbox" name="active" defaultChecked={banner?.active ?? true} className="size-5 accent-dl-copper" />
        Banner ativo
      </label>

      <div className="flex flex-wrap gap-2">
        <button type="submit" disabled={busy} className={buttonClass("primary")}>
          {busy ? "Salvando…" : "Salvar banner"}
        </button>
        <button type="button" onClick={onDone} disabled={busy} className={buttonClass("secondary")}>
          Cancelar
        </button>
      </div>
    </form>
  );
}

function ImageField(props: {
  id: string;
  label: string;
  hint: string;
  preview: string | null;
  aspect: string;
  error?: string;
  required?: boolean;
  onPreview: (url: string | null) => void;
  onRemove?: () => void;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={props.id} className="text-sm font-medium text-dl-ink">
        {props.label}
      </label>
      <label
        htmlFor={props.id}
        className={`${props.aspect} flex cursor-pointer items-center justify-center overflow-hidden rounded-field border border-dashed border-dl-line bg-dl-page text-dl-ink-muted hover:border-dl-copper has-focus-visible:outline-2 has-focus-visible:outline-dl-copper`}
      >
        {props.preview ? (
          // eslint-disable-next-line @next/next/no-img-element -- pré-visualização
          <img src={props.preview} alt="" className="size-full object-cover" />
        ) : (
          <ImagePlus aria-hidden className="size-6" />
        )}
      </label>
      <input
        id={props.id}
        name={props.id}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        required={props.required}
        aria-invalid={props.error ? true : undefined}
        aria-describedby={describedBy(props.id, props.error, true)}
        onChange={(e) => {
          const file = e.target.files?.[0];
          props.onPreview(file ? URL.createObjectURL(file) : null);
        }}
        className="sr-only"
      />
      {props.error ? (
        <p id={`${props.id}-error`} role="alert" className="text-sm font-medium text-state-outbid">{props.error}</p>
      ) : (
        <p id={`${props.id}-hint`} className="text-xs text-dl-ink-muted">{props.hint}</p>
      )}
      {props.onRemove ? (
        <button type="button" onClick={props.onRemove} className="self-start text-sm font-medium text-dl-copper underline-offset-4 hover:underline">
          Remover imagem do computador
        </button>
      ) : null}
    </div>
  );
}
