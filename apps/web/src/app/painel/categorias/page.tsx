import type { Metadata } from "next";
import { Suspense } from "react";
import type { StoreCategory } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { CategoriesManager } from "./categories-manager";

export const metadata: Metadata = { title: "Categorias" };

export default function CategoriesPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Categorias</h1>
        <p className="mt-1 text-dl-ink-muted">Organizam os produtos da sua loja. Ex.: Relógios de pulso, Bolsas, Celulares.</p>
      </div>
      <Suspense fallback={<div className="h-48 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <CategoriesData />
      </Suspense>
    </div>
  );
}

async function CategoriesData() {
  const result = await panelApi<StoreCategory[]>("/auctioneer/categories");
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar as categorias.");
  return <CategoriesManager categories={result.data} />;
}
