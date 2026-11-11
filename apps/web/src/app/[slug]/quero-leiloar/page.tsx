import type { Metadata } from "next";
import { Suspense } from "react";
import { getBidder } from "@/lib/bidder-session";
import { getStorefront } from "@/lib/storefront";
import { ConsignmentForm } from "./consignment-form";

export const metadata: Metadata = { title: "Quer leiloar algo seu?" };

/** "Quer leiloar algum produto seu? Fale com o leiloeiro" (RF-E09). */
async function ConsignmentPageContent({ params }: PageProps<"/[slug]/quero-leiloar">) {
  const { slug } = await params;
  const [bidder, lookup] = await Promise.all([getBidder(), getStorefront(slug)]);
  const name = lookup.kind === "found" ? lookup.storefront.display_name : "o leiloeiro";

  return (
    <main className="mx-auto w-full max-w-xl px-4 py-8 sm:py-12">
      <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">Quer leiloar algo seu?</h1>
      <p className="mt-2 text-store-text-muted">Conte o que você tem. {name} recebe seu pedido e entra em contato.</p>
      <div className="mt-6 rounded-card border border-store-border bg-store-surface p-5 sm:p-6">
        <ConsignmentForm slug={slug} defaults={{ name: bidder?.name ?? "", email: bidder?.email ?? "", phone: bidder?.phone ?? "" }} />
      </div>
    </main>
  );
}

/** Lê a URL e a sessão: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function ConsignmentPage(props: PageProps<"/[slug]/quero-leiloar">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-md animate-pulse px-4 py-8" aria-busy="true" />}>
      <ConsignmentPageContent {...props} />
    </Suspense>
  );
}
