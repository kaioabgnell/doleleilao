import Link from "next/link";
import { ImageOff } from "lucide-react";
import type { Product } from "@/lib/api-types";
import { formatCents } from "@/lib/money";
import { FavoriteButton } from "./favorite-button";

export function ProductPicture({ product, priority = false, className = "" }: { product: Product; priority?: boolean; className?: string }) {
  const photo = product.photos.find((p) => p.status === "ready");
  if (!photo?.card.webp) {
    return (
      <span className={`flex items-center justify-center bg-store-border/40 text-store-text-muted ${className}`}>
        <ImageOff aria-hidden className="size-6" />
      </span>
    );
  }
  return (
    <picture>
      {photo.card.avif ? <source type="image/avif" srcSet={photo.card.avif} /> : null}
      { }
      <img
        src={photo.card.webp}
        alt={product.title}
        width={640}
        height={photo.height && photo.width ? Math.round((640 * photo.height) / photo.width) : 640}
        loading={priority ? "eager" : "lazy"}
        fetchPriority={priority ? "high" : undefined}
        className={`object-cover ${className}`}
      />
    </picture>
  );
}

/** Card de produto da vitrine (modelo "base"; os 3 modelos definitivos trocam este ponto na Fase 7). */
export function ProductCard({
  slug,
  product,
  favorite,
  loggedIn,
  priority = false,
}: {
  slug: string;
  product: Product;
  favorite: boolean;
  loggedIn: boolean;
  priority?: boolean;
}) {
  return (
    <article className="group relative flex flex-col overflow-hidden rounded-card border border-store-border bg-store-surface">
      <Link href={`/${slug}/produto/${product.slug}`} className="flex flex-col focus-visible:outline-2 focus-visible:outline-store-primary">
        <ProductPicture product={product} priority={priority} className="aspect-square w-full" />
        <div className="flex flex-col gap-1 p-3">
          <h3 className="font-store-heading line-clamp-2 text-sm leading-snug font-semibold sm:text-base">{product.title}</h3>
          <p className="text-xs text-store-text-muted sm:text-sm">
            Lance inicial <span className="font-semibold text-store-text tabular">{formatCents(product.starting_price_cents)}</span>
          </p>
        </div>
      </Link>
      {product.status === "in_auction" ? (
        <span className="absolute top-2 left-2 rounded-full bg-state-live px-2 py-0.5 text-[11px] font-semibold text-white">Em leilão</span>
      ) : product.status === "sold" ? (
        <span className="absolute top-2 left-2 rounded-full bg-state-closed px-2 py-0.5 text-[11px] font-semibold text-white">Arrematado</span>
      ) : null}
      <FavoriteButton key={`${product.id}-${favorite}`} slug={slug} productId={product.id} initial={favorite} loggedIn={loggedIn} className="absolute top-2 right-2" />
    </article>
  );
}

export function ProductGrid({ slug, products, favorites, loggedIn }: { slug: string; products: Product[]; favorites: string[]; loggedIn: boolean }) {
  return (
    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      {products.map((product, index) => (
        <li key={product.id}>
          <ProductCard slug={slug} product={product} favorite={favorites.includes(product.id)} loggedIn={loggedIn} priority={index < 2} />
        </li>
      ))}
    </ul>
  );
}
