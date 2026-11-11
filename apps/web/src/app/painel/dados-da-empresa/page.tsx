import type { Metadata } from "next";
import { Suspense } from "react";
import type { StoreTermsSettings, Storefront } from "@/lib/api-types";
import { getMe, panelApi } from "@/lib/auctioneer";
import { CompanyForm } from "./company-form";
import { SlugForm } from "./slug-form";
import { TermsEditor } from "./terms-editor";

export const metadata: Metadata = { title: "Dados da empresa" };

export default function CompanyDataPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Dados da empresa</h1>
        <p className="mt-1 text-dl-ink-muted">Contato, aparência e termos da sua loja. Você vê como fica antes de salvar.</p>
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <CompanyData />
      </Suspense>

      {/* Termos de uso da loja: as regras do leilão que o participante aceita ao se cadastrar. */}
      <section id="termos" aria-labelledby="termos-title" className="flex scroll-mt-24 flex-col gap-4 border-t border-dl-line pt-6">
        <div>
          <h2 id="termos-title" className="font-brand text-xl font-bold text-dl-ink sm:text-2xl">
            Termos da loja
          </h2>
          <p className="mt-1 max-w-3xl text-dl-ink-muted">
            As regras dos seus leilões. O participante aceita estes termos ao se cadastrar, num link que abre em nova aba. A política de privacidade é a do Dole Leilão, igual para todas as lojas.
          </p>
        </div>
        <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
          <TermsContent />
        </Suspense>
      </section>
    </div>
  );
}

async function CompanyData() {
  const [me, storefront] = await Promise.all([getMe(), panelApi<Storefront>("/auctioneer/storefront")]);
  if (!storefront.ok) throw new Error(storefront.problem.detail ?? "Não foi possível carregar sua loja.");

  return (
    <>
      <CompanyForm storefront={storefront.data} />
      <SlugForm auctioneer={me.auctioneer} />
    </>
  );
}

async function TermsContent() {
  const [terms, me] = await Promise.all([panelApi<StoreTermsSettings>("/auctioneer/storefront/terms"), getMe()]);
  if (!terms.ok) throw new Error("Não foi possível carregar os termos da loja.");

  return <TermsEditor initial={terms.data} slug={me.auctioneer.slug} storeName={me.auctioneer.display_name} />;
}
