import type { Metadata } from "next";
import { Suspense } from "react";
import { notFound } from "next/navigation";
import { TermsText } from "@/components/legal/terms-text";
import { getStoreTerms } from "@/lib/storefront";

export const metadata: Metadata = { title: "Termos de uso" };

const day = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", day: "2-digit", month: "long", year: "numeric" });

/** Termos de uso da loja: as regras do leilão definidas pelo leiloeiro (aceitas no cadastro). */
export default function StoreTermsPage(props: PageProps<"/[slug]/termos">) {
  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12">
      <Suspense fallback={<div aria-busy="true" className="h-96 animate-pulse rounded-card bg-store-border/30" />}>
        <Terms {...props} />
      </Suspense>
    </main>
  );
}

async function Terms({ params }: PageProps<"/[slug]/termos">) {
  const { slug } = await params;
  const page = await getStoreTerms(slug);
  if (!page) notFound();

  return (
    <article className="flex flex-col gap-8">
      <header className="flex flex-col gap-2 border-b border-store-border pb-6">
        <h1 className="font-store-heading text-3xl font-bold sm:text-4xl">Termos de uso</h1>
        <p className="text-store-text-muted">
          Regras dos leilões de {page.store}
          {page.updated_at ? <> · atualizado em {day.format(new Date(page.updated_at))}</> : null}
        </p>
      </header>
      <TermsText text={page.terms} tone="store" />
      <p className="border-t border-store-border pt-6 text-sm text-store-text-muted">
        Como seus dados são tratados:{" "}
        <a href="/privacidade" target="_blank" rel="noopener" className="font-semibold text-store-link underline-offset-4 hover:underline">
          política de privacidade
        </a>
        .
      </p>
    </article>
  );
}
