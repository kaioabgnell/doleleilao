import type { Metadata } from "next";
import { Suspense } from "react";
import { redirect } from "next/navigation";
import { getBidder } from "@/lib/bidder-session";
import { getStorefront } from "@/lib/storefront";
import { BidderRegisterForm } from "../auth-forms";
import { AuthShell, param } from "../auth-shell";

export const metadata: Metadata = { title: "Criar conta", robots: { index: false } };

async function BidderRegisterPageContent({ params, searchParams }: PageProps<"/[slug]/cadastro">) {
  const { slug } = await params;
  const sp = await searchParams;
  if (await getBidder()) redirect(`/${slug}/minha-conta`);
  const lookup = await getStorefront(slug);
  const name = lookup.kind === "found" ? lookup.storefront.display_name : "loja";

  return (
    <AuthShell title="Crie sua conta" subtitle={`Com o cadastro completo você já pode dar lances em todos os leilões da ${name}.`}>
      <BidderRegisterForm slug={slug} storeName={name} voltar={param(sp.voltar)} favoritar={param(sp.favoritar)} />
    </AuthShell>
  );
}

/** Lê a URL e a sessão: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function BidderRegisterPage(props: PageProps<"/[slug]/cadastro">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-md animate-pulse px-4 py-8" aria-busy="true" />}>
      <BidderRegisterPageContent {...props} />
    </Suspense>
  );
}
