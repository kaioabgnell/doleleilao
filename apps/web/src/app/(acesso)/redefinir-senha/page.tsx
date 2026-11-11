import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { Alert } from "@/components/ui/alert";
import { ResetForm } from "./reset-form";

export const metadata: Metadata = { title: "Criar nova senha", robots: { index: false } };

export default function ResetPasswordPage(props: PageProps<"/redefinir-senha">) {
  return (
    <>
      <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Criar nova senha</h1>
      <div className="mt-6 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
        <Suspense fallback={null}>
          <ResetWithParams searchParams={props.searchParams} />
        </Suspense>
      </div>
    </>
  );
}

async function ResetWithParams({ searchParams }: Pick<PageProps<"/redefinir-senha">, "searchParams">) {
  const { token, email } = await searchParams;

  if (typeof token !== "string" || typeof email !== "string") {
    return (
      <Alert tone="error" title="Link incompleto.">
        Abra o link do e-mail de novo ou{" "}
        <Link href="/esqueci-senha" className="font-semibold text-dl-copper underline">
          peça um novo
        </Link>
        .
      </Alert>
    );
  }

  return <ResetForm token={token} email={email} />;
}
