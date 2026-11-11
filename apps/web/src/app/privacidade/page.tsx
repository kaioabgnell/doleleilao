import type { Metadata } from "next";
import Image from "next/image";
import { TermsText } from "@/components/legal/terms-text";
import { PRIVACY_POLICY, PRIVACY_POLICY_UPDATED_AT } from "@/lib/privacy-policy";

export const metadata: Metadata = {
  title: "Política de privacidade · Dole Leilão",
  description: "Como o Dole Leilão e as lojas de leilão tratam os seus dados pessoais (LGPD).",
};

const day = new Intl.DateTimeFormat("pt-BR", { timeZone: "UTC", day: "2-digit", month: "long", year: "numeric" });

/** Política de privacidade da plataforma: o mesmo texto para todas as lojas. */
export default function PrivacyPage() {
  return (
    <div className="flex flex-1 flex-col bg-dl-page">
      <header className="border-b border-dl-line bg-dl-card">
        <div className="mx-auto flex h-16 w-full max-w-3xl items-center px-4">
          <Image src="/brand/logo-horizontal.svg" alt="Dole Leilão" width={118} height={45} priority />
        </div>
      </header>
      <main className="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12">
        <article className="flex flex-col gap-8">
          <header className="flex flex-col gap-2 border-b border-dl-line pb-6">
            <h1 className="font-brand text-3xl font-bold text-dl-ink sm:text-4xl">Política de privacidade</h1>
            <p className="text-dl-ink-muted">Atualizada em {day.format(new Date(`${PRIVACY_POLICY_UPDATED_AT}T12:00:00Z`))}</p>
          </header>
          <TermsText text={PRIVACY_POLICY} tone="platform" />
        </article>
      </main>
    </div>
  );
}
