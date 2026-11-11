"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Download, FileText, ImageOff, Mail, MessageCircle, NotebookPen, Search, Trophy } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { Modal } from "@/components/ui/dialog";
import { inputClass } from "@/components/ui/field";
import type { Award, AwardList, AwardStatus, WinnerTemplate } from "@/lib/api-types";
import { formatCents } from "@/lib/money";
import { panelRequest } from "@/lib/panel-client";

/** Ponto de cor ao lado do status: pendente em vermelho, andando em cobre, pago em verde, encerrado em cinza. */
const statusDot: Record<AwardStatus, string> = {
  awaiting_contact: "bg-state-outbid",
  negotiating: "bg-dl-copper",
  paid: "bg-state-winning",
  delivered: "bg-state-closed",
  withdrawn: "bg-dl-line",
};

const dateTime = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

type Notice = { tone: "success" | "error"; text: string } | null;

export function AwardsBoard({ list, template, filters }: { list: AwardList; template: WinnerTemplate; filters: { auction: string; status: string; q: string } }) {
  const router = useRouter();
  const [notice, setNotice] = useState<Notice>(null);
  const [messaging, setMessaging] = useState<Award | null>(null);
  const [editingTemplate, setEditingTemplate] = useState(false);
  const total = Object.values(list.counts).reduce((a, b) => a + (b ?? 0), 0);
  const open = (list.counts.awaiting_contact ?? 0) + (list.counts.negotiating ?? 0) + (list.counts.paid ?? 0);

  const href = (next: Partial<typeof filters>) => {
    const f = { ...filters, ...next };
    const query = new URLSearchParams({ ...(f.auction && { lote: f.auction }), ...(f.status !== "open" && { status: f.status }), ...(f.q && { q: f.q }) });
    return `/painel/arrematacoes${query.size ? `?${query}` : ""}`;
  };
  const tabs: { key: string; label: string; count: number }[] = [
    { key: "open", label: "Em aberto", count: open },
    ...list.statuses.map((s) => ({ key: s.value, label: s.label, count: list.counts[s.value] ?? 0 })),
    { key: "all", label: "Todas", count: total },
  ];

  return (
    <div className="flex flex-col gap-4">
      {notice ? <Alert tone={notice.tone} title={notice.text} /> : null}

      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-col gap-3 sm:flex-row">
          <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink sm:w-72">
            Lote
            <select className={inputClass} value={filters.auction} onChange={(e) => router.push(href({ auction: e.target.value }))}>
              <option value="">Todos os lotes</option>
              {list.auctions.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
          </label>
          <form role="search" className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink sm:w-72" action="/painel/arrematacoes">
            <label htmlFor="q">Buscar</label>
            {filters.auction ? <input type="hidden" name="lote" value={filters.auction} /> : null}
            {filters.status !== "open" ? <input type="hidden" name="status" value={filters.status} /> : null}
            <span className="relative">
              <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-dl-ink-muted" />
              <input id="q" name="q" type="search" defaultValue={filters.q} placeholder="Item ou arrematante" className={`${inputClass} pl-9`} />
            </span>
          </form>
        </div>
        <div className="flex flex-wrap gap-2">
          <button type="button" className={buttonClass("secondary")} onClick={() => setEditingTemplate(true)}>
            <FileText aria-hidden className="size-4" />
            Modelo da mensagem
          </button>
          <a href={`/api/painel/arrematacoes/csv${filters.auction ? `?lote=${filters.auction}` : ""}`} className={buttonClass("secondary")} download>
            <Download aria-hidden className="size-4" />
            Exportar planilha
          </a>
        </div>
      </div>

      <nav aria-label="Filtrar por status" className="flex gap-1 overflow-x-auto">
        {tabs.map((tab) => {
          const active = filters.status === tab.key;
          return (
            <Link
              key={tab.key}
              href={href({ status: tab.key })}
              aria-current={active ? "page" : undefined}
              className={`flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium ${active ? "bg-dl-ink text-white" : "bg-dl-card text-dl-ink-muted hover:text-dl-ink"}`}
            >
              {tab.label} <span className="tabular opacity-70">{tab.count}</span>
            </Link>
          );
        })}
      </nav>

      {list.data.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-12 text-center">
          <Trophy aria-hidden className="size-8 text-dl-copper-icon" />
          <p className="font-semibold text-dl-ink">{total === 0 ? "Nenhuma arrematação ainda" : "Nada por aqui"}</p>
          <p className="max-w-sm text-sm text-dl-ink-muted">
            {total === 0 ? "Quando um item for arrematado num leilão, ele aparece aqui para você falar com o vencedor." : "Troque o filtro para ver as outras."}
          </p>
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {list.data.map((award) => (
            <li key={award.id}>
              <AwardCard award={award} statuses={list.statuses} onMessage={() => setMessaging(award)} onNotice={setNotice} />
            </li>
          ))}
        </ul>
      )}

      {messaging ? (
        <MessageModal
          award={messaging}
          onClose={() => setMessaging(null)}
          onSent={(text) => {
            setMessaging(null);
            setNotice({ tone: "success", text });
            router.refresh();
          }}
        />
      ) : null}
      {editingTemplate ? (
        <TemplateModal
          template={template}
          onClose={() => setEditingTemplate(false)}
          onSaved={() => {
            setEditingTemplate(false);
            setNotice({ tone: "success", text: "Modelo da mensagem salvo." });
            router.refresh();
          }}
        />
      ) : null}
    </div>
  );
}

function AwardCard({
  award,
  statuses,
  onMessage,
  onNotice,
}: {
  award: Award;
  statuses: AwardList["statuses"];
  onMessage: () => void;
  onNotice: (n: Notice) => void;
}) {
  const router = useRouter();
  const [status, setStatus] = useState(award.status);
  const [notesOpen, setNotesOpen] = useState(false);
  const [notes, setNotes] = useState(award.notes ?? "");
  const [saving, setSaving] = useState(false);
  const last = award.messages.at(-1);

  async function patch(body: object) {
    setSaving(true);
    const result = await panelRequest<Award>(`awards/${award.id}`, { method: "PATCH", body });
    setSaving(false);
    if (!result.ok) onNotice({ tone: "error", text: result.message ?? "Não foi possível salvar." });
    return result.ok;
  }

  return (
    <article className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:flex-row">
      {award.item.thumb ? (
        // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
        <img src={award.item.thumb} alt="" className="size-20 shrink-0 rounded-field object-cover" />
      ) : (
        <span className="flex size-20 shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted">
          <ImageOff aria-hidden className="size-6" />
        </span>
      )}

      <div className="flex min-w-0 flex-1 flex-col gap-3">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <h2 className="font-semibold text-dl-ink">{award.item.title}</h2>
            <p className="text-sm text-dl-ink-muted">
              {award.auction.name} · item {award.item.position} · <span className="tabular">{dateTime.format(new Date(award.awarded_at))}</span>
            </p>
          </div>
          <p className="text-xl font-bold text-dl-ink tabular">{formatCents(award.amount_cents)}</p>
        </div>

        <div className="grid gap-3 text-sm sm:grid-cols-[1fr_auto] sm:items-end">
          <div className="flex flex-col gap-0.5">
            <p className="font-medium text-dl-ink">
              {award.bidder.name} <span className="font-normal text-dl-ink-muted">({award.bidder.nickname})</span>
            </p>
            <p className="flex flex-wrap gap-x-3 text-dl-ink-muted">
              <a href={`tel:+55${award.bidder.phone.replace(/\D/g, "")}`} className="tabular hover:text-dl-copper">
                {award.bidder.phone}
              </a>
              <a href={`mailto:${award.bidder.email}`} className="break-all hover:text-dl-copper">
                {award.bidder.email}
              </a>
              <span className="tabular">CPF {award.bidder.cpf_masked}</span>
            </p>
          </div>
          <label className="flex items-center gap-2 text-sm font-medium text-dl-ink">
            <span className="sr-only">Status</span>
            <span aria-hidden className={`size-2.5 shrink-0 rounded-full ${statusDot[status]}`} />
            <select
              className={`${inputClass} min-w-48 font-semibold`}
              value={status}
              disabled={saving}
              onChange={async (e) => {
                const next = e.target.value as AwardStatus;
                const previous = status;
                setStatus(next);
                if (!(await patch({ status: next }))) setStatus(previous);
                else router.refresh();
              }}
            >
              {statuses.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </label>
        </div>

        {notesOpen ? (
          <form
            className="flex flex-col gap-2"
            onSubmit={async (e) => {
              e.preventDefault();
              if (await patch({ notes })) {
                setNotesOpen(false);
                router.refresh();
              }
            }}
          >
            <label htmlFor={`notes-${award.id}`} className="text-sm font-medium text-dl-ink">
              Observações (só você vê)
            </label>
            <textarea id={`notes-${award.id}`} className={`${inputClass} min-h-20 py-2`} value={notes} maxLength={2000} onChange={(e) => setNotes(e.target.value)} autoFocus />
            <div className="flex gap-2">
              <button type="submit" className={buttonClass("primary")} disabled={saving}>
                Salvar
              </button>
              <button type="button" className={buttonClass("ghost")} onClick={() => setNotesOpen(false)}>
                Cancelar
              </button>
            </div>
          </form>
        ) : award.notes ? (
          <p className="rounded-field bg-dl-page px-3 py-2 text-sm whitespace-pre-line text-dl-ink">{award.notes}</p>
        ) : null}

        <div className="flex flex-wrap items-center gap-2 border-t border-dl-line pt-3">
          <button type="button" className={buttonClass("primary")} onClick={onMessage}>
            <MessageCircle aria-hidden className="size-4" />
            Mensagem ao vencedor
          </button>
          {!notesOpen ? (
            <button type="button" className={buttonClass("ghost")} onClick={() => setNotesOpen(true)}>
              <NotebookPen aria-hidden className="size-4" />
              {award.notes ? "Editar observações" : "Observações"}
            </button>
          ) : null}
          <span className="ml-auto text-xs text-dl-ink-muted">
            {last ? `${award.messages.length} ${award.messages.length === 1 ? "mensagem" : "mensagens"} · última em ${dateTime.format(new Date(last.sent_at))}` : "Nenhuma mensagem enviada"}
          </span>
        </div>
      </div>
    </article>
  );
}

function MessageModal({ award, onClose, onSent }: { award: Award; onClose: () => void; onSent: (text: string) => void }) {
  const [body, setBody] = useState<string | null>(null);
  const [busy, setBusy] = useState<"email" | "whatsapp" | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Texto pronto: o modelo da loja preenchido com esta arrematação.
  useEffect(() => {
    void (async () => {
      const r = await panelRequest<{ body: string }>(`awards/${award.id}/message`);
      setBody(r.ok && r.data ? r.data.body : "");
    })();
  }, [award.id]);

  async function send(channel: "email" | "whatsapp") {
    if (!body?.trim()) return;
    // A janela do WhatsApp abre já no clique (o navegador bloqueia janelas abertas depois de esperar a API).
    const popup = channel === "whatsapp" ? window.open("about:blank", "_blank") : null;
    setBusy(channel);
    setError(null);
    const result = await panelRequest<{ whatsapp_url: string }>(`awards/${award.id}/message`, { method: "POST", body: { body, channels: [channel] } });
    setBusy(null);
    if (!result.ok || !result.data) {
      popup?.close();
      setError(result.message ?? "Não foi possível enviar.");
      return;
    }
    if (popup) popup.location.href = result.data.whatsapp_url;
    onSent(channel === "email" ? `Mensagem enviada por e-mail para ${award.bidder.nickname}.` : "WhatsApp aberto com a mensagem pronta. Ela também ficou na conta do arrematante.");
  }

  return (
    <Modal
      open
      size="lg"
      onClose={onClose}
      icon={<MessageCircle className="size-5" />}
      title={`Mensagem para ${award.bidder.name}`}
      description={`${award.item.title} · ${formatCents(award.amount_cents)}. A mensagem também aparece em “Minha conta” e no sino do arrematante.`}
    >
      {error ? <Alert tone="error" title={error} /> : null}
      <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink">
        Mensagem
        <textarea
          className={`${inputClass} min-h-56 py-2`}
          value={body ?? "Carregando…"}
          disabled={body === null}
          maxLength={2000}
          onChange={(e) => setBody(e.target.value)}
          data-autofocus
        />
      </label>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" className={buttonClass("secondary")} onClick={() => void send("whatsapp")} disabled={!body?.trim() || !!busy}>
          <MessageCircle aria-hidden className="size-4" />
          {busy === "whatsapp" ? "Abrindo…" : "Abrir no WhatsApp"}
        </button>
        <button type="button" className={buttonClass("primary")} onClick={() => void send("email")} disabled={!body?.trim() || !!busy}>
          <Mail aria-hidden className="size-4" />
          {busy === "email" ? "Enviando…" : "Enviar por e-mail"}
        </button>
      </div>
    </Modal>
  );
}

const SAMPLE: Record<string, string> = {
  nome: "Ana Paula Souza",
  apelido: "Ana S.",
  item: "Rolex Datejust 1985",
  valor: "R$ 17.000,00",
  leilao: "Relógios de Coleção",
  data: "21/11/2026",
  loja: "Sua loja",
  whatsapp: "(11) 98765-4321",
};

function TemplateModal({ template, onClose, onSaved }: { template: WinnerTemplate; onClose: () => void; onSaved: () => void }) {
  const [text, setText] = useState(template.template);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const ref = useRef<HTMLTextAreaElement>(null);
  const preview = text.replace(/\{([a-z]+)\}/g, (all, key: string) => SAMPLE[key] ?? all);

  function insert(field: string) {
    const el = ref.current;
    const start = el?.selectionStart ?? text.length;
    const end = el?.selectionEnd ?? text.length;
    const token = `{${field}}`;
    setText(text.slice(0, start) + token + text.slice(end));
    requestAnimationFrame(() => {
      el?.focus();
      el?.setSelectionRange(start + token.length, start + token.length);
    });
  }

  async function save(value: string | null) {
    setBusy(true);
    setError(null);
    const result = await panelRequest<WinnerTemplate>("winner-message-template", { method: "PUT", body: { template: value } });
    setBusy(false);
    if (result.ok) onSaved();
    else setError(result.message ?? "Não foi possível salvar.");
  }

  return (
    <Modal
      open
      size="lg"
      onClose={onClose}
      icon={<FileText className="size-5" />}
      title="Modelo da mensagem ao vencedor"
      description="Vem pronto em cada arrematação, e dá para ajustar antes de enviar. Toque num campo para inserir no texto."
    >
      {error ? <Alert tone="error" title={error} /> : null}
      <div className="flex flex-wrap gap-1.5" aria-label="Campos">
        {template.fields.map((f) => (
          <button key={f.key} type="button" onClick={() => insert(f.key)} className="min-h-9 rounded-full bg-dl-page px-3 text-xs font-semibold text-dl-copper hover:bg-dl-copper/10" title={f.label}>
            {`{${f.key}}`}
          </button>
        ))}
      </div>
      <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink">
        Texto
        <textarea ref={ref} className={`${inputClass} min-h-40 py-2`} value={text} maxLength={2000} onChange={(e) => setText(e.target.value)} data-autofocus />
      </label>
      <div className="flex flex-col gap-1.5">
        <p className="text-sm font-medium text-dl-ink">Prévia</p>
        <p className="rounded-field bg-dl-page px-3 py-2 text-sm whitespace-pre-line text-dl-ink">{preview}</p>
      </div>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        {!template.is_default ? (
          <button type="button" className={buttonClass("ghost", "sm:mr-auto")} onClick={() => void save(null)} disabled={busy}>
            Voltar ao texto padrão
          </button>
        ) : null}
        <button type="button" className={buttonClass("secondary")} onClick={onClose}>
          Cancelar
        </button>
        <button type="button" className={buttonClass("primary")} onClick={() => void save(text)} disabled={busy || !text.trim()}>
          {busy ? "Salvando…" : "Salvar modelo"}
        </button>
      </div>
    </Modal>
  );
}
