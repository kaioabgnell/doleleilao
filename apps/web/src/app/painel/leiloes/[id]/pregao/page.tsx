import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { connection } from "next/server";
import { Suspense } from "react";
import type { AuctionLive } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { LiveControl } from "./live-control";

export const metadata: Metadata = { title: "Pregão" };

/** Controles do pregão ao vivo (RF-D05). */
export default function LivePage(props: PageProps<"/painel/leiloes/[id]/pregao">) {
  return (
    <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
      <LiveContent {...props} />
    </Suspense>
  );
}

async function LiveContent({ params }: PageProps<"/painel/leiloes/[id]/pregao">) {
  // O auditório usa a hora atual (cronômetro): renderiza na hora da requisição, nunca no pré-render.
  await connection();
  const { id } = await params;
  const result = await panelApi<AuctionLive>(`/auctioneer/auctions/${encodeURIComponent(id)}/live`);
  if (!result.ok) {
    if (result.status === 404) notFound();
    throw new Error(result.problem.detail ?? "Não foi possível abrir o pregão.");
  }

  return (
    <div className="flex flex-col gap-4">
      <Link href={`/painel/leiloes/${id}`} className="inline-flex min-h-11 w-fit items-center gap-1 text-sm font-medium text-dl-copper hover:underline">
        <ArrowLeft aria-hidden className="size-4" />
        Dados do lote
      </Link>
      <LiveControl initial={result.data} />
    </div>
  );
}
