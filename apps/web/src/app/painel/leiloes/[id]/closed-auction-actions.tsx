"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Trash2 } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { useConfirm } from "@/components/ui/dialog";
import type { Auction } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";

/**
 * Lote encerrado ou cancelado: "Visível no site" (só encerrado) e excluir o lote.
 * Excluir apaga também itens, lances, arrematações e o chat do lote.
 */
export function ClosedAuctionActions({ auction }: { auction: Auction }) {
  const router = useRouter();
  const confirm = useConfirm();
  const [visible, setVisible] = useState(auction.visible_in_store ?? true);
  const [busy, setBusy] = useState<"visibility" | "delete" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const finished = auction.status === "finished";

  async function toggleVisibility() {
    const next = !visible;
    setError(null);
    setBusy("visibility");
    setVisible(next);
    const result = await panelRequest<Auction>(`auctions/${auction.id}/visibility`, { method: "PUT", body: { visible: next } });
    setBusy(null);
    if (!result.ok) {
      setVisible(!next);
      setError(result.message ?? "Não foi possível mudar a visibilidade.");
      return;
    }
    router.refresh();
  }

  async function remove() {
    const ok = await confirm({
      title: "Excluir este lote?",
      description: finished
        ? "O lote some da loja e do painel, junto com os itens, os lances, as arrematações e o chat. Não dá para desfazer."
        : "O lote some do painel. Não dá para desfazer.",
      confirmLabel: "Excluir lote",
      cancelLabel: "Manter lote",
      tone: "danger",
    });
    if (!ok) return;
    setError(null);
    setBusy("delete");
    const result = await panelRequest(`auctions/${auction.id}`, { method: "DELETE" });
    if (!result.ok) {
      setBusy(null);
      setError(result.message ?? "Não foi possível excluir o lote.");
      return;
    }
    router.push("/painel/leiloes");
  }

  return (
    <section className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
      <h2 className="font-brand text-lg font-bold text-dl-ink">Lote</h2>
      {error ? <Alert tone="error" title={error} /> : null}

      {finished ? (
        <div className="flex items-center justify-between gap-4">
          <div className="min-w-0">
            <p id="visible-label" className="font-medium text-dl-ink">
              Visível no site
            </p>
            <p id="visible-hint" className="text-sm text-dl-ink-muted">
              {visible ? "Sim: aparece em “Leilões encerrados” na sua loja." : "Não: só você vê este lote, no painel."}
            </p>
          </div>
          <button
            type="button"
            role="switch"
            aria-checked={visible}
            aria-labelledby="visible-label"
            aria-describedby="visible-hint"
            disabled={!!busy}
            onClick={() => void toggleVisibility()}
            className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dl-copper disabled:opacity-60 ${
              visible ? "bg-dl-copper" : "bg-dl-ink-muted"
            }`}
          >
            <span
              aria-hidden
              className={`inline-block size-5 rounded-full bg-dl-card shadow transition-transform duration-200 motion-reduce:transition-none ${visible ? "translate-x-6" : "translate-x-1"}`}
            />
          </button>
        </div>
      ) : null}

      <div className={finished ? "border-t border-dl-line pt-4" : undefined}>
        <button type="button" className={buttonClass("ghost", "text-state-outbid")} onClick={() => void remove()} disabled={!!busy}>
          <Trash2 aria-hidden className="size-4" />
          {busy === "delete" ? "Excluindo…" : "Excluir lote"}
        </button>
      </div>
    </section>
  );
}
