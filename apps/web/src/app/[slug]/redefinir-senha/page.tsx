import type { Metadata } from "next";
import { Suspense } from "react";
import { StoreNotice } from "@/components/store/store-ui";
import { BidderResetForm } from "../auth-forms";
import { AuthShell, param } from "../auth-shell";

export const metadata: Metadata = { title: "Criar nova senha", robots: { index: false } };

async function BidderResetPageContent({ params, searchParams }: PageProps<"/[slug]/redefinir-senha">) {
  const { slug } = await params;
  const sp = await searchParams;
  const token = param(sp.token);
  const email = param(sp.email);
  return (
    <AuthShell title="Criar nova senha">
      {token && email ? <BidderResetForm slug={slug} token={token} email={email} /> : <StoreNotice tone="error">Link incompleto. Abra o link do e-mail de novo.</StoreNotice>}
    </AuthShell>
  );
}

/** Lê a URL e a sessão: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function BidderResetPage(props: PageProps<"/[slug]/redefinir-senha">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-md animate-pulse px-4 py-8" aria-busy="true" />}>
      <BidderResetPageContent {...props} />
    </Suspense>
  );
}
