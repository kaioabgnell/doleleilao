"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { ArrowDown, ArrowUp, CalendarCheck, CreditCard, ImageOff, ImagePlus, Plus, RefreshCw, Search, Trash2, Undo2, X } from "lucide-react";
import { buttonClass } from "@/components/ui/button-styles";
import { Alert } from "@/components/ui/alert";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { Auction, AuctionSettings, FeePayer, FieldErrors, Product } from "@/lib/api-types";
import { fromLocalInputs, toLocalInputs } from "@/lib/auction-time";
import { centsToInput, formatCents, maskMoney, parseMoney } from "@/lib/money";
import { panelRequest } from "@/lib/panel-client";
import { useConfirm } from "@/components/ui/dialog";
import { useFreshAfterCreate } from "@/lib/use-fresh-after-create";

interface ItemRow {
  productId: string;
  title: string;
  thumb: string | null;
  starting: string;
  increment: string;
}

const DURATIONS = [30, 45, 60, 90, 120, 180, 300];
const WINDOWS = [0, 10, 15, 20, 30, 60];
const EXTENSIONS = [10, 15, 20, 30, 60];
const GAPS = [0, 5, 10, 15, 30, 60];

function seconds(s: number): string {
  if (s === 0) return "Sem intervalo";
  return s < 60 ? `${s} segundos` : s % 60 === 0 ? `${s / 60} ${s === 60 ? "minuto" : "minutos"}` : `${Math.floor(s / 60)} min ${s % 60} s`;
}

const FEE_PAYERS: { value: FeePayer; title: string; text: string }[] = [
  { value: "auctioneer", title: "Assumir a taxa", text: "Ela sai do valor que você recebe." },
  { value: "bidder", title: "Repassar ao vencedor", text: "O vencedor paga o arremate mais a taxa." },
];

/**
 * Cadastro do lote (RF-D01 a RF-D03): dados, data e hora (Brasília), itens com valor inicial e
 * incremento copiados do produto (ajustáveis), ordem, configurações do pregão e do chat, e quem
 * paga a taxa (D-54). Agendar exige o cartão em Configurações → Pagamento.
 */
export function AuctionForm({ auction, defaults, hasPaymentCard }: { auction: Auction | null; defaults: AuctionSettings; hasPaymentCard: boolean }) {
  const [formKey, markCreated] = useFreshAfterCreate();
  return <AuctionFormFields key={formKey} auction={auction} defaults={defaults} hasPaymentCard={hasPaymentCard} onCreated={markCreated} />;
}

const COVER_TYPES = ["image/jpeg", "image/png", "image/webp"];
const COVER_MAX_BYTES = 10 * 1024 * 1024;

function AuctionFormFields({
  auction,
  defaults,
  hasPaymentCard,
  onCreated,
}: {
  auction: Auction | null;
  defaults: AuctionSettings;
  hasPaymentCard: boolean;
  onCreated: () => void;
}) {
  const confirm = useConfirm();
  const router = useRouter();
  const start = auction ? toLocalInputs(auction.starts_at) : null;
  const [name, setName] = useState(auction?.name ?? "");
  const [description, setDescription] = useState(auction?.description ?? "");
  const [date, setDate] = useState(start?.date ?? "");
  const [time, setTime] = useState(start?.time ?? "20:00");
  const [settings, setSettings] = useState<AuctionSettings>({ ...defaults, ...(auction?.settings ?? {}) } as AuctionSettings);
  const [items, setItems] = useState<ItemRow[]>(
    (auction?.items ?? []).map((item) => ({
      productId: item.product?.id ?? "",
      title: item.title,
      thumb: item.product?.photos[0]?.thumb ?? null,
      starting: centsToInput(item.starting_price_cents),
      increment: centsToInput(item.increment_cents),
    })),
  );
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [picking, setPicking] = useState(false);
  // Capa: o arquivo novo sobe depois de salvar o lote (como as fotos do produto).
  const savedCover = auction?.cover?.custom ? auction.cover.card.webp : null;
  const [coverFile, setCoverFile] = useState<{ file: File; url: string } | null>(null);
  const [removeCover, setRemoveCover] = useState(false);
  const coverPreview = coverFile?.url ?? (removeCover ? null : savedCover);

  const status = auction?.status ?? "draft";
  const editable = status === "draft" || status === "scheduled";

  function move(index: number, delta: number) {
    setItems((rows) => {
      const next = [...rows];
      const [row] = next.splice(index, 1);
      next.splice(index + delta, 0, row);
      return next;
    });
  }

  function payload() {
    return {
      name,
      description,
      starts_at: date && time ? fromLocalInputs(date, time) : "",
      settings,
      items: items.map((row) => ({
        product_id: row.productId,
        starting_price_cents: parseMoney(row.starting),
        increment_cents: parseMoney(row.increment),
      })),
    };
  }

  async function save(): Promise<Auction | null> {
    setErrors({});
    setMessage(null);
    const result = await panelRequest<Auction>(auction ? `auctions/${auction.id}` : "auctions", {
      method: auction ? "PATCH" : "POST",
      body: payload(),
    });
    if (!result.ok || !result.data) {
      setErrors(result.errors ?? {});
      setMessage({ tone: "error", text: result.message ?? "Não foi possível salvar." });
      return null;
    }
    if (!auction) onCreated();

    const coverError = await saveCover(result.data.id);
    if (coverError) {
      // O lote já foi salvo: no cadastro novo, segue para a página dele com o aviso.
      if (!auction) router.push(`/painel/leiloes/${result.data.id}?capa=erro`);
      else setMessage({ tone: "error", text: `Lote salvo, mas a capa não foi alterada: ${coverError}` });
      return null;
    }
    return result.data;
  }

  /** Envia ou tira a capa. Devolve a mensagem de erro, se houver. */
  async function saveCover(id: string): Promise<string | null> {
    if (coverFile) {
      const form = new FormData();
      form.append("cover", coverFile.file);
      const result = await panelRequest<Auction>(`auctions/${id}/cover`, { method: "POST", body: form });
      if (!result.ok) return result.errors?.cover?.[0] ?? result.message ?? "tente de novo.";
      setCoverFile(null);
    } else if (removeCover && savedCover) {
      const result = await panelRequest<Auction>(`auctions/${id}/cover`, { method: "DELETE" });
      if (!result.ok) return result.message ?? "tente de novo.";
      setRemoveCover(false);
    }
    return null;
  }

  function chooseCover(file: File | undefined) {
    if (!file) return;
    if (!COVER_TYPES.includes(file.type)) return setErrors((e) => ({ ...e, cover: ["Envie uma imagem JPG, PNG ou WebP."] }));
    if (file.size > COVER_MAX_BYTES) return setErrors((e) => ({ ...e, cover: ["A imagem pode ter até 10 MB."] }));
    setErrors((current) => {
      const next = { ...current };
      delete next.cover;
      return next;
    });
    setCoverFile({ file, url: URL.createObjectURL(file) });
    setRemoveCover(false);
  }

  async function onSave() {
    setBusy("save");
    const saved = await save();
    setBusy(null);
    if (!saved) return;
    if (!auction) {
      router.push(`/painel/leiloes/${saved.id}`);
      return;
    }
    setMessage({ tone: "success", text: "Alterações salvas." });
    router.refresh();
  }

  async function onSchedule() {
    setBusy("schedule");
    const saved = await save();
    if (!saved) return setBusy(null);
    const result = await panelRequest<Auction>(`auctions/${saved.id}/schedule`, { method: "POST" });
    setBusy(null);
    if (!result.ok) {
      setErrors(result.errors ?? {});
      setMessage({ tone: "error", text: result.message ?? "Não foi possível agendar." });
      if (!auction) router.push(`/painel/leiloes/${saved.id}`);
      return;
    }
    if (!auction) router.push(`/painel/leiloes/${saved.id}?agendado=1`);
    else {
      setMessage({ tone: "success", text: "Lote agendado! Ele já aparece na sua loja." });
      router.refresh();
    }
  }

  async function onAction(action: "unschedule" | "cancel" | "delete") {
    if (!auction) return;
    const ask = {
      unschedule: {
        title: "Voltar o lote para rascunho?",
        description: "Ele sai da loja até você agendar de novo. Os produtos voltam a ficar disponíveis no catálogo.",
        confirmLabel: "Voltar para rascunho",
      },
      cancel: {
        title: "Cancelar este lote?",
        description: "Ele sai da loja e os produtos voltam a ficar disponíveis. Quem marcou “Quero participar” deixa de receber os avisos.",
        confirmLabel: "Cancelar lote",
        cancelLabel: "Manter lote",
        tone: "danger" as const,
      },
      delete: { title: "Apagar este lote?", description: "Não dá para desfazer.", confirmLabel: "Apagar lote", tone: "danger" as const },
    }[action];
    if (!(await confirm(ask))) return;
    setBusy(action);
    const result =
      action === "delete"
        ? await panelRequest(`auctions/${auction.id}`, { method: "DELETE" })
        : await panelRequest<Auction>(`auctions/${auction.id}/${action}`, { method: "POST" });
    setBusy(null);
    if (!result.ok) return setMessage({ tone: "error", text: result.message ?? "Não foi possível concluir." });
    if (action === "delete") router.push("/painel/leiloes");
    else router.refresh();
  }

  const err = (key: string) => errors[key]?.[0];
  const itemErrors = Object.entries(errors).filter(([key]) => key.startsWith("items"));

  return (
    <form
      className="flex flex-col gap-6"
      onSubmit={(e) => {
        e.preventDefault();
        void onSave();
      }}
      noValidate
    >
      {message ? <Alert tone={message.tone} title={message.text} /> : null}

      <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
        <h2 className="font-brand text-lg font-bold text-dl-ink">Dados do lote</h2>
        <Field id="name" label="Nome do leilão" hint="Aparece na loja. Ex.: Relógios de Coleção — Novembro" error={err("name")}>
          <input
            id="name"
            className={inputClass}
            value={name}
            onChange={(e) => setName(e.target.value)}
            maxLength={80}
            required
            disabled={!editable}
            aria-invalid={!!err("name")}
            aria-describedby={describedBy("name", err("name"), true)}
          />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field id="date" label="Data" error={err("starts_at")}>
            <input
              id="date"
              type="date"
              className={inputClass}
              value={date}
              onChange={(e) => setDate(e.target.value)}
              required
              disabled={!editable}
              aria-invalid={!!err("starts_at")}
              aria-describedby={describedBy("date", err("starts_at"))}
            />
          </Field>
          <Field id="time" label="Hora de início" hint="Horário de Brasília">
            <input id="time" type="time" className={inputClass} value={time} onChange={(e) => setTime(e.target.value)} required disabled={!editable} aria-describedby="time-hint" />
          </Field>
        </div>
        <div className="flex flex-col gap-1.5">
          <span id="cover-label" className="text-sm font-medium text-dl-ink">
            Capa <span className="font-normal text-dl-ink-muted">(opcional)</span>
          </span>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div className="relative aspect-[16/10] w-full overflow-hidden rounded-field border border-dl-line bg-dl-page sm:w-72">
              {coverPreview ? (
                // eslint-disable-next-line @next/next/no-img-element -- pré-visualização local ou capa já processada
                <img src={coverPreview} alt="Capa do leilão" className="size-full object-cover" />
              ) : (
                <span className="flex size-full flex-col items-center justify-center gap-1 px-4 text-center text-sm text-dl-ink-muted">
                  <ImageOff aria-hidden className="size-6" />
                  Sem capa, a loja usa a foto do 1º item.
                </span>
              )}
              {coverFile ? <span className="absolute top-2 left-2 rounded-full bg-dl-copper px-2 py-0.5 text-[11px] font-semibold text-white">Nova</span> : null}
            </div>
            <div className="flex flex-col gap-2">
              <label className={buttonClass("secondary", "cursor-pointer has-focus-visible:outline-2 has-focus-visible:outline-dl-copper")}>
                {coverPreview ? <RefreshCw aria-hidden className="size-4" /> : <ImagePlus aria-hidden className="size-4" />}
                {coverPreview ? "Trocar capa" : "Escolher capa"}
                <input
                  type="file"
                  accept={COVER_TYPES.join(",")}
                  className="sr-only"
                  aria-labelledby="cover-label"
                  aria-describedby={err("cover") ? "cover-error" : "cover-hint"}
                  onChange={(e) => {
                    chooseCover(e.target.files?.[0]);
                    e.target.value = "";
                  }}
                />
              </label>
              {coverPreview ? (
                <button
                  type="button"
                  className={buttonClass("ghost", "justify-start")}
                  onClick={() => {
                    if (coverFile) setCoverFile(null);
                    else setRemoveCover(true);
                  }}
                >
                  <Trash2 aria-hidden className="size-4" />
                  {coverFile ? "Desfazer troca" : "Tirar capa"}
                </button>
              ) : null}
              {err("cover") ? (
                <p id="cover-error" role="alert" className="text-sm font-medium text-state-outbid">{err("cover")}</p>
              ) : (
                <p id="cover-hint" className="max-w-xs text-sm text-dl-ink-muted">
                  Aparece na home e nos cartões do leilão. Use uma imagem horizontal, de até 10 MB. Deixe o principal no centro: em alguns lugares as bordas são cortadas.
                </p>
              )}
              {removeCover ? <p className="text-sm text-dl-ink-muted">A capa sai quando você salvar.</p> : null}
            </div>
          </div>
        </div>
        <Field id="description" label="Descrição (opcional)" hint="Regras de pagamento, retirada, observações." error={err("description")}>
          <textarea
            id="description"
            className={`${inputClass} min-h-28 py-2`}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={5000}
            disabled={!editable}
            aria-describedby={describedBy("description", err("description"), true)}
          />
        </Field>
      </section>

      <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-brand text-lg font-bold text-dl-ink">Itens</h2>
            <p className="text-sm text-dl-ink-muted">Vão a pregão nesta ordem, um de cada vez. Os valores vêm do produto e podem ser ajustados aqui.</p>
          </div>
          {editable ? (
            <button type="button" className={buttonClass("secondary")} onClick={() => setPicking(true)}>
              <Plus aria-hidden className="size-5" />
              Adicionar produtos
            </button>
          ) : null}
        </div>

        {itemErrors.length > 0 ? (
          <Alert tone="error" title="Confira os itens">
            <ul className="list-disc pl-4">
              {itemErrors.map(([key, msgs]) => (
                <li key={key}>
                  {key.match(/^items\.(\d+)/) ? `Item ${Number(key.split(".")[1]) + 1}: ` : ""}
                  {msgs[0]}
                </li>
              ))}
            </ul>
          </Alert>
        ) : null}

        {items.length === 0 ? (
          <div className="rounded-field border border-dashed border-dl-line px-4 py-8 text-center text-sm text-dl-ink-muted">
            Nenhum item ainda. Adicione produtos disponíveis do seu catálogo.
          </div>
        ) : (
          <ol className="flex flex-col gap-2">
            {items.map((row, index) => (
              <li key={row.productId} className="flex flex-col gap-3 rounded-field border border-dl-line p-3 sm:flex-row sm:items-center">
                <div className="flex min-w-0 flex-1 items-center gap-3">
                  <span className="w-6 shrink-0 text-center font-semibold text-dl-ink-muted tabular">{index + 1}</span>
                  {row.thumb ? (
                    // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                    <img src={row.thumb} alt="" className="size-14 shrink-0 rounded-field object-cover" />
                  ) : (
                    <span className="flex size-14 shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted">
                      <ImageOff aria-hidden className="size-5" />
                    </span>
                  )}
                  <span className="line-clamp-2 min-w-0 font-medium text-dl-ink">{row.title}</span>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:w-72">
                  <label className="flex flex-col gap-1 text-xs font-medium text-dl-ink-muted">
                    Lance inicial (R$)
                    <input
                      inputMode="numeric"
                      className={`${inputClass} tabular`}
                      value={row.starting}
                      disabled={!editable}
                      aria-invalid={!!err(`items.${index}.starting_price_cents`)}
                      onChange={(e) => setItems((rows) => rows.map((r, i) => (i === index ? { ...r, starting: maskMoney(e.target.value) } : r)))}
                    />
                  </label>
                  <label className="flex flex-col gap-1 text-xs font-medium text-dl-ink-muted">
                    Incremento (R$)
                    <input
                      inputMode="numeric"
                      className={`${inputClass} tabular`}
                      value={row.increment}
                      disabled={!editable}
                      aria-invalid={!!err(`items.${index}.increment_cents`)}
                      onChange={(e) => setItems((rows) => rows.map((r, i) => (i === index ? { ...r, increment: maskMoney(e.target.value) } : r)))}
                    />
                  </label>
                </div>
                {editable ? (
                  <div className="flex justify-end gap-1">
                    <button type="button" className={iconButton} onClick={() => move(index, -1)} disabled={index === 0} aria-label={`Subir ${row.title}`}>
                      <ArrowUp aria-hidden className="size-4" />
                    </button>
                    <button type="button" className={iconButton} onClick={() => move(index, 1)} disabled={index === items.length - 1} aria-label={`Descer ${row.title}`}>
                      <ArrowDown aria-hidden className="size-4" />
                    </button>
                    <button type="button" className={iconButton} onClick={() => setItems((rows) => rows.filter((_, i) => i !== index))} aria-label={`Tirar ${row.title} do lote`}>
                      <Trash2 aria-hidden className="size-4" />
                    </button>
                  </div>
                ) : null}
              </li>
            ))}
          </ol>
        )}
      </section>

      <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
        <div>
          <h2 className="font-brand text-lg font-bold text-dl-ink">Pregão</h2>
          <p className="text-sm text-dl-ink-muted">Os valores sugeridos funcionam bem para a maioria dos leilões.</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField id="duration" label="Duração de cada item" value={settings.item_duration_s} options={DURATIONS} disabled={!editable} error={err("settings.item_duration_s")} onChange={(v) => setSettings({ ...settings, item_duration_s: v })} />
          <SelectField id="gap" label="Intervalo entre itens" value={settings.gap_s} options={GAPS} disabled={!editable} error={err("settings.gap_s")} onChange={(v) => setSettings({ ...settings, gap_s: v })} />
          <SelectField
            id="window"
            label="Prorroga se houver lance nos últimos"
            value={settings.extend_window_s}
            options={WINDOWS}
            format={(s) => (s === 0 ? "Não prorrogar" : seconds(s))}
            disabled={!editable}
            error={err("settings.extend_window_s")}
            onChange={(v) => setSettings({ ...settings, extend_window_s: v })}
          />
          <SelectField
            id="extend"
            label="O tempo volta para"
            value={settings.extend_by_s}
            options={EXTENSIONS}
            disabled={!editable || settings.extend_window_s === 0}
            error={err("settings.extend_by_s")}
            onChange={(v) => setSettings({ ...settings, extend_by_s: v })}
          />
        </div>

        <fieldset className="flex flex-col gap-3 border-t border-dl-line pt-4" disabled={!editable}>
          <legend className="sr-only">Chat do auditório</legend>
          <label className="flex min-h-11 items-center gap-3 font-medium text-dl-ink">
            <input type="checkbox" className="size-5 accent-dl-copper" checked={settings.chat_enabled} onChange={(e) => setSettings({ ...settings, chat_enabled: e.target.checked })} />
            Chat ligado no auditório
          </label>
          {settings.chat_enabled ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <RadioGroup
                name="chat_visibility"
                label="Quem vê o chat"
                value={settings.chat_visibility}
                options={[
                  ["everyone", "Todos os visitantes"],
                  ["logged_in", "Só quem entrou na conta"],
                ]}
                onChange={(v) => setSettings({ ...settings, chat_visibility: v as AuctionSettings["chat_visibility"] })}
              />
              <RadioGroup
                name="chat_write"
                label="Quem escreve"
                value={settings.chat_write}
                options={[
                  ["logged_in", "Quem entrou na conta"],
                  ["eligible", "Só com cadastro completo"],
                ]}
                onChange={(v) => setSettings({ ...settings, chat_write: v as AuctionSettings["chat_write"] })}
              />
            </div>
          ) : null}
        </fieldset>
      </section>

      <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
        <div>
          <h2 className="font-brand text-lg font-bold text-dl-ink">Taxa do leilão</h2>
          <p className="text-sm text-dl-ink-muted">
            Cobrada só sobre os itens arrematados, pela faixa do valor.{" "}
            <Link href="/painel/configuracoes/pagamento" className="font-medium text-dl-copper underline-offset-4 hover:underline">
              Ver as faixas
            </Link>
          </p>
        </div>
        <fieldset className="flex flex-col gap-2" disabled={!editable}>
          <legend className="mb-2 text-sm font-medium text-dl-ink">Quem paga a taxa</legend>
          <div className="grid gap-1 rounded-field bg-dl-page p-1 sm:grid-cols-2">
            {FEE_PAYERS.map((option) => (
              <label
                key={option.value}
                className="flex min-h-11 cursor-pointer flex-col justify-center rounded-field px-4 py-2.5 text-dl-ink-muted transition-colors hover:text-dl-ink has-checked:bg-dl-card has-checked:text-dl-ink has-checked:shadow-sm has-focus-visible:outline-2 has-focus-visible:outline-dl-copper has-disabled:cursor-not-allowed"
              >
                <input
                  type="radio"
                  name="fee_payer"
                  value={option.value}
                  className="sr-only"
                  checked={(settings.fee_payer ?? "auctioneer") === option.value}
                  onChange={() => setSettings({ ...settings, fee_payer: option.value })}
                />
                <span className="font-semibold">{option.title}</span>
                <span className="text-sm text-dl-ink-muted">{option.text}</span>
              </label>
            ))}
          </div>
          {err("settings.fee_payer") ? (
            <p role="alert" className="text-sm font-medium text-state-outbid">
              {err("settings.fee_payer")}
            </p>
          ) : (
            <p className="text-sm text-dl-ink-muted">Nos dois casos, a taxa é debitada no seu cartão. Ao repassar, você cobra a taxa do vencedor junto com o arremate.</p>
          )}
        </fieldset>
      </section>

      {status === "draft" && (!hasPaymentCard || err("payment_card")) ? (
        <Alert tone="info" title="Para agendar, cadastre um cartão de crédito">
          <p>
            Cadastro e uso são grátis: a taxa dos itens arrematados é debitada nele depois do leilão. Você pode salvar o rascunho antes.
          </p>
          <Link href="/painel/configuracoes/pagamento" className={buttonClass("secondary", "mt-3")}>
            <CreditCard aria-hidden className="size-4" />
            Cadastrar cartão
          </Link>
        </Alert>
      ) : null}

      {editable ? (
        <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-2 border-t border-dl-line bg-dl-page/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-card sm:border">
          {status === "draft" ? (
            <button type="button" className={buttonClass("primary")} onClick={() => void onSchedule()} disabled={!!busy}>
              <CalendarCheck aria-hidden className="size-5" />
              {busy === "schedule" ? "Agendando…" : "Salvar e agendar"}
            </button>
          ) : null}
          <button type="submit" className={buttonClass(status === "draft" ? "secondary" : "primary")} disabled={!!busy}>
            {busy === "save" ? "Salvando…" : status === "draft" ? "Salvar rascunho" : "Salvar alterações"}
          </button>
          <span className="flex-1" />
          {status === "scheduled" ? (
            <button type="button" className={buttonClass("ghost")} onClick={() => void onAction("unschedule")} disabled={!!busy}>
              <Undo2 aria-hidden className="size-4" />
              Voltar para rascunho
            </button>
          ) : null}
          {auction && status === "scheduled" ? (
            <button type="button" className={buttonClass("ghost", "text-state-outbid")} onClick={() => void onAction("cancel")} disabled={!!busy}>
              Cancelar lote
            </button>
          ) : null}
          {auction && status === "draft" ? (
            <button type="button" className={buttonClass("ghost", "text-state-outbid")} onClick={() => void onAction("delete")} disabled={!!busy}>
              <Trash2 aria-hidden className="size-4" />
              Apagar
            </button>
          ) : null}
        </div>
      ) : null}

      {picking ? (
        <ProductPicker
          auctionId={auction?.id ?? null}
          exclude={items.map((row) => row.productId)}
          onClose={() => setPicking(false)}
          onAdd={(products) => {
            setItems((rows) => [
              ...rows,
              ...products.map((p) => ({
                productId: p.id,
                title: p.title,
                thumb: p.photos[0]?.thumb ?? null,
                starting: centsToInput(p.starting_price_cents),
                increment: centsToInput(p.increment_cents),
              })),
            ]);
            setPicking(false);
          }}
        />
      ) : null}
    </form>
  );
}

const iconButton =
  "flex size-11 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-dl-ink disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-dl-copper";

function SelectField({
  id,
  label,
  value,
  options,
  onChange,
  disabled,
  error,
  format = seconds,
}: {
  id: string;
  label: string;
  value: number;
  options: number[];
  onChange: (value: number) => void;
  disabled?: boolean;
  error?: string;
  format?: (s: number) => string;
}) {
  const all = options.includes(value) ? options : [...options, value].sort((a, b) => a - b);
  return (
    <Field id={id} label={label} error={error}>
      <select id={id} className={inputClass} value={value} disabled={disabled} onChange={(e) => onChange(Number(e.target.value))} aria-invalid={!!error}>
        {all.map((s) => (
          <option key={s} value={s}>
            {format(s)}
          </option>
        ))}
      </select>
    </Field>
  );
}

function RadioGroup({ name, label, value, options, onChange }: { name: string; label: string; value: string; options: [string, string][]; onChange: (v: string) => void }) {
  return (
    <fieldset className="flex flex-col gap-1">
      <legend className="mb-1 text-sm font-medium text-dl-ink">{label}</legend>
      {options.map(([key, text]) => (
        <label key={key} className="flex min-h-11 items-center gap-3 text-dl-ink">
          <input type="radio" name={name} className="size-5 accent-dl-copper" checked={value === key} onChange={() => onChange(key)} />
          {text}
        </label>
      ))}
    </fieldset>
  );
}

/** Produtos disponíveis (ou sem venda) que não estão em outro lote. */
function ProductPicker({ auctionId, exclude, onAdd, onClose }: { auctionId: string | null; exclude: string[]; onAdd: (p: Product[]) => void; onClose: () => void }) {
  const [products, setProducts] = useState<Product[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [q, setQ] = useState("");
  const [selected, setSelected] = useState<string[]>([]);

  useEffect(() => {
    const query = new URLSearchParams(auctionId ? { auction: auctionId } : {});
    void panelRequest<{ data: Product[] }>(`auctions/selectable-products?${query}`).then((r) => {
      if (r.ok && r.data) setProducts(r.data.data);
      else setError(r.message ?? "Não foi possível carregar os produtos.");
    });
  }, [auctionId]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  const visible = useMemo(
    () => (products ?? []).filter((p) => !exclude.includes(p.id) && p.title.toLowerCase().includes(q.trim().toLowerCase())),
    [products, exclude, q],
  );

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-dl-ink/40 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="picker-title">
      <div className="flex max-h-[90dvh] w-full max-w-2xl flex-col rounded-t-card bg-dl-card shadow-xl sm:rounded-card">
        <div className="flex items-center justify-between gap-3 border-b border-dl-line p-4">
          <h2 id="picker-title" className="font-brand text-lg font-bold text-dl-ink">
            Adicionar produtos
          </h2>
          <button type="button" className={iconButton} onClick={onClose} aria-label="Fechar">
            <X aria-hidden className="size-5" />
          </button>
        </div>
        <div className="border-b border-dl-line p-4">
          <label className="relative block">
            <span className="sr-only">Buscar produto</span>
            <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-dl-ink-muted" />
            <input className={`${inputClass} pl-9`} type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar pelo título" autoFocus />
          </label>
        </div>
        <div className="flex-1 overflow-y-auto p-2">
          {error ? <p className="p-4 text-sm text-state-outbid">{error}</p> : null}
          {!products && !error ? <p className="p-4 text-sm text-dl-ink-muted">Carregando…</p> : null}
          {products && visible.length === 0 ? (
            <p className="p-4 text-sm text-dl-ink-muted">
              Nenhum produto disponível. Só entram produtos com status &ldquo;Disponível&rdquo; (ou que não foram arrematados) e que não estão em outro lote.
            </p>
          ) : null}
          <ul>
            {visible.map((p) => {
              const checked = selected.includes(p.id);
              return (
                <li key={p.id}>
                  <label className="flex min-h-16 cursor-pointer items-center gap-3 rounded-field p-2 hover:bg-dl-page">
                    <input
                      type="checkbox"
                      className="size-5 shrink-0 accent-dl-copper"
                      checked={checked}
                      onChange={() => setSelected((s) => (checked ? s.filter((id) => id !== p.id) : [...s, p.id]))}
                    />
                    {p.photos[0]?.thumb ? (
                      // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                      <img src={p.photos[0].thumb} alt="" className="size-12 shrink-0 rounded-field object-cover" />
                    ) : (
                      <span className="size-12 shrink-0 rounded-field bg-dl-page" />
                    )}
                    <span className="min-w-0 flex-1">
                      <span className="line-clamp-1 font-medium text-dl-ink">{p.title}</span>
                      <span className="text-sm text-dl-ink-muted tabular">
                        {formatCents(p.starting_price_cents)} · incremento {formatCents(p.increment_cents)}
                      </span>
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        </div>
        <div className="flex justify-end gap-2 border-t border-dl-line p-4">
          <button type="button" className={buttonClass("secondary")} onClick={onClose}>
            Cancelar
          </button>
          <button type="button" className={buttonClass("primary")} disabled={selected.length === 0} onClick={() => onAdd((products ?? []).filter((p) => selected.includes(p.id)))}>
            Adicionar {selected.length > 0 ? `(${selected.length})` : ""}
          </button>
        </div>
      </div>
    </div>
  );
}
