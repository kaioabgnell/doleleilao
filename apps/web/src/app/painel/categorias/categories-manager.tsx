"use client";

import { ArrowDown, ArrowUp, ImagePlus, Pencil, Tag, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { inputClass } from "@/components/ui/field";
import type { StoreCategory } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";
import { useConfirm } from "@/components/ui/dialog";

export function CategoriesManager({ categories }: { categories: StoreCategory[] }) {
  const confirm = useConfirm();
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [editing, setEditing] = useState<string | null>(null);

  async function run(promise: ReturnType<typeof panelRequest>): Promise<boolean> {
    setBusy(true);
    setError(null);
    const result = await promise;
    setBusy(false);
    if (!result.ok) {
      if (result.status === 401) router.refresh();
      setError(result.errors ? Object.values(result.errors)[0]?.[0] ?? "Confira os dados." : (result.message ?? "Não foi possível salvar."));
      return false;
    }
    router.refresh();
    return true;
  }

  async function add(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const formEl = e.currentTarget;
    const raw = new FormData(formEl);
    const form = new FormData();
    form.append("name", String(raw.get("name") ?? ""));
    const image = raw.get("image");
    if (image instanceof File && image.size > 0) form.append("image", image);
    if (await run(panelRequest("categories", { method: "POST", body: form }))) formEl.reset();
  }

  function move(index: number, delta: number) {
    const ids = categories.map((c) => c.id);
    const [moved] = ids.splice(index, 1);
    ids.splice(index + delta, 0, moved);
    void run(panelRequest("categories/order", { method: "PUT", body: { ids } }));
  }

  return (
    <div className="flex flex-col gap-4">
      <form onSubmit={add} className="flex flex-col gap-3 rounded-card border border-dl-line bg-dl-card p-4 sm:flex-row sm:items-end">
        <div className="flex flex-1 flex-col gap-1.5">
          <label htmlFor="new-category" className="text-sm font-medium text-dl-ink">
            Nova categoria
          </label>
          <input id="new-category" name="name" required minLength={2} maxLength={40} placeholder="Ex.: Relógios de pulso" className={inputClass} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor="new-category-image" className="text-sm font-medium text-dl-ink">
            Imagem <span className="font-normal text-dl-ink-muted">(opcional)</span>
          </label>
          <input id="new-category-image" name="image" type="file" accept="image/png,image/jpeg,image/webp" className="min-h-11 text-sm text-dl-ink-muted file:mr-3 file:min-h-11 file:rounded-field file:border file:border-dl-line file:bg-dl-page file:px-3 file:text-sm file:font-medium file:text-dl-ink" />
        </div>
        <button type="submit" disabled={busy} className={buttonClass("primary")}>
          Adicionar
        </button>
      </form>

      {error ? <Alert tone="error" title={error} /> : null}

      {categories.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-10 text-center">
          <Tag aria-hidden className="size-8 text-dl-copper-icon" />
          <p className="font-semibold text-dl-ink">Nenhuma categoria ainda</p>
          <p className="max-w-sm text-sm text-dl-ink-muted">Crie categorias para os clientes acharem seus produtos mais rápido.</p>
        </div>
      ) : (
        <ol className="divide-y divide-dl-line rounded-card border border-dl-line bg-dl-card">
          {categories.map((category, index) => (
            <li key={category.id} className="flex flex-wrap items-center gap-3 px-3 py-2 sm:flex-nowrap sm:px-4">
              {editing === category.id ? (
                <CategoryEditor
                  category={category}
                  busy={busy}
                  onCancel={() => setEditing(null)}
                  onSave={async (form) => {
                    if (await run(panelRequest(`categories/${category.id}`, { method: "POST", body: form }))) setEditing(null);
                  }}
                />
              ) : (
                <>
                  <CategoryImage url={category.image_url} />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-dl-ink">{category.name}</p>
                    <p className="truncate text-xs text-dl-ink-muted">/{category.slug}</p>
                  </div>
                  <Icon label="Subir" disabled={index === 0 || busy} onClick={() => move(index, -1)} icon={<ArrowUp className="size-4" />} />
                  <Icon label="Descer" disabled={index === categories.length - 1 || busy} onClick={() => move(index, 1)} icon={<ArrowDown className="size-4" />} />
                  <Icon label="Editar nome e imagem" onClick={() => setEditing(category.id)} icon={<Pencil className="size-4" />} />
                  <Icon
                    label="Excluir"
                    disabled={busy}
                    onClick={async () => {
                      const ok = await confirm({
                        title: `Excluir a categoria “${category.name}”?`,
                        description: "Os produtos dela continuam no catálogo, só ficam sem categoria.",
                        confirmLabel: "Excluir categoria",
                        tone: "danger",
                      });
                      if (ok) void run(panelRequest(`categories/${category.id}`, { method: "DELETE" }));
                    }}
                    icon={<Trash2 className="size-4" />}
                  />
                </>
              )}
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}

function CategoryImage({ url, size = "size-10" }: { url: string | null; size?: string }) {
  return url ? (
    // eslint-disable-next-line @next/next/no-img-element -- imagem já processada pela API ou pré-visualização local
    <img src={url} alt="" className={`${size} shrink-0 rounded-full object-cover`} />
  ) : (
    <span aria-hidden className={`flex ${size} shrink-0 items-center justify-center rounded-full bg-dl-page text-dl-copper-icon`}>
      <Tag className="size-4" />
    </span>
  );
}

/** Edição de uma categoria: nome e imagem (trocar ou tirar). Tudo vai junto ao salvar. */
function CategoryEditor({ category, busy, onSave, onCancel }: { category: StoreCategory; busy: boolean; onSave: (form: FormData) => void; onCancel: () => void }) {
  const [file, setFile] = useState<{ file: File; url: string } | null>(null);
  const [remove, setRemove] = useState(false);
  const preview = file?.url ?? (remove ? null : category.image_url);
  const nameId = `edit-name-${category.id}`;
  const imageId = `edit-image-${category.id}`;

  return (
    <form
      className="flex w-full flex-col gap-3 py-2"
      onSubmit={(e) => {
        e.preventDefault();
        const form = new FormData();
        form.append("name", String(new FormData(e.currentTarget).get("name") ?? ""));
        if (file) form.append("image", file.file);
        else if (remove) form.append("remove_image", "1");
        onSave(form);
      }}
    >
      <div className="flex flex-col gap-1.5">
        <label htmlFor={nameId} className="text-sm font-medium text-dl-ink">
          Nome
        </label>
        <input id={nameId} name="name" defaultValue={category.name} autoFocus required minLength={2} maxLength={40} className={inputClass} />
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <CategoryImage url={preview} size="size-16" />
        <label htmlFor={imageId} className={buttonClass("secondary", "cursor-pointer has-focus-visible:outline-2 has-focus-visible:outline-dl-copper")}>
          <ImagePlus aria-hidden className="size-4" />
          {preview ? "Trocar imagem" : "Adicionar imagem"}
          <input
            id={imageId}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            className="sr-only"
            onChange={(e) => {
              const chosen = e.target.files?.[0];
              if (chosen) {
                setFile({ file: chosen, url: URL.createObjectURL(chosen) });
                setRemove(false);
              }
              e.target.value = "";
            }}
          />
        </label>
        {preview ? (
          <button
            type="button"
            className={buttonClass("ghost")}
            onClick={() => {
              if (file) setFile(null);
              else setRemove(true);
            }}
          >
            <Trash2 aria-hidden className="size-4" />
            {file ? "Desfazer troca" : "Tirar imagem"}
          </button>
        ) : null}
      </div>
      <div className="flex gap-2">
        <button type="submit" disabled={busy} className={buttonClass("primary")}>
          {busy ? "Salvando…" : "Salvar"}
        </button>
        <button type="button" onClick={onCancel} className={buttonClass("ghost")}>
          Cancelar
        </button>
      </div>
    </form>
  );
}

function Icon({ label, icon, onClick, disabled, type = "button" }: { label: string; icon: React.ReactNode; onClick?: () => void; disabled?: boolean; type?: "button" | "submit" }) {
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className="flex size-11 shrink-0 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper disabled:opacity-30"
    >
      {icon}
    </button>
  );
}
