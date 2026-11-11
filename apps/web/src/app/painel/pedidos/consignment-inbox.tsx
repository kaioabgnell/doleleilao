"use client";

import { Archive, Check, Inbox, Mail, MessageCircle } from "lucide-react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui/button-styles";
import type { Consignment } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";

const when = new Intl.DateTimeFormat("pt-BR", { dateStyle: "short", timeStyle: "short", timeZone: "America/Sao_Paulo" });

export function ConsignmentInbox({ items }: { items: Consignment[] }) {
  const router = useRouter();

  async function setStatus(id: string, status: Consignment["status"]) {
    await panelRequest(`consignment-requests/${id}`, { method: "PATCH", body: { status } });
    router.refresh();
  }

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-12 text-center">
        <Inbox aria-hidden className="size-8 text-dl-copper-icon" />
        <p className="font-semibold text-dl-ink">Nenhum pedido por enquanto</p>
        <p className="max-w-sm text-sm text-dl-ink-muted">Quando alguém usar o “Quer leiloar algo seu?” da sua loja, o pedido aparece aqui e no sino.</p>
      </div>
    );
  }

  return (
    <ul className="flex flex-col gap-3">
      {items.map((item) => (
        <li key={item.id} className={`flex flex-col gap-3 rounded-card border bg-dl-card p-4 sm:p-5 ${item.status === "new" ? "border-dl-copper" : "border-dl-line"}`}>
          <div className="flex flex-wrap items-center gap-2">
            <p className="font-semibold text-dl-ink">{item.name}</p>
            {item.status === "new" ? <span className="rounded-full bg-dl-copper px-2 py-0.5 text-xs font-semibold text-white">Novo</span> : null}
            {item.from_bidder ? <span className="rounded-full bg-dl-page px-2 py-0.5 text-xs text-dl-ink-muted">Participante da loja</span> : null}
            <time dateTime={item.created_at} className="ml-auto text-xs text-dl-ink-muted">{when.format(new Date(item.created_at))}</time>
          </div>
          <p className="text-sm whitespace-pre-line text-dl-ink">{item.description}</p>
          {item.photos.length > 0 ? (
            <div className="flex gap-2">
              {item.photos.map((url) => (
                <a key={url} href={url} target="_blank" rel="noreferrer" className="rounded-field focus-visible:outline-2 focus-visible:outline-dl-copper">
                  {/* eslint-disable-next-line @next/next/no-img-element -- foto enviada, já processada */}
                  <img src={url} alt="Foto enviada" className="size-20 rounded-field object-cover" />
                </a>
              ))}
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2">
            {item.whatsapp_url ? (
              <a href={item.whatsapp_url} target="_blank" rel="noreferrer" className={buttonClass("secondary")}>
                <MessageCircle aria-hidden className="size-4" />
                WhatsApp {item.phone}
              </a>
            ) : null}
            {item.email ? (
              <a href={`mailto:${item.email}`} className={buttonClass("secondary")}>
                <Mail aria-hidden className="size-4" />
                {item.email}
              </a>
            ) : null}
            {item.status === "new" ? (
              <button type="button" onClick={() => setStatus(item.id, "read")} className={buttonClass("ghost")}>
                <Check aria-hidden className="size-4" />
                Marcar como lido
              </button>
            ) : null}
            <button type="button" onClick={() => setStatus(item.id, "archived")} className={buttonClass("ghost")}>
              <Archive aria-hidden className="size-4" />
              Arquivar
            </button>
          </div>
        </li>
      ))}
    </ul>
  );
}
