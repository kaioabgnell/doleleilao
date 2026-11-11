import Link from "next/link";
import { Suspense } from "react";
import { BidderAvatar } from "@/components/store/bidder-avatar";
import type { Bidder, BidderAward } from "@/lib/api-types";
import { bidderApi, getBidder } from "@/lib/bidder-session";
import { AccountNav } from "./account-nav";
import { AccountNotices } from "./account-notices";
import { EmailPending } from "./account-forms";

const missingLabels = { name: "Seu nome", cpf: "CPF", phone: "Celular", email: "Confirmar o e-mail" } as const;

/**
 * "Minha conta" do participante (RF-E07, RF-E08): uma página para cada assunto (próximos leilões,
 * arrematações, dados, alertas e favoritos), com o mesmo menu e o aviso de cadastro incompleto.
 */
export default function AccountLayout({ children, params }: LayoutProps<"/[slug]/minha-conta">) {
  return (
    <main className="mx-auto w-full max-w-[72rem] px-4 py-6 sm:py-8 @3xl:px-6">
      <Suspense fallback={<div aria-busy="true" className="h-96 animate-pulse rounded-card bg-store-border/30" />}>
        <AccountFrame params={params}>{children}</AccountFrame>
      </Suspense>
    </main>
  );
}

async function AccountFrame({ params, children }: Pick<LayoutProps<"/[slug]/minha-conta">, "params" | "children">) {
  const { slug } = await params;
  const bidder = await getBidder();
  // Sem sessão, a própria página leva ao login (com a volta para ela).
  if (!bidder) return <>{children}</>;

  const awards = await bidderApi<{ data: BidderAward[] }>("/bidders/me/awards");
  const awardsCount = awards.ok ? awards.data.data.length : 0;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center gap-4">
        <BidderAvatar name={bidder.name} photoUrl={bidder.photo_url} avatarKey={bidder.avatar_key} className="size-14 shrink-0" />
        <div className="min-w-0">
          <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">Minha conta</h1>
          <p className="truncate text-store-text-muted">
            {bidder.nickname} · {bidder.email}
          </p>
        </div>
      </div>

      <Suspense fallback={null}>
        <AccountNotices nickname={bidder.nickname} />
      </Suspense>

      {bidder.eligible ? null : <PendingRegistration slug={slug} bidder={bidder} />}

      <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10">
        <AccountNav slug={slug} awardsCount={awardsCount} />
        <div className="min-w-0">{children}</div>
      </div>
    </div>
  );
}

function PendingRegistration({ slug, bidder }: { slug: string; bidder: Bidder }) {
  return (
    <section aria-labelledby="situacao" className="flex flex-col gap-3 rounded-card border border-store-primary/40 bg-store-surface p-5">
      <h2 id="situacao" className="font-semibold">Falta pouco para você poder dar lances</h2>
      <ul className="flex flex-wrap gap-x-5 gap-y-1">
        {bidder.missing.map((m) => (
          <li key={m} className="flex items-center gap-2 text-sm">
            <span aria-hidden className="size-1.5 rounded-full bg-store-primary" />
            {missingLabels[m]}
          </li>
        ))}
      </ul>
      {bidder.missing.includes("email") ? <EmailPending email={bidder.email} /> : null}
      {bidder.missing.some((m) => m !== "email") ? (
        <Link href={`/${slug}/minha-conta/dados`} className="w-fit text-sm font-semibold text-store-link underline-offset-4 hover:underline">
          Completar em “Seus dados”
        </Link>
      ) : null}
    </section>
  );
}
