"use client";

import { useCallback, useState } from "react";
import { Ban, MicOff, Zap } from "lucide-react";
import { ChatComposer } from "@/components/chat/chat-composer";
import { ChatView } from "@/components/chat/chat-view";
import { HighlightFeed, isStaff } from "@/components/chat/highlight-feed";
import { quickMessages, type QuickMessageContext } from "@/components/chat/quick-messages";
import { useChat, type ChatMessage, type ChatPage, type SendResult } from "@/components/chat/use-chat";
import { buttonClass } from "@/components/ui/button-styles";
import { dangerButton, Modal, useConfirm } from "@/components/ui/dialog";
import { inputClass } from "@/components/ui/field";
import { panelRequest } from "@/lib/panel-client";
import type { RealtimeClient } from "@/lib/realtime";

type Dialog = { kind: "mute" | "ban"; bidderId: string; nickname: string } | null;

/**
 * Chat no painel do pregão (RF-F08, RF-G02). Duas áreas: o **destaque**, onde só o leiloeiro escreve
 * (mensagens prontas de um toque e campo livre), e a **conversa dos participantes**, que ele lê com
 * os nomes reais e modera. Responder a alguém escreve no destaque, citando a mensagem.
 */
export function PanelChat({ client, auctionId, onError, context }: { client: RealtimeClient | null; auctionId: string; onError: (message: string) => void; context: QuickMessageContext }) {
  const load = useCallback(
    async (before?: string): Promise<ChatPage | null> => {
      const result = await panelRequest<ChatPage>(`auctions/${auctionId}/chat${before ? `?before=${before}` : ""}`);
      return result.ok && result.data ? result.data : null;
    },
    [auctionId],
  );
  const post = useCallback(
    async (body: string, replyTo: string | null): Promise<SendResult> => {
      const result = await panelRequest<ChatMessage>(`auctions/${auctionId}/chat`, { method: "POST", body: { body, reply_to_id: replyTo } });
      return result.ok && result.data ? { ok: true, message: result.data } : { ok: false, status: result.status, error: result.message ?? "Não foi possível enviar." };
    },
    [auctionId],
  );
  const chat = useChat({ client, load, post, withNames: true });
  const confirm = useConfirm();
  const [dialog, setDialog] = useState<Dialog>(null);
  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const settings = (chat.page as (ChatPage & { settings?: { enabled: boolean; open: boolean } }) | null)?.settings;
  const open = settings?.open ?? true;

  async function act(path: string, method: "PUT" | "DELETE", body?: object) {
    const result = await panelRequest(path, { method, body });
    if (!result.ok) onError(result.message ?? "Não foi possível concluir.");
    await chat.refresh();
    return result.ok;
  }

  async function remove(m: ChatMessage, own: boolean) {
    const ok = await confirm({
      title: own ? "Apagar esta mensagem do destaque?" : `Apagar a mensagem de ${m.author.nickname}?`,
      description: own ? "Ela some para todos no auditório." : "Todos passam a ver “Mensagem apagada pelo leiloeiro”.",
      confirmLabel: "Apagar mensagem",
      tone: "danger",
    });
    if (ok) void act(`auctions/${auctionId}/chat/${m.id}`, "DELETE");
  }

  if (settings && !settings.enabled) {
    return <p className="rounded-card border border-dl-line bg-dl-card p-4 text-sm text-dl-ink-muted">O chat está desligado neste lote.</p>;
  }

  return (
    <>
      <HighlightFeed tone="panel" messages={chat.messages} hint="só você escreve" onDelete={open ? (m) => void remove(m, true) : undefined}>
        {open ? (
          <div className="border-t border-dl-line">
            <QuickMessages items={quickMessages(context)} onSend={(text) => chat.send(text, null)} />
            <ChatComposer id="highlight-input" tone="panel" onSend={chat.send} replyTo={replyTo} onCancelReply={() => setReplyTo(null)} placeholder="Escrever no destaque…" label="Mensagem do destaque" />
          </div>
        ) : (
          <p className="border-t border-dl-line px-4 py-3 text-sm text-dl-ink-muted">O leilão terminou. O chat fica só para leitura.</p>
        )}
      </HighlightFeed>
      <ChatView
        tone="panel"
        title="Participantes"
        className="h-[26rem] lg:h-[30rem]"
        messages={chat.messages}
        hasMore={chat.hasMore}
        loaded={chat.loaded}
        meId={null}
        canWrite={false}
        onSend={chat.send}
        onLoadOlder={chat.loadOlder}
        onReplyRequest={open ? setReplyTo : undefined}
        moderation={{
          muted: chat.page?.muted ?? {},
          banned: chat.page?.banned ?? [],
          onDelete: (m) => void remove(m, isStaff(m)),
          onMute: (bidderId, nickname) => setDialog({ kind: "mute", bidderId, nickname }),
          onUnmute: (bidderId) => void act(`auctions/${auctionId}/mutes/${bidderId}`, "DELETE"),
          onBan: (bidderId, nickname) => setDialog({ kind: "ban", bidderId, nickname }),
        }}
      />
      {dialog ? (
        <ModerationDialog
          dialog={dialog}
          onCancel={() => setDialog(null)}
          onMute={async (minutes) => {
            if (await act(`auctions/${auctionId}/mutes/${dialog.bidderId}`, "PUT", { minutes })) setDialog(null);
          }}
          onBan={async (reason) => {
            if (await act(`bidders/${dialog.bidderId}/ban`, "PUT", { reason })) setDialog(null);
          }}
        />
      ) : null}
    </>
  );
}

/** Mensagens prontas: um toque envia para o destaque, sem digitar nada durante o pregão. */
function QuickMessages({ items, onSend }: { items: string[]; onSend: (text: string) => Promise<SendResult> }) {
  const [sending, setSending] = useState<string | null>(null);

  async function pick(text: string) {
    if (sending) return;
    setSending(text);
    await onSend(text);
    setSending(null);
  }

  return (
    <div className="flex flex-col gap-1.5 px-2 pt-2">
      <p className="flex items-center gap-1 px-1 text-xs font-medium text-dl-ink-muted">
        <Zap aria-hidden className="size-3.5" />
        Mensagens prontas · um toque envia
      </p>
      <ul className="-mx-2 flex gap-1.5 overflow-x-auto px-2 pb-1 [scrollbar-width:thin]" aria-label="Mensagens prontas">
        {items.map((text) => (
          <li key={text} className="shrink-0">
            <button
              type="button"
              onClick={() => void pick(text)}
              disabled={sending !== null}
              aria-busy={sending === text}
              className="inline-flex min-h-10 items-center rounded-full border border-dl-line bg-dl-card px-3 text-sm font-medium whitespace-nowrap text-dl-ink transition-colors hover:border-dl-copper hover:text-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper active:bg-dl-page disabled:opacity-60"
            >
              {sending === text ? "Enviando…" : text}
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}

function ModerationDialog({
  dialog,
  onCancel,
  onMute,
  onBan,
}: {
  dialog: NonNullable<Dialog>;
  onCancel: () => void;
  onMute: (minutes: number | null) => void;
  onBan: (reason: string) => void;
}) {
  const [reason, setReason] = useState("");
  if (dialog.kind === "mute") {
    return (
      <Modal
        open
        onClose={onCancel}
        icon={<MicOff className="size-5" />}
        title={`Silenciar ${dialog.nickname}`}
        description="A pessoa continua vendo o leilão e dando lances, mas não escreve no chat deste lote."
      >
        <div className="grid grid-cols-2 gap-2">
          {[5, 15, 60].map((m) => (
            <button key={m} type="button" className={buttonClass("secondary")} onClick={() => onMute(m)}>
              {m === 60 ? "1 hora" : `${m} minutos`}
            </button>
          ))}
          <button type="button" className={buttonClass("secondary")} onClick={() => onMute(null)}>
            Até o fim do leilão
          </button>
        </div>
      </Modal>
    );
  }
  return (
    <Modal
      open
      onClose={onCancel}
      tone="danger"
      icon={<Ban className="size-5" />}
      title={`Banir ${dialog.nickname} da loja`}
      description="Vale na hora: a pessoa sai do auditório, perde a sessão, os lances automáticos dela são desligados e ela não entra mais na conta desta loja."
    >
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          onBan(reason);
        }}
      >
        <label className="flex flex-col gap-1.5 text-sm font-medium text-dl-ink">
          Motivo
          <input className={inputClass} value={reason} onChange={(e) => setReason(e.target.value)} minLength={3} maxLength={300} required data-autofocus />
        </label>
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" className={buttonClass("secondary")} onClick={onCancel}>
            Voltar
          </button>
          <button type="submit" className={dangerButton} disabled={reason.trim().length < 3}>
            Banir
          </button>
        </div>
      </form>
    </Modal>
  );
}
