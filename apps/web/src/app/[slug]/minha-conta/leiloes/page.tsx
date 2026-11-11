import type { Metadata } from "next";
import { Suspense } from "react";
import { AuctionCard } from "@/components/auction/auction-card";
import type { UpcomingAuction } from "@/lib/api-types";
import { bidderApi } from "@/lib/bidder-session";
import { AccountSection, EmptyState, SectionSkeleton, requireBidder } from "../shared";

export const metadata: Metadata = { title: "Próximos leilões", robots: { index: false } };

export default function UpcomingPage(props: PageProps<"/[slug]/minha-conta/leiloes">) {
  return (
    <Suspense fallback={<SectionSkeleton />}>
      <Upcoming {...props} />
    </Suspense>
  );
}

async function Upcoming({ params }: PageProps<"/[slug]/minha-conta/leiloes">) {
  const { slug } = await params;
  await requireBidder(slug, "leiloes");
  const upcoming = await bidderApi<{ data: UpcomingAuction[] }>("/bidders/me/upcoming-auctions");
  const list = upcoming.ok ? upcoming.data.data : [];

  return (
    <AccountSection title="Próximos leilões" description="Os leilões em que você marcou “Quero participar” ou que têm produtos favoritos seus.">
      {list.length > 0 ? (
        <ul className="grid gap-4 sm:grid-cols-2">
          {list.map(({ auction, participating, has_favorites }) => (
            <li key={auction.id} className="flex flex-col gap-2">
              <AuctionCard slug={slug} auction={auction} />
              <p className="flex flex-wrap gap-2 text-xs font-semibold">
                {participating ? <span className="rounded-full bg-state-winning/10 px-2 py-0.5 text-state-winning">Você vai participar</span> : null}
                {has_favorites ? <span className="rounded-full bg-store-primary/10 px-2 py-0.5 text-store-link">Tem favoritos seus</span> : null}
              </p>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState>
          Marque &ldquo;Quero participar&rdquo; num leilão ou favorite um produto: os leilões aparecem aqui.{" "}
          <a href={`/${slug}/leiloes`} className="font-semibold text-store-link underline-offset-4 hover:underline">
            Ver leilões
          </a>
        </EmptyState>
      )}
    </AccountSection>
  );
}
