import type { Metadata } from "next";
import { Suspense } from "react";
import type { StoreBanner } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { BannersManager } from "./banners-manager";

export const metadata: Metadata = { title: "Banners" };

export default function BannersPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Banners</h1>
        <p className="mt-1 text-dl-ink-muted">Destaques no topo da sua loja. Até 10, na ordem que você escolher.</p>
      </div>
      <Suspense fallback={<div className="h-64 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <BannersData />
      </Suspense>
    </div>
  );
}

async function BannersData() {
  const result = await panelApi<StoreBanner[]>("/auctioneer/banners");
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar os banners.");
  return <BannersManager banners={result.data} />;
}
