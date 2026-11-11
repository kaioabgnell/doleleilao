"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { ChatChannelEvents, ChatMessage } from "@doleleilao/realtime-contract";
import type { RealtimeClient } from "@/lib/realtime";
import { useChannel } from "@/lib/use-realtime";

export type { ChatMessage };

export interface ChatAccess {
  enabled: boolean;
  can_read: boolean;
  can_write: boolean;
  reason: string | null;
  message: string | null;
  muted_until: string | null;
  channel: { name: string; private: boolean };
}

export interface ChatPage {
  data: ChatMessage[];
  has_more: boolean;
  access?: ChatAccess;
  channel?: { name: string; private: boolean };
  muted?: Record<string, string | null>;
  banned?: string[];
}

export type SendResult = { ok: true; message: ChatMessage } | { ok: false; status: number; error: string; reason?: string };

/** Máximo de mensagens guardadas na tela (as mais antigas voltam ao rolar para cima). */
const KEEP = 300;

/**
 * Estado do chat de um lote (RF-F05): histórico, mensagens novas pelo WebSocket, envio e páginas
 * antigas. O canal (público ou privado) vem da primeira resposta da API.
 */
export function useChat({
  client,
  load,
  post,
  withNames = false,
}: {
  client: RealtimeClient | null;
  load: (before?: string) => Promise<ChatPage | null>;
  post: (body: string, replyTo: string | null) => Promise<SendResult>;
  /** Painel: o WebSocket traz só o apelido; quando chega alguém sem nome, busca a última página (com nomes). */
  withNames?: boolean;
}) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [hasMore, setHasMore] = useState(false);
  const [page, setPage] = useState<ChatPage | null>(null);
  const [loaded, setLoaded] = useState(false);
  /** Quantas mensagens já estavam lá ao abrir (não contam como novas). */
  const [initialCount, setInitialCount] = useState(0);
  const loadRef = useRef(load);
  const namesTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => {
    loadRef.current = load;
  });

  const merge = useCallback((incoming: ChatMessage[], position: "end" | "start") => {
    setMessages((current) => {
      const byId = new Map(current.map((m) => [m.id, m]));
      for (const m of incoming) byId.set(m.id, m);
      const all = [...byId.values()].sort((a, b) => a.created_at.localeCompare(b.created_at) || a.id.localeCompare(b.id));
      return position === "end" ? all.slice(-KEEP) : all.slice(0, KEEP);
    });
  }, []);

  /** Última página (na abertura e a cada reconexão do canal). */
  const refresh = useCallback(async () => {
    const result = await loadRef.current();
    if (!result) return;
    setPage(result);
    merge(result.data, "end");
    setHasMore((had) => (loaded ? had : result.has_more));
    setLoaded(true);
  }, [merge, loaded]);

  useEffect(() => {
    void (async () => {
      const result = await loadRef.current();
      if (!result) return setLoaded(true);
      setPage(result);
      setMessages(result.data);
      setInitialCount(result.data.length);
      setHasMore(result.has_more);
      setLoaded(true);
    })();
  }, []);

  const loadOlder = useCallback(async (): Promise<boolean> => {
    const first = messages[0];
    if (!first || !hasMore) return false;
    const result = await loadRef.current(first.id);
    if (!result) return false;
    merge(result.data, "start");
    setHasMore(result.has_more);
    return result.data.length > 0;
  }, [messages, hasMore, merge]);

  const channelInfo = page?.access?.channel ?? page?.channel ?? null;
  const canSubscribe = page?.access ? page.access.can_read : true;
  const channel = channelInfo && canSubscribe ? `${channelInfo.private ? "private-" : ""}${channelInfo.name}` : null;

  useChannel(
    client,
    channel,
    (event, data) => {
      if (event === "chat.message") {
        const message = data as ChatChannelEvents["chat.message"];
        merge([message], "end");
        if (withNames && message.author.type === "bidder" && !message.author.name && !namesTimer.current) {
          namesTimer.current = setTimeout(() => {
            namesTimer.current = null;
            void refresh();
          }, 400);
        }
      }
      if (event === "chat.message_deleted") {
        const { id } = data as ChatChannelEvents["chat.message_deleted"];
        setMessages((current) =>
          current.map((m) =>
            m.id === id
              ? { ...m, deleted: true, body: null }
              : m.reply_to?.id === id
                ? { ...m, reply_to: { ...m.reply_to, deleted: true, excerpt: null } }
                : m,
          ),
        );
      }
    },
    () => void refresh(),
  );

  const send = useCallback(
    async (body: string, replyTo: string | null): Promise<SendResult> => {
      const result = await post(body, replyTo);
      if (result.ok) merge([result.message], "end");
      return result;
    },
    [post, merge],
  );

  return { messages, hasMore, loaded, initialCount, page, setPage, loadOlder, send, refresh, setMessages };
}
