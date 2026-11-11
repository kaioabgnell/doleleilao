"use client";

import Link from "next/link";
import { useCallback, useState } from "react";
import { ChevronUp, MessageCircle } from "lucide-react";
import { ChatView } from "@/components/chat/chat-view";
import { useChat, type ChatPage, type SendResult } from "@/components/chat/use-chat";
import type { RealtimeClient } from "@/lib/realtime";

export type AuditoriumChat = ReturnType<typeof useAuditoriumChat>;

/** Chat do auditório pela BFF da loja (o token do participante, se houver, vai junto). */
export function useAuditoriumChat(client: RealtimeClient | null, base: string, auctionSlug: string) {
  const load = useCallback(
    async (before?: string): Promise<ChatPage | null> => {
      const response = await fetch(`${base}/chat/${auctionSlug}${before ? `?before=${before}` : ""}`, { cache: "no-store" }).catch(() => null);
      return response?.ok ? ((await response.json()) as ChatPage) : null;
    },
    [base, auctionSlug],
  );
  const post = useCallback(
    async (body: string, replyTo: string | null): Promise<SendResult> => {
      const response = await fetch(`${base}/chat/${auctionSlug}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ body, reply_to_id: replyTo }),
      }).catch(() => null);
      const json = (await response?.json().catch(() => null)) as (SendResult & { detail?: string; reason?: string }) | null;
      if (response?.ok && json) return { ok: true, message: json as never };
      return { ok: false, status: response?.status ?? 0, error: json?.detail ?? "Sem conexão. A mensagem não foi enviada.", reason: json?.reason };
    },
    [base, auctionSlug],
  );
  return useChat({ client, load, post });
}

/** Aviso no lugar do campo de texto: por que não dá para escrever, e o caminho para resolver. */
function Notice({ chat, slug, here }: { chat: AuditoriumChat; slug: string; here: string }) {
  const access = chat.page?.access;
  if (!access?.message) return null;
  if (access.reason === "login") {
    return (
      <>
        <Link href={`/${slug}/entrar?voltar=${encodeURIComponent(here)}`} className="font-semibold text-store-link underline-offset-4 hover:underline">
          Entre na sua conta
        </Link>{" "}
        para escrever no chat.
      </>
    );
  }
  return <>{access.message}</>;
}

export function ChatPanel({ chat, slug, here, meId, className = "", onClose }: { chat: AuditoriumChat; slug: string; here: string; meId: string | null; className?: string; onClose?: () => void }) {
  const access = chat.page?.access;
  if (chat.loaded && access && !access.enabled) return null;
  if (chat.loaded && access?.reason === "expired") {
    return (
      <p className="flex items-center gap-2 rounded-card border border-store-border bg-store-surface px-4 py-3 text-sm text-store-text-muted">
        <MessageCircle aria-hidden className="size-4 shrink-0" />
        {access.message}
      </p>
    );
  }
  if (chat.loaded && access && !access.can_read) {
    return (
      <div className={`flex flex-col items-center justify-center gap-2 rounded-card border border-store-border bg-store-surface p-6 text-center text-sm text-store-text-muted ${className}`}>
        <MessageCircle aria-hidden className="size-6 text-store-link" />
        {access.reason === "banned" ? (
          access.message
        ) : (
          <p>
            <Link href={`/${slug}/entrar?voltar=${encodeURIComponent(here)}`} className="font-semibold text-store-link underline-offset-4 hover:underline">
              Entre na sua conta
            </Link>{" "}
            para ver o chat deste leilão.
          </p>
        )}
      </div>
    );
  }
  return (
    <ChatView
      tone="store"
      className={className}
      messages={chat.messages}
      hasMore={chat.hasMore}
      loaded={chat.loaded}
      meId={meId}
      canWrite={access?.can_write ?? false}
      disabledNotice={<Notice chat={chat} slug={slug} here={here} />}
      onSend={chat.send}
      onLoadOlder={chat.loadOlder}
      onClose={onClose}
    />
  );
}

/**
 * Celular (RF-F05, Fase 4): o chat fica recolhido numa barra acima do botão de lance e abre por cima
 * da página, nunca por cima do botão.
 */
export function MobileChatDock({ chat, slug, here, meId }: { chat: AuditoriumChat; slug: string; here: string; meId: string | null }) {
  const [open, setOpen] = useState(false);
  const [seen, setSeen] = useState<number | null>(null);
  const access = chat.page?.access;
  if (!chat.loaded || (access && !access.enabled)) return null;

  const total = chat.messages.length;
  const unread = open ? 0 : Math.max(0, total - (seen ?? chat.initialCount));
  const last = chat.messages.at(-1);

  if (open) {
    return (
      <ChatPanel
        chat={chat}
        slug={slug}
        here={here}
        meId={meId}
        className="h-[min(55dvh,28rem)] lg:hidden"
        onClose={() => {
          setSeen(total);
          setOpen(false);
        }}
      />
    );
  }

  return (
    <button
      type="button"
      onClick={() => setOpen(true)}
      className="flex min-h-11 w-full items-center gap-2 rounded-field border border-store-border px-3 text-left text-sm lg:hidden"
      aria-expanded={false}
    >
      <MessageCircle aria-hidden className="size-4 shrink-0 text-store-link" />
      <span className="min-w-0 flex-1 truncate text-store-text-muted">
        {last ? (
          <>
            <strong className="font-semibold text-store-text">{last.author.nickname}:</strong> {last.deleted ? "mensagem apagada" : last.body}
          </>
        ) : (
          "Chat do leilão"
        )}
      </span>
      {unread > 0 ? <span className="rounded-full bg-store-primary px-2 text-xs font-bold text-store-on-primary tabular">{unread > 99 ? "99+" : unread}</span> : null}
      <ChevronUp aria-hidden className="size-4 shrink-0 text-store-text-muted" />
    </button>
  );
}
