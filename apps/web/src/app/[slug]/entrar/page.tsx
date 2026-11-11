import type { Metadata } from "next";
import { Suspense } from "react";
import { redirect } from "next/navigation";
import { StoreNotice } from "@/components/store/store-ui";
import { getBidder } from "@/lib/bidder-session";
import { BidderLoginForm } from "../auth-forms";
import { AuthShell, param } from "../auth-shell";

export const metadata: Metadata = { title: "Entrar", robots: { index: false } };

async function BidderLoginPageContent({ params, searchParams }: PageProps<"/[slug]/entrar">) {
  const { slug } = await params;
  const sp = await searchParams;
  if (await getBidder()) redirect(`/${slug}/minha-conta`);
  const favoritar = param(sp.favoritar);

  return (
    <AuthShell title="Entrar na loja">
      <div className="flex flex-col gap-4">
        {param(sp.senha) === "alterada" ? <StoreNotice tone="success">Senha alterada. Entre com a nova senha.</StoreNotice> : null}
        {favoritar ? <StoreNotice>Entre ou crie sua conta para salvar este produto nos favoritos.</StoreNotice> : null}
        <BidderLoginForm slug={slug} voltar={param(sp.voltar)} favoritar={favoritar} />
      </div>
    </AuthShell>
  );
}

/** Lê a URL e a sessão: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function BidderLoginPage(props: PageProps<"/[slug]/entrar">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-md animate-pulse px-4 py-8" aria-busy="true" />}>
      <BidderLoginPageContent {...props} />
    </Suspense>
  );
}
