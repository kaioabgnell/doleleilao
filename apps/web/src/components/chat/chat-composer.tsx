"use client";

import { lazy, Suspense, useEffect, useRef, useState } from "react";
import { SendHorizontal, Smile, X } from "lucide-react";
import { chatTheme, type ChatTone } from "./chat-theme";
import type { ChatMessage, SendResult } from "./use-chat";

const EmojiPicker = lazy(() => import("./emoji-picker"));

export const MAX_LENGTH = 500;

/**
 * Campo de escrita do chat: uma linha que cresce, Enter envia (Shift+Enter quebra a linha), emojis
 * carregados só quando abertos (RF-F07) e a citação da mensagem respondida (RF-F06).
 */
export function ChatComposer({
  tone,
  onSend,
  replyTo = null,
  onCancelReply,
  placeholder = "Escreva uma mensagem",
  label = "Mensagem",
  id,
  autoFocus = false,
}: {
  tone: ChatTone;
  onSend: (body: string, replyTo: string | null) => Promise<SendResult>;
  replyTo?: ChatMessage | null;
  onCancelReply?: () => void;
  placeholder?: string;
  label?: string;
  id: string;
  autoFocus?: boolean;
}) {
  const t = chatTheme[tone];
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const [text, setText] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [emojiOpen, setEmojiOpen] = useState(false);

  // Responder: o foco vai para o campo.
  const replyId = replyTo?.id ?? null;
  useEffect(() => {
    if (replyId) inputRef.current?.focus();
  }, [replyId]);

  useEffect(() => {
    const el = inputRef.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 112)}px`;
    el.style.overflowY = el.scrollHeight > 112 ? "auto" : "hidden";
  }, [text]);

  async function submit() {
    const body = text.trim();
    if (!body || sending) return;
    setSending(true);
    setError(null);
    const result = await onSend(body, replyTo?.id ?? null);
    setSending(false);
    if (result.ok) {
      setText("");
      setEmojiOpen(false);
      onCancelReply?.();
      inputRef.current?.focus();
    } else {
      setError(result.error);
    }
  }

  function insertEmoji(emoji: string) {
    const el = inputRef.current;
    const start = el?.selectionStart ?? text.length;
    const end = el?.selectionEnd ?? text.length;
    const next = (text.slice(0, start) + emoji + text.slice(end)).slice(0, MAX_LENGTH);
    setText(next);
    requestAnimationFrame(() => {
      el?.focus();
      el?.setSelectionRange(start + emoji.length, start + emoji.length);
    });
  }

  return (
    <form
      className="relative flex flex-col gap-2 p-2"
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
    >
      {emojiOpen ? (
        <div className="absolute inset-x-2 bottom-full z-10 mb-2">
          <Suspense fallback={<div className={`h-16 rounded-card border ${t.box}`} />}>
            <EmojiPicker tone={tone} onPick={insertEmoji} />
          </Suspense>
        </div>
      ) : null}
      {replyTo ? (
        <div className={`flex items-center gap-2 rounded-field px-3 py-1.5 text-sm ${t.bubble}`}>
          <p className={`min-w-0 flex-1 truncate ${t.muted}`}>
            <span className={`font-semibold ${t.link}`}>Respondendo a {replyTo.author.nickname}</span>
            {replyTo.body ? <> · {replyTo.body}</> : null}
          </p>
          <button type="button" onClick={onCancelReply} className={`flex size-9 shrink-0 items-center justify-center rounded-field ${t.muted}`} aria-label="Cancelar resposta">
            <X aria-hidden className="size-4" />
          </button>
        </div>
      ) : null}
      {error ? (
        <p role="alert" className="px-1 text-sm font-medium text-state-outbid">
          {error}
        </p>
      ) : null}
      <div className="flex items-end gap-1.5">
        <button
          type="button"
          onClick={() => setEmojiOpen((o) => !o)}
          className={`flex size-11 shrink-0 items-center justify-center rounded-field transition-colors ${t.muted} ${t.hover}`}
          aria-label="Emojis"
          aria-expanded={emojiOpen}
        >
          <Smile aria-hidden className="size-5" />
        </button>
        <label htmlFor={id} className="sr-only">
          {label}
        </label>
        <textarea
          id={id}
          ref={inputRef}
          rows={1}
          maxLength={MAX_LENGTH}
          value={text}
          autoFocus={autoFocus}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
              e.preventDefault();
              void submit();
            }
            if (e.key === "Escape" && replyTo) onCancelReply?.();
          }}
          placeholder={placeholder}
          className={`block min-h-11 flex-1 resize-none rounded-field border px-3 py-2.5 text-base focus:outline-2 focus:outline-offset-0 ${t.input}`}
        />
        <button
          type="submit"
          disabled={!text.trim() || sending}
          className={`flex size-11 shrink-0 items-center justify-center rounded-field transition-colors disabled:opacity-50 ${t.send}`}
          aria-label="Enviar"
        >
          <SendHorizontal aria-hidden className="size-5" />
        </button>
      </div>
      {text.length > 400 ? (
        <p className={`px-1 text-right text-xs tabular ${t.muted}`}>
          {text.length}/{MAX_LENGTH}
        </p>
      ) : null}
    </form>
  );
}
