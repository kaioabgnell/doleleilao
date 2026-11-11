import type { Metadata } from "next";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { Suspense } from "react";
import type { AuctionList, BillingSettings } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { AuctionForm } from "../auction-form";

export const metadata: Metadata = { title: "Novo lote" };

export default function NewAuctionPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link href="/painel/leiloes" className="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-dl-copper hover:underline">
          <ArrowLeft aria-hidden className="size-4" />
          Leilões
        </Link>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Novo lote</h1>
        <p className="mt-1 text-dl-ink-muted">Salve como rascunho e agende quando estiver pronto. Só os agendados aparecem na loja.</p>
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <NewAuctionForm />
      </Suspense>
    </div>
  );
}

async function NewAuctionForm() {
  const [result, billing] = await Promise.all([
    panelApi<AuctionList>("/auctioneer/auctions?status=draft"),
    panelApi<BillingSettings>("/auctioneer/billing"),
  ]);
  if (!result.ok || !result.data.defaults) throw new Error("Não foi possível abrir o cadastro do lote.");
  return <AuctionForm auction={null} defaults={result.data.defaults} hasPaymentCard={!billing.ok || billing.data.card !== null} />;
}
