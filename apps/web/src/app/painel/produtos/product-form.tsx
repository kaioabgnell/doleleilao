"use client";

import { ArrowLeft, ArrowRight, ImagePlus, Loader2, Plus, Trash2, X } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState, type FormEvent, type ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { FieldErrors, Product, ProductPhoto, StoreCategory } from "@/lib/api-types";
import { centsToInput, formatCents, maskMoney, parseMoney } from "@/lib/money";
import { panelRequest } from "@/lib/panel-client";
import { useConfirm } from "@/components/ui/dialog";
import { useFreshAfterCreate } from "@/lib/use-fresh-after-create";
import { CategoryCombobox } from "./category-combobox";

const MAX_PHOTOS = 10;
const INCREMENT_CHIPS = [10000, 20000, 50000, 100000];

interface Attribute {
  key: number;
  name: string;
  value: string;
}

/** Mesmos formatos aceitos pela API (YouTubeVideoId). */
function youtubeId(url: string): string | null {
  const m = url.trim().match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/))([A-Za-z0-9_-]{11})/);
  return m ? m[1] : null;
}

/**
 * Cadastro e edição de produto (RF-C01 a RF-C06): fotos, valores em reais (enviados em centavos),
 * vídeo do YouTube, ficha técnica livre com autocompletar e status.
 */
export function ProductForm({ product, categories }: { product: Product | null; categories: StoreCategory[] }) {
  const [formKey, markCreated] = useFreshAfterCreate();
  return <ProductFormFields key={formKey} product={product} categories={categories} onCreated={markCreated} />;
}

function ProductFormFields({ product, categories, onCreated }: { product: Product | null; categories: StoreCategory[]; onCreated: () => void }) {
  const confirm = useConfirm();
  const router = useRouter();
  const datalistId = useId();
  const locked = product?.status === "in_auction" || product?.status === "sold";

  const photos: ProductPhoto[] = product?.photos ?? [];
  const [newFiles, setNewFiles] = useState<{ file: File; url: string }[]>([]);
  const [starting, setStarting] = useState(centsToInput(product?.starting_price_cents));
  const [increment, setIncrement] = useState(centsToInput(product?.increment_cents));
  const [reference, setReference] = useState(centsToInput(product?.reference_price_cents));
  const [youtube, setYoutube] = useState(product?.youtube_url ?? "");
  const [attributes, setAttributes] = useState<Attribute[]>(
    (product?.attributes.length ? product.attributes : [{ name: "", value: "" }]).map((a, i) => ({ key: i, ...a })),
  );
  const [status, setStatus] = useState<"draft" | "available">(product?.status === "available" ? "available" : "draft");
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const err = (f: string) => errors[f]?.[0];
  const alertRef = useRef<HTMLDivElement>(null);

  // Depois de salvar (ou de um erro), sobe até o aviso no topo do formulário.
  useEffect(() => {
    if (!message) return;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    alertRef.current?.scrollIntoView({ block: "start", behavior: reduce ? "auto" : "smooth" });
  }, [message]);

  // Nomes de ficha técnica já usados pela loja (RF-C06).
  useEffect(() => {
    void panelRequest<string[]>("products/attribute-names").then((r) => r.ok && r.data && setSuggestions(r.data));
  }, []);

  // Enquanto alguma foto estiver sendo processada na fila, recarrega a cada 3 s.
  const processing = photos.some((p) => p.status === "processing");
  useEffect(() => {
    if (!processing) return;
    const timer = setInterval(() => router.refresh(), 3000);
    return () => clearInterval(timer);
  }, [processing, router]);

  const photoCount = photos.filter((p) => p.status !== "failed").length + newFiles.length;
  const videoId = youtubeId(youtube);

  async function photoAction(promise: ReturnType<typeof panelRequest>) {
    const result = await promise;
    if (!result.ok) setMessage({ tone: "error", text: result.errors?.photos?.[0] ?? result.message ?? "Não foi possível alterar as fotos." });
    router.refresh();
  }

  function movePhoto(index: number, delta: number) {
    if (!product) return;
    const ids = photos.map((p) => p.id);
    const [moved] = ids.splice(index, 1);
    ids.splice(index + delta, 0, moved);
    void photoAction(panelRequest(`products/${product.id}/photos/order`, { method: "PUT", body: { ids } }));
  }

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const raw = new FormData(e.currentTarget);
    const body = {
      title: String(raw.get("title") ?? ""),
      description: String(raw.get("description") ?? ""),
      category_id: String(raw.get("category_id") ?? ""),
      starting_price_cents: parseMoney(starting) ?? 0,
      increment_cents: parseMoney(increment) ?? 0,
      reference_price_cents: parseMoney(reference),
      youtube_url: youtube,
      attributes: attributes.map(({ name, value }) => ({ name, value })),
      // Produto novo nasce em rascunho; vira disponível depois que as fotos sobem.
      status: product && (status === "draft" || photos.some((p) => p.status !== "failed")) ? status : "draft",
    };

    setBusy(true);
    setMessage(null);
    setErrors({});
    const saved = await panelRequest<Product>(product ? `products/${product.id}` : "products", { method: product ? "PATCH" : "POST", body });
    if (!saved.ok || !saved.data) {
      setBusy(false);
      setErrors(saved.errors ?? {});
      setMessage({ tone: "error", text: saved.errors ? "Confira os campos destacados." : (saved.message ?? "Não foi possível salvar.") });
      return;
    }
    const id = saved.data.id;
    // Produto criado: a tela "Novo produto" volta limpa no próximo cadastro.
    if (!product) onCreated();

    if (newFiles.length > 0) {
      const form = new FormData();
      newFiles.forEach(({ file }) => form.append("photos[]", file));
      const uploaded = await panelRequest(`products/${id}/photos`, { method: "POST", body: form });
      if (!uploaded.ok) {
        setBusy(false);
        setMessage({ tone: "error", text: `Produto salvo, mas as fotos não subiram: ${uploaded.errors?.photos?.[0] ?? uploaded.message}` });
        router.push(`/painel/produtos/${id}`);
        return;
      }
      setNewFiles([]);
    }

    if (status === "available" && saved.data.status !== "available") {
      const published = await panelRequest<Product>(`products/${id}`, { method: "PATCH", body: { status: "available" } });
      if (!published.ok) {
        setBusy(false);
        setErrors(published.errors ?? {});
        setMessage({ tone: "error", text: published.errors?.status?.[0] ?? published.message ?? "Não foi possível deixar disponível." });
        router.push(`/painel/produtos/${id}`);
        return;
      }
    }

    setBusy(false);
    setMessage({ tone: "success", text: status === "available" ? "Produto salvo e disponível na loja." : "Produto salvo como rascunho." });
    if (product) router.refresh();
    else router.push(`/painel/produtos/${id}`);
  }

  // Em lote ou arrematado: a página mostra os detalhes e o histórico no lugar do formulário.
  if (locked) return null;

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-6 pb-24 lg:pb-0">
      <div ref={alertRef} className="scroll-mt-24">
        {message ? <Alert tone={message.tone} title={message.text} /> : null}
      </div>

      <Section title="Fotos" description={`Até ${MAX_PHOTOS} fotos, de até 10 MB. A primeira é a capa.`} aside={<span className="text-sm text-dl-ink-muted tabular">{photoCount}/{MAX_PHOTOS}</span>}>
        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-5">
          {photos.map((photo, index) => (
            <li key={photo.id} className="relative aspect-square overflow-hidden rounded-field border border-dl-line bg-dl-page">
              {photo.status === "ready" && photo.thumb ? (
                // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                <img src={photo.thumb} alt={`Foto ${index + 1}`} className="size-full object-cover" />
              ) : (
                <span className="flex size-full flex-col items-center justify-center gap-1 text-xs text-dl-ink-muted">
                  {photo.status === "failed" ? "Falhou" : <><Loader2 aria-hidden className="size-5 animate-spin" />Processando</>}
                </span>
              )}
              {index === 0 ? <span className="absolute top-1 left-1 rounded-full bg-dl-ink/80 px-2 py-0.5 text-[11px] font-semibold text-white">Capa</span> : null}
              <span className="absolute inset-x-1 bottom-1 flex justify-between gap-1">
                <span className="flex gap-1">
                  <MiniButton label="Mover para a esquerda" disabled={index === 0} onClick={() => movePhoto(index, -1)}><ArrowLeft className="size-3.5" /></MiniButton>
                  <MiniButton label="Mover para a direita" disabled={index === photos.length - 1} onClick={() => movePhoto(index, 1)}><ArrowRight className="size-3.5" /></MiniButton>
                </span>
                <MiniButton
                  label="Apagar foto"
                  onClick={async () => {
                    if (!product) return;
                    const ok = await confirm({ title: "Apagar esta foto?", description: "Não dá para desfazer.", confirmLabel: "Apagar foto", tone: "danger" });
                    if (ok) void photoAction(panelRequest(`products/${product.id}/photos/${photo.id}`, { method: "DELETE" }));
                  }}
                >
                  <Trash2 className="size-3.5" />
                </MiniButton>
              </span>
            </li>
          ))}
          {newFiles.map((item, index) => (
            <li key={item.url} className="relative aspect-square overflow-hidden rounded-field border border-dashed border-dl-copper">
              {/* eslint-disable-next-line @next/next/no-img-element -- pré-visualização local */}
              <img src={item.url} alt="" className="size-full object-cover" />
              <span className="absolute top-1 left-1 rounded-full bg-dl-copper px-2 py-0.5 text-[11px] font-semibold text-white">Nova</span>
              <span className="absolute right-1 bottom-1">
                <MiniButton label="Tirar esta foto" onClick={() => setNewFiles((files) => files.filter((_, i) => i !== index))}><X className="size-3.5" /></MiniButton>
              </span>
            </li>
          ))}
          {photoCount < MAX_PHOTOS ? (
            <li>
              <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-field border border-dashed border-dl-line bg-dl-page text-sm text-dl-ink-muted hover:border-dl-copper hover:text-dl-copper has-focus-visible:outline-2 has-focus-visible:outline-dl-copper">
                <ImagePlus aria-hidden className="size-6" />
                Adicionar
                <input
                  type="file"
                  multiple
                  accept="image/jpeg,image/png,image/webp"
                  className="sr-only"
                  onChange={(e) => {
                    const files = Array.from(e.target.files ?? []).slice(0, MAX_PHOTOS - photoCount);
                    setNewFiles((current) => [...current, ...files.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
                    e.target.value = "";
                  }}
                />
              </label>
            </li>
          ) : null}
        </ul>
        {err("photos") ? <p role="alert" className="text-sm font-medium text-state-outbid">{err("photos")}</p> : null}
      </Section>

      <Section title="Informações">
        <Field id="title" label="Título" error={err("title")}>
          <input id="title" name="title" defaultValue={product?.title} required minLength={3} maxLength={120} placeholder="Ex.: Rolex Datejust 1985" aria-invalid={err("title") ? true : undefined} aria-describedby={describedBy("title", err("title"))} className={inputClass} />
        </Field>
        <Field id="category_id" label="Categoria (opcional)" error={err("category_id")} hint="Digite para buscar. Se não existir, crie na hora.">
          <CategoryCombobox
            id="category_id"
            name="category_id"
            categories={categories}
            defaultValue={product?.category?.id ?? null}
            error={err("category_id")}
            describedBy={describedBy("category_id", err("category_id"), true)}
            onCreated={() => router.refresh()}
          />
        </Field>
        <Field id="description" label="Descrição (opcional)" error={err("description")}>
          <textarea id="description" name="description" defaultValue={product?.description ?? ""} rows={5} maxLength={5000} className={`${inputClass} py-2`} />
        </Field>
      </Section>

      <Section title="Valores" description="O lance precisa ser pelo menos o lance atual + o incremento.">
        <div className="grid gap-4 sm:grid-cols-2">
          <MoneyField id="starting_price_cents" label="Valor inicial do lance" value={starting} onChange={setStarting} error={err("starting_price_cents")} />
          <MoneyField id="reference_price_cents" label="Valor do produto (opcional)" hint="Referência para o cliente." value={reference} onChange={setReference} error={err("reference_price_cents")} />
        </div>
        <MoneyField id="increment_cents" label="Incremento" hint="Quanto cada lance sobe, no mínimo. Mínimo R$ 1,00." value={increment} onChange={setIncrement} error={err("increment_cents")} />
        <div className="-mt-2 flex flex-wrap gap-2" aria-label="Sugestões de incremento">
          {INCREMENT_CHIPS.map((cents) => (
            <button key={cents} type="button" onClick={() => setIncrement(centsToInput(cents))} className="min-h-11 rounded-full border border-dl-line px-4 text-sm font-medium text-dl-ink hover:border-dl-copper hover:text-dl-copper">
              {formatCents(cents).replace(",00", "")}
            </button>
          ))}
        </div>
      </Section>

      <Section title="Vídeo (opcional)" description="Só link do YouTube. O vídeo carrega quando o cliente toca no play.">
        <Field id="youtube_url" label="Link do YouTube" error={err("youtube_url")}>
          <input id="youtube_url" value={youtube} onChange={(e) => setYoutube(e.target.value)} inputMode="url" placeholder="https://youtu.be/…" aria-invalid={err("youtube_url") ? true : undefined} aria-describedby={describedBy("youtube_url", err("youtube_url"))} className={inputClass} />
        </Field>
        {videoId ? (
          // eslint-disable-next-line @next/next/no-img-element -- miniatura do YouTube
          <img src={`https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`} alt="Miniatura do vídeo" className="aspect-video w-full max-w-xs rounded-field object-cover" />
        ) : null}
      </Section>

      <Section title="Ficha técnica (opcional)" description="Itens livres, do jeito do seu ramo. Ex.: Calibre: automático · Tamanho: M · Memória: 256 GB.">
        <datalist id={datalistId}>
          {suggestions.map((s) => <option key={s} value={s} />)}
        </datalist>
        <ul className="flex flex-col gap-2">
          {attributes.map((attr, index) => (
            <li key={attr.key} className="grid grid-cols-[1fr_1fr_auto] gap-2">
              <input
                aria-label={`Nome do item ${index + 1}`}
                list={datalistId}
                value={attr.name}
                maxLength={40}
                placeholder="Nome"
                onChange={(e) => setAttributes((list) => list.map((a) => (a.key === attr.key ? { ...a, name: e.target.value } : a)))}
                className={inputClass}
              />
              <input
                aria-label={`Valor do item ${index + 1}`}
                value={attr.value}
                maxLength={120}
                placeholder="Valor"
                onChange={(e) => setAttributes((list) => list.map((a) => (a.key === attr.key ? { ...a, value: e.target.value } : a)))}
                className={inputClass}
              />
              <button
                type="button"
                aria-label={`Remover item ${index + 1}`}
                onClick={() => setAttributes((list) => (list.length > 1 ? list.filter((a) => a.key !== attr.key) : [{ key: Date.now(), name: "", value: "" }]))}
                className="flex size-11 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-state-outbid"
              >
                <X aria-hidden className="size-4" />
              </button>
            </li>
          ))}
        </ul>
        {err("attributes") ? <p role="alert" className="text-sm font-medium text-state-outbid">{err("attributes")}</p> : null}
        {attributes.length < 30 ? (
          <button type="button" onClick={() => setAttributes((list) => [...list, { key: Date.now(), name: "", value: "" }])} className={buttonClass("ghost", "self-start px-2")}>
            <Plus aria-hidden className="size-4" />
            Adicionar item
          </button>
        ) : null}
      </Section>

      <Section title="Status">
        <fieldset className="grid gap-2 sm:grid-cols-2" aria-describedby={err("status") ? "status-error" : undefined}>
          <legend className="sr-only">Status do produto</legend>
          {(
            [
              ["draft", "Rascunho", "Só você vê. Termine o cadastro com calma."],
              ["available", "Disponível na loja", "Aparece na loja e pode entrar num leilão. Precisa de pelo menos 1 foto."],
            ] as const
          ).map(([value, label, hint]) => (
            <label key={value} className="flex cursor-pointer gap-3 rounded-field border border-dl-line p-3 has-checked:border-dl-copper has-checked:bg-dl-page has-focus-visible:outline-2 has-focus-visible:outline-dl-copper">
              <input type="radio" name="status" value={value} checked={status === value} onChange={() => setStatus(value)} className="mt-1 size-4 accent-dl-copper" />
              <span className="flex flex-col">
                <span className="text-sm font-semibold text-dl-ink">{label}</span>
                <span className="text-xs text-dl-ink-muted">{hint}</span>
              </span>
            </label>
          ))}
        </fieldset>
        {err("status") ? <p id="status-error" role="alert" className="text-sm font-medium text-state-outbid">{err("status")}</p> : null}
      </Section>

      <div className="fixed inset-x-0 bottom-0 z-20 flex gap-2 border-t border-dl-line bg-dl-card p-3 lg:static lg:border-0 lg:bg-transparent lg:p-0">
        <button type="submit" disabled={busy} className={buttonClass("primary", "flex-1 lg:flex-none")}>
          {busy ? <><Loader2 aria-hidden className="size-4 animate-spin" />Salvando…</> : "Salvar produto"}
        </button>
        {product ? (
          <button
            type="button"
            disabled={busy}
            onClick={async () => {
              const ok = await confirm({
                title: `Apagar “${product.title}”?`,
                description: "O produto e as fotos dele saem do catálogo e da loja. Não dá para desfazer.",
                confirmLabel: "Apagar produto",
                tone: "danger",
              });
              if (!ok) return;
              const result = await panelRequest(`products/${product.id}`, { method: "DELETE" });
              if (result.ok) router.push("/painel/produtos");
              else setMessage({ tone: "error", text: result.message ?? "Não foi possível apagar." });
            }}
            className={buttonClass("ghost")}
          >
            <Trash2 aria-hidden className="size-4" />
            Apagar
          </button>
        ) : null}
      </div>
    </form>
  );
}

function Section({ title, description, aside, children }: { title: string; description?: string; aside?: ReactNode; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-brand text-lg font-bold text-dl-ink">{title}</h2>
          {description ? <p className="mt-0.5 text-sm text-dl-ink-muted">{description}</p> : null}
        </div>
        {aside}
      </div>
      {children}
    </section>
  );
}

function MoneyField({ id, label, hint, value, onChange, error }: { id: string; label: string; hint?: string; value: string; onChange: (v: string) => void; error?: string }) {
  return (
    <Field id={id} label={label} hint={hint} error={error}>
      <div className="flex min-h-11 items-stretch overflow-hidden rounded-field border border-dl-line bg-dl-card focus-within:border-dl-copper focus-within:outline-2 focus-within:outline-dl-copper/30">
        <span className="flex items-center border-r border-dl-line bg-dl-page px-3 text-sm text-dl-ink-muted">R$</span>
        <input
          id={id}
          value={value}
          onChange={(e) => onChange(maskMoney(e.target.value))}
          inputMode="numeric"
          placeholder="0,00"
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(id, error, Boolean(hint))}
          className={`${inputClass} rounded-none border-0 text-right tabular focus:outline-none`}
        />
      </div>
    </Field>
  );
}

function MiniButton({ label, onClick, disabled, children }: { label: string; onClick: () => void; disabled?: boolean; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className="flex size-11 items-center justify-center rounded-full bg-dl-card/90 text-dl-ink shadow-sm hover:text-dl-copper disabled:opacity-30"
    >
      {children}
    </button>
  );
}
