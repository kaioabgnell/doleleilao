import type { Metadata } from "next";
import { Suspense } from "react";
import { BidderForgotForm } from "../auth-forms";
import { AuthShell } from "../auth-shell";

export const metadata: Metadata = { title: "Esqueci minha senha", robots: { index: false } };

async function BidderForgotPageContent({ params }: PageProps<"/[slug]/esqueci-senha">) {
  const { slug } = await params;
  return (
    <AuthShell title="Esqueci minha senha" subtitle="Informe o e-mail da sua conta nesta loja.">
      <BidderForgotForm slug={slug} />
    </AuthShell>
  );
}

/** Lê a URL e a sessão: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function BidderForgotPage(props: PageProps<"/[slug]/esqueci-senha">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-md animate-pulse px-4 py-8" aria-busy="true" />}>
      <BidderForgotPageContent {...props} />
    </Suspense>
  );
}
