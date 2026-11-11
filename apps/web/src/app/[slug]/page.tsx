import { Suspense } from "react";
import { FavoriteButton } from "@/components/store/favorite-button";
import { Pagination } from "@/components/store/pagination";
import { type Featured, StoreHomeLayout } from "@/components/store/templates/home";
import { getAuction, getAuctions } from "@/lib/auctions";
import { getBidder, getFavoriteIds } from "@/lib/bidder-session";
import { getProducts, getStorefront } from "@/lib/storefront";

/**
 * Home da loja (RF-E01): o leilão em destaque primeiro (ao vivo ou o próximo), depois a vitrine,
 * os outros leilões e o "quero leiloar". O arranjo vem do modelo escolhido pelo leiloeiro (D-07).
 */
export default function StoreHomePage({ params, searchParams }: PageProps<"/[slug]">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-6xl animate-pulse px-4 py-6" aria-busy="true" />}>
      <StoreHome params={params} searchParams={searchParams} />
    </Suspense>
  );
}

async function StoreHome({ params, searchParams }: Pick<PageProps<"/[slug]">, "params" | "searchParams">) {
  const { slug } = await params;
  const sp = await searchParams;
  const q = typeof sp.q === "string" ? sp.q.slice(0, 120) : "";
  const page = typeof sp.pagina === "string" ? Math.max(1, Number(sp.pagina) || 1) : 1;
  const [lookup, products, bidder, favorites, auctions] = await Promise.all([
    getStorefront(slug),
    getProducts(slug, { q, page }),
    getBidder(),
    getFavoriteIds(),
    getAuctions(slug, "upcoming"),
  ]);
  if (lookup.kind !== "found") return null;
  const { storefront } = lookup;

  // Busca e páginas seguintes mostram só a vitrine.
  const browsing = q !== "" || page > 1;
  const lead = !browsing ? auctions[0] : undefined;
  const details = lead ? await getAuction(slug, lead.slug) : null;
  const featured: Featured | null = details ? { auction: details.auction, items: details.auction.items ?? [] } : null;

  return (
    <StoreHomeLayout
      template={storefront.theme.template}
      slug={slug}
      name={storefront.display_name}
      banners={browsing ? [] : storefront.banners}
      categories={storefront.categories}
      featured={featured}
      others={browsing ? [] : auctions.slice(featured ? 1 : 0, 6)}
      products={products?.data ?? []}
      q={q}
      pagination={products ? <Pagination base={`/${slug}`} page={products.page} lastPage={products.last_page} query={q ? { q } : {}} /> : null}
      favorite={(product) => (
        <FavoriteButton key={`${product.id}-${favorites.includes(product.id)}`} slug={slug} productId={product.id} initial={favorites.includes(product.id)} loggedIn={bidder !== null} />
      )}
    />
  );
}
