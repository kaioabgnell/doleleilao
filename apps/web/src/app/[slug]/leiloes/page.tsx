import type { Metadata } from "next";
import { Suspense } from "react";
import Link from "next/link";
import { Gavel } from "lucide-react";
import { AuctionCard } from "@/components/auction/auction-card";
import { getAuctions } from "@/lib/auctions";

export const metadata: Metadata = { title: "Leilões" };

/** Próximos leilões (ao vivo primeiro) e encerrados (RF-E01, RF-E05). */
async function AuctionsPageContent({ params }: PageProps<"/[slug]/leiloes">) {
  const { slug } = await params;
  const [upcoming, finished] = await Promise.all([getAuctions(slug, "upcoming"), getAuctions(slug, "finished")]);

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-6 sm:py-8">
      <section className="flex flex-col gap-4">
        <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">Leilões</h1>
        {upcoming.length === 0 ? (
          <div className="flex flex-col items-center gap-3 rounded-card border border-store-border bg-store-surface px-6 py-12 text-center">
            <Gavel aria-hidden className="size-8 text-store-link" />
            <p className="font-store-heading text-lg font-semibold">Nenhum leilão agendado por enquanto</p>
            <p className="max-w-sm text-store-text-muted">Cadastre-se na loja para participar quando o próximo leilão for marcado.</p>
            <Link
              href={`/${slug}/cadastro`}
              className="mt-2 inline-flex min-h-11 items-center rounded-field bg-store-primary px-5 font-semibold text-store-on-primary hover:bg-store-primary-hover"
            >
              Criar minha conta
            </Link>
          </div>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {upcoming.map((auction, index) => (
              <li key={auction.id} className="flex">
                <AuctionCard slug={slug} auction={auction} priority={index === 0} />
              </li>
            ))}
          </ul>
        )}
      </section>

      {finished.length > 0 ? (
        <section className="flex flex-col gap-4">
          <h2 className="font-store-heading text-xl font-bold">Encerrados</h2>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {finished.map((auction) => (
              <li key={auction.id} className="flex">
                <AuctionCard slug={slug} auction={auction} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </main>
  );
}

/** Lê a URL: fica dentro de um Suspense (navegação instantânea com Cache Components). */
export default function AuctionsPage(props: PageProps<"/[slug]/leiloes">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-6xl animate-pulse px-4 py-8" aria-busy="true" />}>
      <AuctionsPageContent {...props} />
    </Suspense>
  );
}
