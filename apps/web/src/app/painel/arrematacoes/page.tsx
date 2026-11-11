import type { Metadata } from "next";
import { Suspense } from "react";
import type { AwardList, WinnerTemplate } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { AwardsBoard } from "./awards-board";

export const metadata: Metadata = { title: "Arrematações" };

/** Arrematações (RF-E08, RF-G05, RF-G06): acompanhamento, mensagem ao vencedor e planilha. */
export default function AwardsPage(props: PageProps<"/painel/arrematacoes">) {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Arrematações</h1>
        <p className="mt-1 text-dl-ink-muted">Fale com quem arrematou, acompanhe o pagamento e a entrega, e exporte a planilha do lote.</p>
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <AwardsContent searchParams={props.searchParams} />
      </Suspense>
    </div>
  );
}

async function AwardsContent({ searchParams }: Pick<PageProps<"/painel/arrematacoes">, "searchParams">) {
  const sp = await searchParams;
  const auction = typeof sp.lote === "string" ? sp.lote : "";
  const status = typeof sp.status === "string" ? sp.status : "open";
  const q = typeof sp.q === "string" ? sp.q : "";
  const query = new URLSearchParams({ ...(auction && { auction }), ...(status !== "all" && { status }), ...(q && { q }) });
  const [list, template] = await Promise.all([
    panelApi<AwardList>(`/auctioneer/awards?${query}`),
    panelApi<WinnerTemplate>("/auctioneer/winner-message-template"),
  ]);
  if (!list.ok || !template.ok) throw new Error("Não foi possível carregar as arrematações.");

  return <AwardsBoard list={list.data} template={template.data} filters={{ auction, status, q }} />;
}
