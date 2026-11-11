"use client";

import { useLayoutEffect, useRef, type ReactNode } from "react";
import { Megaphone, Trash2 } from "lucide-react";
import { chatClock, chatTheme, type ChatTone } from "./chat-theme";
import type { ChatMessage } from "./use-chat";

export function isStaff(m: ChatMessage): boolean {
  return m.author.type === "auctioneer";
}

/**
 * Destaque do leiloeiro: tudo o que ele escreve fica aqui, com a mais nova sempre no topo (a lista
 * empurra as antigas para baixo). As mesmas falas também aparecem na conversa, com fundo cinza.
 * `compact` mostra só a última mensagem (celular, acima do botão de lance); o modo completo lista
 * todas e aceita o campo de escrita do painel como `children`.
 */
export function HighlightFeed({
  tone,
  messages,
  compact = false,
  onDelete,
  children,
  className = "",
  hint,
}: {
  tone: ChatTone;
  messages: ChatMessage[];
  compact?: boolean;
  /** Painel: apagar uma mensagem do destaque. */
  onDelete?: (message: ChatMessage) => void;
  /** Painel: mensagens prontas e campo de escrita. */
  children?: ReactNode;
  className?: string;
  hint?: string;
}) {
  const t = chatTheme[tone];
  const staff = messages.filter(isStaff);
  // Mais nova primeiro.
  const newestFirst = [...staff].reverse();
  const listRef = useRef<HTMLOListElement>(null);
  const lastId = staff.at(-1)?.id;

  // Mensagem nova: a lista volta ao topo para mostrá-la.
  useLayoutEffect(() => {
    const el = listRef.current;
    if (el) el.scrollTop = 0;
  }, [lastId]);

  if (compact) {
    const last = staff.filter((m) => !m.deleted).at(-1);
    if (!last) return null;
    return (
      <div role="status" className={`flex items-start gap-2.5 rounded-card px-3 py-2.5 text-sm ${t.staff} ${t.text} ${className}`} style={t.style}>
        <Megaphone aria-hidden className={`mt-0.5 size-4 shrink-0 ${t.link}`} />
        <p className="min-w-0 flex-1 leading-snug break-words">
          <span className={`font-semibold ${t.link}`}>Leiloeiro</span> {last.body}
          <time dateTime={last.created_at} className={`ml-1.5 text-[11px] whitespace-nowrap tabular ${t.muted}`}>
            {chatClock.format(new Date(last.created_at))}
          </time>
        </p>
      </div>
    );
  }

  if (staff.length === 0 && !children) return null;

  return (
    <section aria-label="Destaque do leiloeiro" className={`flex min-h-0 flex-col overflow-hidden rounded-card border ${t.box} ${className}`} style={t.style}>
      <header className={`flex items-center gap-2 border-b px-4 py-2.5 ${t.divider}`}>
        <Megaphone aria-hidden className={`size-4 ${t.link}`} />
        <h2 className={`text-sm font-semibold ${t.text}`}>Destaque do leiloeiro</h2>
        {hint ? <span className={`ml-auto text-xs ${t.muted}`}>{hint}</span> : null}
      </header>
      <ol ref={listRef} className="flex max-h-56 min-h-0 flex-col gap-1.5 overflow-y-auto overscroll-contain px-3 py-3" aria-live="polite" aria-relevant="additions">
        {staff.length === 0 ? <li className={`py-3 text-center text-sm ${t.muted}`}>Nada no destaque ainda. Toque numa mensagem pronta ou escreva abaixo.</li> : null}
        {newestFirst.map((m, i) => (
          <li key={m.id} className={`group flex items-start gap-2 rounded-field px-3 py-2 ${t.staff} ${i === 0 ? "motion-safe:animate-row-in" : ""}`}>
            <div className="min-w-0 flex-1 text-sm leading-snug">
              {m.reply_to ? (
                <p className={`truncate text-xs ${t.muted}`}>
                  ↩ <span className="font-semibold">{m.reply_to.author}</span>: {m.reply_to.deleted ? "mensagem apagada" : m.reply_to.excerpt}
                </p>
              ) : null}
              {m.deleted ? (
                <p className={`italic ${t.muted}`}>Mensagem apagada</p>
              ) : (
                <p className={`break-words whitespace-pre-wrap ${t.text} ${i === 0 ? "font-semibold" : ""}`}>
                  {m.body}
                  <time dateTime={m.created_at} className={`ml-1.5 text-[11px] whitespace-nowrap tabular ${t.muted}`}>
                    {chatClock.format(new Date(m.created_at))}
                  </time>
                </p>
              )}
            </div>
            {onDelete && !m.deleted ? (
              <button
                type="button"
                onClick={() => onDelete(m)}
                className={`-my-1 -mr-1.5 flex size-9 shrink-0 items-center justify-center rounded-field transition-colors ${t.muted} hover:text-state-outbid focus-visible:outline-2 focus-visible:outline-dl-copper`}
                aria-label="Apagar do destaque"
              >
                <Trash2 aria-hidden className="size-4" />
              </button>
            ) : null}
          </li>
        ))}
      </ol>
      {children}
    </section>
  );
}
