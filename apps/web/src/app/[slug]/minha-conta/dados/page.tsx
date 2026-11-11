import type { Metadata } from "next";
import { Suspense } from "react";
import { CheckCircle2 } from "lucide-react";
import { AvatarPicker, ProfileForm } from "../account-forms";
import { AccountSection, SectionSkeleton, requireBidder } from "../shared";

export const metadata: Metadata = { title: "Seus dados", robots: { index: false } };

export default function ProfilePage(props: PageProps<"/[slug]/minha-conta/dados">) {
  return (
    <Suspense fallback={<SectionSkeleton />}>
      <Profile {...props} />
    </Suspense>
  );
}

async function Profile({ params }: PageProps<"/[slug]/minha-conta/dados">) {
  const { slug } = await params;
  const bidder = await requireBidder(slug, "dados");

  return (
    <AccountSection title="Seus dados" description={`CPF: ${bidder.cpf_masked} · E-mail: ${bidder.email}`}>
      {bidder.eligible ? (
        <p className="flex items-center gap-2 rounded-card border border-store-border bg-store-surface px-4 py-3 text-sm font-medium text-state-winning">
          <CheckCircle2 aria-hidden className="size-5 shrink-0" />
          Cadastro completo: você pode dar lances em todos os leilões desta loja.
        </p>
      ) : null}
      <div className="grid gap-6 xl:grid-cols-2">
        <section aria-labelledby="dados-pessoais" className="rounded-card border border-store-border bg-store-surface p-5 sm:p-6">
          <h3 id="dados-pessoais" className="font-store-heading text-lg font-bold">
            Dados pessoais
          </h3>
          <div className="mt-4">
            <ProfileForm slug={slug} bidder={bidder} />
          </div>
        </section>
        <section aria-labelledby="foto" className="rounded-card border border-store-border bg-store-surface p-5 sm:p-6">
          <h3 id="foto" className="font-store-heading text-lg font-bold">
            Foto ou avatar
          </h3>
          <p className="mt-1 text-sm text-store-text-muted">Opcional. Aparece no auditório e no chat, junto do seu apelido.</p>
          <div className="mt-4">
            <AvatarPicker slug={slug} bidder={bidder} />
          </div>
        </section>
      </div>
    </AccountSection>
  );
}
