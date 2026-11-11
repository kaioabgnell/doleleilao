import type { Metadata } from "next";
import { Suspense } from "react";
import { FavoriteButton } from "@/components/store/favorite-button";
import { ProductGridLayout } from "@/components/store/templates/cards";
import type { Product } from "@/lib/api-types";
import { bidderApi } from "@/lib/bidder-session";
import { getStorefront } from "@/lib/storefront";
import { AccountSection, EmptyState, SectionSkeleton, requireBidder } from "../shared";

export const metadata: Metadata = { title: "Favoritos", robots: { index: false } };

export default function FavoritesPage(props: PageProps<"/[slug]/minha-conta/favoritos">) {
  return (
    <Suspense fallback={<SectionSkeleton />}>
      <Favorites {...props} />
    </Suspense>
  );
}

async function Favorites({ params }: PageProps<"/[slug]/minha-conta/favoritos">) {
  const { slug } = await params;
  await requireBidder(slug, "favoritos");
  const [favorites, lookup] = await Promise.all([bidderApi<{ data: Product[]; ids: string[] }>("/bidders/me/favorites"), getStorefront(slug)]);
  const template = lookup.kind === "found" ? lookup.storefront.theme.template : "watches";
  const list = favorites.ok ? favorites.data.data : [];

  return (
    <AccountSection title="Favoritos" description="Os produtos que você está acompanhando.">
      {list.length > 0 ? (
        <ProductGridLayout template={template} slug={slug} products={list} favorite={(p) => <FavoriteButton key={p.id} slug={slug} productId={p.id} initial loggedIn />} />
      ) : (
        <EmptyState>Toque no coração de um produto para acompanhá-lo aqui.</EmptyState>
      )}
    </AccountSection>
  );
}
