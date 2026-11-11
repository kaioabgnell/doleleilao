"use client";

import { useEffect, useRef, useState } from "react";
import { ExternalLink, RotateCcw, ScrollText } from "lucide-react";
import { TermsText } from "@/components/legal/terms-text";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { useConfirm } from "@/components/ui/dialog";
import type { StoreTermsSettings } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";

const topics = ["Regras dos lances e do lance automático", "Prazo e formas de pagamento", "Retirada, envio e frete", "O que acontece se o pagamento não for feito", "Quando um participante é suspenso ou banido"];

const dateTime = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

/** Editor dos termos: texto simples à esquerda e, ao lado (ou na aba, no celular), como o participante vai ler. */
export function TermsEditor({ initial, slug, storeName }: { initial: StoreTermsSettings; slug: string; storeName: string }) {
  const confirm = useConfirm();
  const [saved, setSaved] = useState(initial);
  const [text, setText] = useState(initial.terms);
  const [view, setView] = useState<"edit" | "preview">("edit");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const [error, setError] = useState<string | undefined>();
  const textarea = useRef<HTMLTextAreaElement>(null);
  const alertRef = useRef<HTMLDivElement>(null);
  const dirty = text !== saved.terms;

  useEffect(() => {
    if (!message) return;
    alertRef.current?.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
  }, [message]);

  async function save(body: { terms: string } | { reset: true }) {
    setBusy(true);
    setError(undefined);
    const result = await panelRequest<StoreTermsSettings>("storefront/terms", { method: "PUT", body });
    setBusy(false);
    if (!result.ok || !result.data) {
      setError(result.errors?.terms?.[0]);
      setMessage({ tone: "error", text: result.errors?.terms?.[0] ?? result.message ?? "Não foi possível salvar os termos." });
      return;
    }
    setSaved(result.data);
    setText(result.data.terms);
    setMessage({ tone: "success", text: "reset" in body ? "Texto padrão restaurado. Já está valendo na loja." : "Termos salvos. Já estão valendo na loja." });
  }

  async function restore() {
    const ok = await confirm({
      title: "Voltar ao texto padrão?",
      description: "O texto que você escreveu será substituído pelo modelo do Dole Leilão. Quem se cadastrar a partir de agora vai aceitar o texto padrão.",
      confirmLabel: "Restaurar texto padrão",
      tone: "danger",
      icon: <RotateCcw className="size-5" />,
    });
    if (ok) await save({ reset: true });
  }

  function insertField(key: string) {
    const el = textarea.current;
    const token = `{${key}}`;
    if (!el) return setText((t) => t + token);
    const start = el.selectionStart;
    const end = el.selectionEnd;
    setText((t) => t.slice(0, start) + token + t.slice(end));
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(start + token.length, start + token.length);
    });
  }

  const preview = text.replaceAll("{loja}", storeName);

  return (
    <div className="flex flex-col gap-4">
      <div ref={alertRef} className="scroll-mt-24">{message ? <Alert tone={message.tone} title={message.text} /> : null}</div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-dl-ink-muted">
          {saved.is_default ? "Usando o texto padrão do Dole Leilão." : "Texto personalizado da loja."}
          {saved.updated_at ? ` Última alteração em ${dateTime.format(new Date(saved.updated_at))}.` : ""}
        </p>
        <a href={`/${slug}/termos`} target="_blank" rel="noopener" className={buttonClass("secondary")}>
          <ExternalLink aria-hidden className="size-4" />
          Ver na loja
        </a>
      </div>

      <div role="radiogroup" aria-label="Mostrar" className="inline-flex w-fit rounded-full bg-dl-page p-1 ring-1 ring-dl-line xl:hidden">
        {(["edit", "preview"] as const).map((key) => (
          <button
            key={key}
            type="button"
            role="radio"
            aria-checked={view === key}
            onClick={() => setView(key)}
            className={`min-h-10 rounded-full px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-dl-copper ${view === key ? "bg-dl-card text-dl-ink shadow-sm" : "text-dl-ink-muted hover:text-dl-ink"}`}
          >
            {key === "edit" ? "Editar" : "Como fica"}
          </button>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-2">
        <section aria-labelledby="editar-termos" className={`flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6 ${view === "edit" ? "" : "hidden xl:flex"}`}>
          <div className="flex flex-col gap-1.5">
            <label id="editar-termos" htmlFor="terms" className="text-sm font-medium text-dl-ink">
              Texto dos termos
            </label>
            <textarea
              ref={textarea}
              id="terms"
              value={text}
              onChange={(e) => setText(e.target.value)}
              rows={24}
              maxLength={saved.max}
              aria-invalid={error ? true : undefined}
              aria-describedby="terms-hint"
              className="block w-full resize-y rounded-field border border-dl-line bg-dl-card px-3 py-3 text-base leading-relaxed text-dl-ink focus:border-dl-copper focus:outline-2 focus:outline-offset-0 focus:outline-dl-copper/30 aria-invalid:border-state-outbid"
            />
            <p id="terms-hint" className="flex flex-wrap justify-between gap-2 text-sm text-dl-ink-muted">
              <span>
                <code className="rounded bg-dl-page px-1">## </code> no começo da linha cria um título; <code className="rounded bg-dl-page px-1">- </code> cria um item de lista.
              </span>
              <span className="tabular">
                {text.length.toLocaleString("pt-BR")} / {saved.max.toLocaleString("pt-BR")}
              </span>
            </p>
          </div>

          <div className="flex flex-col gap-2">
            <p className="text-sm font-medium text-dl-ink">Inserir dado da loja</p>
            <div className="flex flex-wrap gap-2">
              {saved.fields.map((f) => (
                <button key={f.key} type="button" onClick={() => insertField(f.key)} className="min-h-9 rounded-full border border-dl-line px-3 text-sm text-dl-ink hover:border-dl-copper hover:text-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper">
                  {f.label}
                </button>
              ))}
            </div>
          </div>

          <div className="flex flex-col-reverse gap-2 border-t border-dl-line pt-4 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" onClick={() => void restore()} disabled={busy || saved.is_default} className={buttonClass("ghost")}>
              <RotateCcw aria-hidden className="size-4" />
              Restaurar texto padrão
            </button>
            <button type="button" onClick={() => void save({ terms: text })} disabled={busy || !dirty} className={buttonClass("primary")}>
              {busy ? "Salvando…" : dirty ? "Salvar termos" : "Salvo"}
            </button>
          </div>
        </section>

        <div className={`flex flex-col gap-4 ${view === "preview" ? "" : "hidden xl:flex"}`}>
          <section aria-labelledby="como-fica" className="rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
            <h2 id="como-fica" className="mb-4 flex items-center gap-2 text-sm font-semibold tracking-wide text-dl-ink-muted uppercase">
              <ScrollText aria-hidden className="size-4" />
              Como o participante lê
            </h2>
            <div className="max-h-[38rem] overflow-y-auto pr-2">
              <TermsText text={preview} tone="platform" />
            </div>
          </section>
          <section aria-labelledby="o-que-incluir" className="rounded-card border border-dl-line bg-dl-page p-4 sm:p-6">
            <h2 id="o-que-incluir" className="font-semibold text-dl-ink">
              O que não pode faltar
            </h2>
            <ul className="mt-2 flex flex-col gap-1 text-sm text-dl-ink">
              {topics.map((t) => (
                <li key={t} className="flex gap-2">
                  <span aria-hidden className="mt-[0.55em] size-1.5 shrink-0 rounded-full bg-dl-copper" />
                  {t}
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>
    </div>
  );
}
