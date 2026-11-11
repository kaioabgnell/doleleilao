import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { FavoriteButton } from "@/components/store/favorite-button";
import { ViewCounter } from "@/components/store/view-counter";
import { ProductDetailLayout } from "@/components/store/templates/product-detail";
import { getBidder, getFavoriteIds } from "@/lib/bidder-session";
import { formatCents } from "@/lib/money";
import { getProduct, getStorefront } from "@/lib/storefront";

export async function generateMetadata({ params }: PageProps<"/[slug]/produto/[produto]">): Promise<Metadata> {
  const { slug, produto } = await params;
  const product = await getProduct(slug, produto);
  if (!product) return { title: "Produto não encontrado" };
  const image = product.photos.find((p) => p.status === "ready")?.card.webp;
  return {
    title: product.title,
    description: product.description?.slice(0, 160) ?? `Lance inicial ${formatCents(product.starting_price_cents)}.`,
    openGraph: image ? { images: [image] } : undefined,
  };
}

export default function ProductPage(props: PageProps<"/[slug]/produto/[produto]">) {
  return (
    <Suspense fallback={<div className="mx-auto h-[32rem] w-full max-w-6xl animate-pulse" aria-busy="true" />}>
      <ProductDetail params={props.params} />
    </Suspense>
  );
}

async function ProductDetail({ params }: Pick<PageProps<"/[slug]/produto/[produto]">, "params">) {
  const { slug, produto } = await params;
  const [product, bidder, favorites, lookup] = await Promise.all([getProduct(slug, produto), getBidder(), getFavoriteIds(), getStorefront(slug)]);
  if (!product || lookup.kind !== "found") notFound();
  const favorite = favorites.includes(product.id);

  const notice =
    product.status === "in_auction" ? (
      <p className="rounded-card border border-state-live/30 bg-state-live/5 p-4 text-sm">
        Este produto está num leilão.{" "}
        <Link href={`/${slug}/leiloes`} className="font-semibold text-store-link underline-offset-4 hover:underline">
          Ver os leilões
        </Link>
      </p>
    ) : product.status === "sold" ? (
      <p className="rounded-card border border-store-border bg-store-surface p-4 text-sm text-store-text-muted">Este produto já foi arrematado.</p>
    ) : (
      <p className="rounded-card border border-store-border bg-store-surface p-4 text-sm text-store-text-muted">
        Este produto ainda não está num leilão. {bidder ? "Favorite para acompanhar." : "Cadastre-se na loja para dar lances quando ele entrar num leilão."}
      </p>
    );

  return (
    <ProductDetailLayout
      template={lookup.storefront.theme.template}
      slug={slug}
      product={product}
      favorite={<FavoriteButton key={`${product.id}-${favorite}`} slug={slug} productId={product.id} initial={favorite} loggedIn={bidder !== null} className="shrink-0 border border-store-border" />}
      views={<ViewCounter slug={slug} productSlug={product.slug} initial={product.views_count} />}
      notice={notice}
    />
  );
}
