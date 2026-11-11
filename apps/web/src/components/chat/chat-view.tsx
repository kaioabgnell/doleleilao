"use client";

import { useCallback, useLayoutEffect, useRef, useState, type ReactNode } from "react";
import { ArrowDown, Ban, Ellipsis, Gavel, MessageCircle, MicOff, Reply, Trash2, X } from "lucide-react";
import { ChatComposer } from "./chat-composer";
import { chatClock, chatTheme, type ChatTone } from "./chat-theme";
import { isStaff } from "./highlight-feed";
import type { ChatMessage, SendResult } from "./use-chat";

export interface Moderation {
  muted: Record<string, string | null>;
  banned: string[];
  onDelete: (message: ChatMessage) => void;
  onMute: (bidderId: string, nickname: string) => void;
  onUnmute: (bidderId: string) => void;
  onBan: (bidderId: string, nickname: string) => void;
}

/**
 * Conversa do leilão (RF-F05 a RF-F08): uma linha por mensagem ("nome: texto hora"), rolagem
 * automática que pausa quando a pessoa sobe para ler, responder citando (tocar na citação rola até a
 * original) e moderação no painel. As falas do leiloeiro aparecem com fundo cinza e também ficam
 * reunidas no destaque (`HighlightFeed`). Texto puro: o React escapa e links não ficam clicáveis.
 */
export function ChatView({
  tone,
  messages,
  hasMore,
  loaded,
  meId,
  canWrite,
  disabledNotice,
  onSend,
  onLoadOlder,
  moderation,
  onReplyRequest,
  className = "",
  title = "Chat do leilão",
  onClose,
}: {
  tone: ChatTone;
  messages: ChatMessage[];
  hasMore: boolean;
  loaded: boolean;
  meId: string | null;
  canWrite: boolean;
  disabledNotice?: ReactNode;
  onSend: (body: string, replyTo: string | null) => Promise<SendResult>;
  onLoadOlder: () => Promise<boolean>;
  moderation?: Moderation;
  /** Painel: responder escreve no destaque, fora deste componente. */
  onReplyRequest?: (message: ChatMessage) => void;
  className?: string;
  title?: string;
  onClose?: () => void;
}) {
  const t = chatTheme[tone];
  // O leiloeiro também aparece na conversa, com fundo cinza; no destaque, as falas dele ficam reunidas.
  const rows = messages;
  const listRef = useRef<HTMLDivElement>(null);
  const atBottom = useRef(true);
  const keepOffset = useRef<number | null>(null);
  // Longe do fim da lista: conta as mensagens novas depois da última vista.
  const [away, setAway] = useState(false);
  const [lastSeenId, setLastSeenId] = useState<string | null>(null);
  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const [highlight, setHighlight] = useState<string | null>(null);
  const [actionsFor, setActionsFor] = useState<string | null>(null);
  const lastId = rows.at(-1)?.id;
  const lastMine = meId !== null && rows.at(-1)?.author.id === meId;

  // Mensagem nova: desce se a pessoa já estava no fim (ou se foi ela que escreveu); senão, conta.
  useLayoutEffect(() => {
    const el = listRef.current;
    if (!el) return;
    if (keepOffset.current !== null) {
      el.scrollTop = el.scrollHeight - keepOffset.current;
      keepOffset.current = null;
      return;
    }
    if (atBottom.current || lastMine) el.scrollTop = el.scrollHeight;
  }, [lastId, lastMine, rows.length]);

  function onScroll() {
    const el = listRef.current;
    if (!el) return;
    atBottom.current = el.scrollHeight - el.scrollTop - el.clientHeight < 48;
    setAway(!atBottom.current);
    if (atBottom.current) setLastSeenId(rows.at(-1)?.id ?? null);
  }

  const seenIndex = lastSeenId ? rows.findIndex((m) => m.id === lastSeenId) : -1;
  const unseen = away && seenIndex >= 0 ? rows.length - 1 - seenIndex : 0;

  async function older() {
    const el = listRef.current;
    if (el) keepOffset.current = el.scrollHeight - el.scrollTop;
    const added = await onLoadOlder();
    if (!added) keepOffset.current = null;
    return added;
  }

  /** Tocar na citação rola até a original (RF-F06), carregando páginas antigas se preciso. */
  const jumpTo = useCallback(
    async (id: string) => {
      for (let i = 0; i < 6; i++) {
        const target = listRef.current?.querySelector<HTMLElement>(`[data-message="${id}"]`);
        if (target) {
          target.scrollIntoView({ block: "center", behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
          setHighlight(id);
          setTimeout(() => setHighlight(null), 1600);
          return;
        }
        if (!hasMore || !(await older())) return;
        await new Promise((r) => setTimeout(r, 50));
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps -- `older` muda a cada render; o laço usa a versão atual
    [hasMore],
  );

  const send = useCallback(
    async (body: string, reply: string | null) => {
      const result = await onSend(body, reply);
      if (result.ok) atBottom.current = true;
      return result;
    },
    [onSend],
  );

  const canReply = canWrite || !!onReplyRequest;
  function reply(m: ChatMessage) {
    setActionsFor(null);
    if (onReplyRequest) onReplyRequest(m);
    else setReplyTo(m);
  }

  return (
    <section aria-label={title} className={`flex min-h-0 flex-col overflow-hidden rounded-card border ${t.box} ${className}`} style={t.style}>
      <header className={`flex items-center justify-between gap-2 border-b px-4 py-2.5 ${t.divider}`}>
        <h2 className={`flex items-center gap-2 text-sm font-semibold ${t.text}`}>
          <MessageCircle aria-hidden className="size-4" />
          {title}
        </h2>
        {onClose ? (
          <button type="button" onClick={onClose} className={`-mr-2 flex size-9 items-center justify-center rounded-field ${t.muted} ${t.hover}`} aria-label="Recolher o chat">
            <X aria-hidden className="size-5" />
          </button>
        ) : null}
      </header>

      <div className="relative min-h-0 flex-1">
        <div ref={listRef} onScroll={onScroll} className="h-full overflow-y-auto overscroll-contain px-2 py-2" aria-live="polite" aria-relevant="additions">
          {hasMore ? (
            <div className="mb-2 text-center">
              <button type="button" onClick={() => void older()} className={`min-h-9 rounded-full border px-3 text-xs font-semibold transition-colors ${t.chip}`}>
                Ver mensagens anteriores
              </button>
            </div>
          ) : null}
          {loaded && rows.length === 0 ? <p className={`py-8 text-center text-sm ${t.muted}`}>{canWrite ? "Nenhuma mensagem ainda. Diga oi!" : "Nenhuma mensagem ainda."}</p> : null}
          {!loaded ? <p className={`py-8 text-center text-sm ${t.muted}`}>Carregando…</p> : null}
          <ol className="flex flex-col">
            {rows.map((m) => (
              <MessageRow
                key={m.id}
                m={m}
                tone={tone}
                mine={meId !== null && m.author.id === meId}
                highlighted={highlight === m.id}
                onReply={canReply && !m.deleted ? () => reply(m) : undefined}
                onJump={jumpTo}
                moderation={moderation}
                actionsOpen={actionsFor === m.id}
                onToggleActions={() => setActionsFor((id) => (id === m.id ? null : m.id))}
              />
            ))}
          </ol>
        </div>
        {unseen > 0 ? (
          <button
            type="button"
            onClick={() => listRef.current?.scrollTo({ top: listRef.current.scrollHeight })}
            className={`absolute bottom-3 left-1/2 flex min-h-9 -translate-x-1/2 items-center gap-1 rounded-full px-3 text-xs font-semibold shadow-lg ${t.send}`}
          >
            <ArrowDown aria-hidden className="size-4" />
            {unseen === 1 ? "1 mensagem nova" : `${unseen} mensagens novas`}
          </button>
        ) : null}
      </div>

      {canWrite ? (
        <div className={`border-t ${t.divider}`}>
          <ChatComposer id={`chat-input-${tone}`} tone={tone} onSend={send} replyTo={replyTo} onCancelReply={() => setReplyTo(null)} />
        </div>
      ) : disabledNotice ? (
        <div className={`border-t px-4 py-3 text-sm ${t.muted} ${t.divider}`}>{disabledNotice}</div>
      ) : null}
    </section>
  );
}

function MessageRow({
  m,
  tone,
  mine,
  highlighted,
  onReply,
  onJump,
  moderation,
  actionsOpen,
  onToggleActions,
}: {
  m: ChatMessage;
  tone: ChatTone;
  mine: boolean;
  highlighted: boolean;
  onReply?: () => void;
  onJump: (id: string) => void;
  moderation?: Moderation;
  actionsOpen: boolean;
  onToggleActions: () => void;
}) {
  const t = chatTheme[tone];
  const staff = isStaff(m);
  const authorId = m.author.id;
  const muted = authorId !== null && moderation ? authorId in moderation.muted : false;
  const banned = authorId !== null && moderation ? moderation.banned.includes(authorId) : false;
  const realName = m.author.name && m.author.name !== m.author.nickname ? m.author.name : null;
  const hasMenu = !!moderation && !m.deleted;

  return (
    <li data-message={m.id} className={`group rounded-field transition-colors ${highlighted ? "bg-state-winning/15" : staff ? `my-0.5 ${t.staffRow}` : actionsOpen ? t.bubble : t.hover}`}>
      <div className="flex items-start gap-1 px-1.5 py-0.5">
        <p className={`min-w-0 flex-1 py-1 text-sm leading-snug break-words ${t.text}`}>
          {m.reply_to ? (
            <button
              type="button"
              onClick={() => onJump(m.reply_to!.id)}
              className={`mr-1.5 inline-flex max-w-[55%] items-baseline gap-1 rounded-sm align-baseline text-xs ${t.muted} hover:underline`}
              aria-label={`Ver a mensagem de ${m.reply_to.author} que foi respondida`}
            >
              <span aria-hidden>↩</span>
              <span className="truncate">
                <span className="font-semibold">{m.reply_to.author}</span>: {m.reply_to.deleted ? "mensagem apagada" : m.reply_to.excerpt}
              </span>
            </button>
          ) : null}
          {staff ? (
            <span className={`mr-1.5 inline-flex items-center gap-1 rounded-full px-1.5 align-[1px] text-[11px] font-semibold ${t.chipSolid}`}>
              <Gavel aria-hidden className="size-3" />
              Leiloeiro
            </span>
          ) : null}
          <span className={`font-semibold ${mine || staff ? t.text : t.link}`}>{mine ? "Você" : m.author.nickname}</span>
          {realName && !staff ? <span className={`text-xs ${t.muted}`}> {realName}</span> : null}
          {muted ? <span className="mx-1 rounded-full bg-state-closed/15 px-1.5 text-[11px] font-semibold text-state-closed">silenciado</span> : null}
          {banned ? <span className="mx-1 rounded-full bg-state-outbid/15 px-1.5 text-[11px] font-semibold text-state-outbid">banido</span> : null}
          <span className={t.muted}>: </span>
          {m.deleted ? <span className={`italic ${t.muted}`}>mensagem apagada pelo leiloeiro</span> : <span className="whitespace-pre-wrap">{m.body}</span>}
          <time dateTime={m.created_at} className={`ml-1.5 text-[11px] whitespace-nowrap tabular ${t.muted}`}>
            {chatClock.format(new Date(m.created_at))}
          </time>
        </p>
        {hasMenu ? (
          <button
            type="button"
            onClick={onToggleActions}
            className={`-my-1.5 flex size-9 shrink-0 items-center justify-center rounded-field transition-opacity ${t.muted} ${t.hover} ${actionsOpen ? "" : "opacity-60 group-hover:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100"}`}
            aria-label={`Ações para ${m.author.nickname}`}
            aria-expanded={actionsOpen}
          >
            <Ellipsis aria-hidden className="size-4" />
          </button>
        ) : onReply ? (
          <button
            type="button"
            onClick={onReply}
            className={`-my-1.5 flex size-9 shrink-0 items-center justify-center rounded-field transition-opacity ${t.muted} ${t.hover} opacity-0 group-hover:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-60`}
            aria-label={`Responder ${m.author.nickname}`}
          >
            <Reply aria-hidden className="size-4" />
          </button>
        ) : null}
      </div>
      {hasMenu && actionsOpen ? (
        <div className="flex flex-wrap gap-1.5 px-1.5 pb-2" role="group" aria-label={`Ações para ${m.author.nickname}`}>
          {onReply ? (
            <ActionChip tone={tone} onClick={onReply} icon={<Reply aria-hidden className="size-3.5" />}>
              Responder
            </ActionChip>
          ) : null}
          <ActionChip tone={tone} danger onClick={() => { onToggleActions(); moderation!.onDelete(m); }} icon={<Trash2 aria-hidden className="size-3.5" />}>
            Apagar
          </ActionChip>
          {authorId !== null && !banned && !staff ? (
            <>
              <ActionChip
                tone={tone}
                onClick={() => {
                  onToggleActions();
                  if (muted) moderation!.onUnmute(authorId);
                  else moderation!.onMute(authorId, m.author.nickname);
                }}
                icon={<MicOff aria-hidden className="size-3.5" />}
              >
                {muted ? "Liberar no chat" : "Silenciar"}
              </ActionChip>
              <ActionChip tone={tone} danger onClick={() => { onToggleActions(); moderation!.onBan(authorId, m.author.nickname); }} icon={<Ban aria-hidden className="size-3.5" />}>
                Banir da loja
              </ActionChip>
            </>
          ) : null}
        </div>
      ) : null}
    </li>
  );
}

function ActionChip({ tone, danger = false, onClick, icon, children }: { tone: ChatTone; danger?: boolean; onClick: () => void; icon: ReactNode; children: ReactNode }) {
  const t = chatTheme[tone];
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors ${danger ? "border-state-outbid/40 text-state-outbid hover:bg-state-outbid/10" : t.chip}`}
    >
      {icon}
      {children}
    </button>
  );
}
