"use client";

import { Bell, CheckCheck } from "lucide-react";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import type { AppNotification, NotificationPage } from "@/lib/api-types";

const REFRESH_MS = 60_000;

const relative = new Intl.RelativeTimeFormat("pt-BR", { numeric: "auto" });

/** "agora", "há 5 min", "ontem", "há 3 dias". */
function timeAgo(iso: string): string {
  const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
  const abs = Math.abs(seconds);
  if (abs < 60) return "agora";
  if (abs < 3600) return relative.format(Math.round(seconds / 60), "minute");
  if (abs < 86400) return relative.format(Math.round(seconds / 3600), "hour");
  return relative.format(Math.round(seconds / 86400), "day");
}

/** Sino do painel e da loja (D-43, RF-N01, RF-N02, RF-N06). */
export function NotificationBell({
  endpoint = "/api/painel/notificacoes",
  readEndpoint = "/api/painel/notificacoes/lidas",
  tone = "light",
}: {
  endpoint?: string;
  readEndpoint?: string;
  /** "dark": botão sobre a barra escura do painel. */
  tone?: "light" | "dark";
} = {}) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [cursor, setCursor] = useState<string | null>(null);
  const [status, setStatus] = useState<"idle" | "loading" | "error">("loading");
  const root = useRef<HTMLDivElement>(null);

  const load = useCallback(async (more?: string) => {
    try {
      const res = await fetch(`${endpoint}${more ? `?cursor=${more}` : ""}`, { cache: "no-store" });
      if (res.status === 401) {
        // Sessão vencida: recarregar a página faz a camada de dados levar ao /sair.
        router.refresh();
        return;
      }
      if (!res.ok) throw new Error(String(res.status));
      const page = (await res.json()) as NotificationPage;
      setItems((current) => (more ? [...current, ...page.data] : page.data));
      setUnread(page.unread_count);
      setCursor(page.next_cursor);
      setStatus("idle");
    } catch {
      setStatus("error");
    }
  }, [router, endpoint]);

  // Atualiza ao abrir a página, a cada 60 s (com a aba visível) e ao abrir o sino.
  useEffect(() => {
    const first = setTimeout(() => void load(), 0);
    const timer = setInterval(() => document.visibilityState === "visible" && void load(), REFRESH_MS);
    return () => {
      clearTimeout(first);
      clearInterval(timer);
    };
  }, [load]);

  useEffect(() => {
    if (!open) return;
    const onPointer = (e: PointerEvent) => !root.current?.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  async function markRead(ids?: string[]) {
    setItems((current) => current.map((n) => (!ids || ids.includes(n.id) ? { ...n, read: true } : n)));
    const res = await fetch(readEndpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(ids ? { ids } : {}),
    }).catch(() => null);
    if (res?.ok) setUnread(((await res.json()) as { unread_count: number }).unread_count);
  }

  function openNotification(n: AppNotification) {
    if (!n.read) void markRead([n.id]);
    setOpen(false);
    if (n.url.startsWith("/")) router.push(n.url);
    else window.open(n.url, "_blank", "noopener");
  }

  const badge = unread > 9 ? "9+" : String(unread);

  return (
    <div ref={root} className="relative">
      <button
        type="button"
        onClick={() => {
          if (!open) void load();
          setOpen((v) => !v);
        }}
        aria-expanded={open}
        aria-controls="notification-panel"
        aria-label={unread > 0 ? `Notificações, ${unread} não lidas` : "Notificações"}
        className={`relative flex size-11 items-center justify-center rounded-field focus-visible:outline-2 ${
          tone === "dark" ? "text-dl-on-topbar hover:bg-dl-topbar-hover focus-visible:outline-dl-on-topbar" : "text-dl-ink hover:bg-dl-page focus-visible:outline-dl-copper"
        }`}
      >
        <Bell aria-hidden className="size-5" />
        {unread > 0 ? (
          <span
            aria-hidden
            className="absolute top-1.5 right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-state-outbid px-1 text-[11px] font-semibold text-white tabular"
          >
            {badge}
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          id="notification-panel"
          role="dialog"
          aria-label="Notificações"
          className="fixed inset-x-2 top-[4.25rem] z-50 flex max-h-[min(32rem,calc(100dvh-5rem))] flex-col overflow-hidden rounded-card border border-dl-line bg-dl-card shadow-xl sm:absolute sm:inset-x-auto sm:top-12 sm:right-0 sm:w-96"
        >
          <div className="flex items-center justify-between gap-2 border-b border-dl-line px-4 py-3">
            <p className="font-semibold text-dl-ink">Notificações</p>
            {unread > 0 ? (
              <button
                type="button"
                onClick={() => void markRead()}
                className="flex min-h-9 items-center gap-1.5 rounded-field px-2 text-sm font-medium text-dl-copper hover:bg-dl-page focus-visible:outline-2 focus-visible:outline-dl-copper"
              >
                <CheckCheck aria-hidden className="size-4" />
                Marcar todas como lidas
              </button>
            ) : null}
          </div>

          <div className="overflow-y-auto">
            {status === "error" && items.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-dl-ink-muted">Não foi possível carregar. Tente de novo.</p>
            ) : items.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-dl-ink-muted">
                {status === "loading" ? "Carregando…" : "Nenhuma notificação por enquanto."}
              </p>
            ) : (
              <ul className="divide-y divide-dl-line">
                {items.map((n) => (
                  <li key={n.id}>
                    <button
                      type="button"
                      onClick={() => openNotification(n)}
                      className={`flex w-full gap-3 px-4 py-3 text-left hover:bg-dl-page focus-visible:bg-dl-page focus-visible:outline-none ${n.read ? "" : "bg-dl-page/60"}`}
                    >
                      <span
                        aria-hidden
                        className={`mt-1.5 size-2 shrink-0 rounded-full ${n.read ? "bg-transparent" : "bg-dl-copper"}`}
                      />
                      <span className="flex min-w-0 flex-col gap-0.5">
                        <span className={`text-sm text-dl-ink ${n.read ? "" : "font-semibold"}`}>
                          {n.title}
                          {!n.read ? <span className="sr-only"> (não lida)</span> : null}
                        </span>
                        <span className="text-sm text-dl-ink-muted">{n.body}</span>
                        <time dateTime={n.created_at} className="text-xs text-dl-ink-muted">
                          {timeAgo(n.created_at)}
                        </time>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
            {cursor ? (
              <button
                type="button"
                onClick={() => void load(cursor)}
                className="min-h-11 w-full border-t border-dl-line text-sm font-medium text-dl-copper hover:bg-dl-page"
              >
                Ver mais
              </button>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
