import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { Pagination } from "@/components/store/pagination";
import { FavoriteButton } from "@/components/store/favorite-button";
import { ProductGridLayout } from "@/components/store/templates/cards";
import { getBidder, getFavoriteIds } from "@/lib/bidder-session";
import { getProducts, getStorefront } from "@/lib/storefront";

export async function generateMetadata({ params }: PageProps<"/[slug]/categoria/[categoria]">): Promise<Metadata> {
  const { slug, categoria } = await params;
  const page = await getProducts(slug, { category: categoria });
  return { title: page?.category?.name ?? "Categoria" };
}

export default function CategoryPage(props: PageProps<"/[slug]/categoria/[categoria]">) {
  return (
    <Suspense fallback={<div className="mx-auto h-96 w-full max-w-6xl animate-pulse" aria-busy="true" />}>
      <Category {...props} />
    </Suspense>
  );
}

async function Category({ params, searchParams }: PageProps<"/[slug]/categoria/[categoria]">) {
  const { slug, categoria } = await params;
  const sp = await searchParams;
  const page = typeof sp.pagina === "string" ? Math.max(1, Number(sp.pagina) || 1) : 1;
  const [products, bidder, favorites, lookup] = await Promise.all([getProducts(slug, { category: categoria, page }), getBidder(), getFavoriteIds(), getStorefront(slug)]);
  if (!products?.category || lookup.kind !== "found") notFound();
  const template = lookup.storefront.theme.template;

  return (
    <main className={`mx-auto flex w-full flex-col gap-6 px-4 py-6 @3xl:px-6 @3xl:py-10 ${template === "watches" ? "max-w-[72rem]" : "max-w-[80rem]"}`}>
      <nav aria-label="Você está em" className="text-sm text-store-text-muted">
        <Link href={`/${slug}`} className="hover:text-store-link">Início</Link> <span aria-hidden>/</span> {products.category.name}
      </nav>
      <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">{products.category.name}</h1>
      {products.data.length > 0 ? (
        <>
          <ProductGridLayout
            template={template}
            slug={slug}
            products={products.data}
            favorite={(p) => <FavoriteButton key={`${p.id}-${favorites.includes(p.id)}`} slug={slug} productId={p.id} initial={favorites.includes(p.id)} loggedIn={bidder !== null} />}
          />
          <Pagination base={`/${slug}/categoria/${categoria}`} page={products.page} lastPage={products.last_page} />
        </>
      ) : (
        <p className="rounded-card border border-store-border bg-store-surface p-6 text-center text-store-text-muted">Nenhum produto nesta categoria por enquanto.</p>
      )}
    </main>
  );
}
