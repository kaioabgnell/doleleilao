import type { Metadata } from "next";
import { Suspense } from "react";
import type { Consignment } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { ConsignmentInbox } from "./consignment-inbox";

export const metadata: Metadata = { title: "Pedidos para leiloar" };

export default function ConsignmentPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Pedidos para leiloar</h1>
        <p className="mt-1 text-dl-ink-muted">Pessoas que querem leiloar algo com você, pelo “Quer leiloar algo seu?” da loja.</p>
      </div>
      <Suspense fallback={<div className="h-48 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <Inbox />
      </Suspense>
    </div>
  );
}

async function Inbox() {
  const result = await panelApi<{ data: Consignment[]; new_count: number }>("/auctioneer/consignment-requests");
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar os pedidos.");
  return <ConsignmentInbox items={result.data.data} />;
}
