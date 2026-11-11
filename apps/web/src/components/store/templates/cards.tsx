import Link from "next/link";
import type { ReactNode } from "react";
import type { Product } from "@/lib/api-types";
import { formatCents } from "@/lib/money";
import { type ArtFor, ProductFrame, StatusTag, type TemplateKey } from "./kit";

export interface GridProps {
  template: TemplateKey;
  slug: string;
  products: Product[];
  /** Coração de favorito (a loja passa o botão de verdade; a prévia, um enfeite). */
  favorite: (product: Product) => ReactNode;
  art?: ArtFor;
}

/** Vitrine de produtos no arranjo de cada modelo (D-07: card de item). */
export function ProductGridLayout(props: GridProps) {
  if (props.template === "fashion") return <FashionGrid {...props} />;
  if (props.template === "electronics") return <ElectronicsGrid {...props} />;
  return <WatchesGrid {...props} />;
}

const lift = "transition-transform duration-300 ease-out hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0";

/** Relógios: uma peça por linha no celular, foto inteira com respiro, nome e valor centralizados. */
function WatchesGrid({ slug, products, favorite, art }: GridProps) {
  return (
    <ul className="grid grid-cols-1 gap-y-12 @xl:grid-cols-2 @xl:gap-x-6 @5xl:grid-cols-3 @5xl:gap-x-10 @5xl:gap-y-16">
      {products.map((product, i) => (
        <li key={product.id} className={`group relative ${lift}`}>
          <Link href={`/${slug}/produto/${product.slug}`} className="flex flex-col items-center gap-4 text-center focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-store-primary">
            <ProductFrame product={product} ratio="square" fit="contain" priority={i < 2} art={art} className="w-full rounded-card" />
            <span className="flex flex-col gap-1.5 px-4">
              <span className="font-store-heading text-lg leading-snug text-balance @3xl:text-xl">{product.title}</span>
              <span className="text-xs font-medium tracking-[0.12em] text-store-text-muted uppercase">
                Lance inicial <span className="ml-1 font-sans text-sm tracking-normal text-store-text normal-case tabular">{formatCents(product.starting_price_cents)}</span>
              </span>
            </span>
          </Link>
          <StatusTag status={product.status} className="absolute top-3 left-3" />
          <span className="absolute top-2 right-2">{favorite(product)}</span>
        </li>
      ))}
    </ul>
  );
}

/** Roupa: grade editorial 4:5, foto dominante, a primeira peça maior no computador. */
function FashionGrid({ slug, products, favorite, art }: GridProps) {
  return (
    <ul className="grid grid-cols-2 gap-x-3 gap-y-8 @3xl:grid-cols-3 @5xl:grid-cols-4 @5xl:gap-x-4 @5xl:gap-y-10">
      {products.map((product, i) => {
        const featured = i === 0 && products.length > 4;
        return (
          <li key={product.id} className={`group relative ${featured ? "@5xl:col-span-2 @5xl:row-span-2" : ""}`}>
            <Link href={`/${slug}/produto/${product.slug}`} className="flex h-full flex-col gap-2.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-store-primary">
              <ProductFrame product={product} ratio="portrait" fit="cover" priority={i < 2} art={art} className={featured ? "w-full @5xl:flex-1 @5xl:aspect-auto" : "w-full"} />
              <span className="flex items-baseline justify-between gap-3">
                <span className={`line-clamp-2 leading-snug ${featured ? "font-store-heading text-base @5xl:text-xl" : "text-sm"}`}>{product.title}</span>
                <span className="shrink-0 text-sm font-semibold tabular">{formatCents(product.starting_price_cents)}</span>
              </span>
            </Link>
            <StatusTag status={product.status} className="absolute top-2 left-2" />
            <span className="absolute top-1 right-1">{favorite(product)}</span>
          </li>
        );
      })}
    </ul>
  );
}

/** Eletrônicos: grade densa, foto contida, ficha resumida e valor em destaque no próprio card. */
function ElectronicsGrid({ slug, products, favorite, art }: GridProps) {
  return (
    <ul className="grid grid-cols-2 gap-2 @3xl:grid-cols-3 @3xl:gap-3 @5xl:grid-cols-4 @6xl:grid-cols-5">
      {products.map((product, i) => (
        <li key={product.id} className={`group relative flex rounded-card border border-store-border bg-store-surface transition-[transform,border-color] duration-300 ease-out hover:-translate-y-0.5 hover:border-store-primary motion-reduce:transition-none motion-reduce:hover:translate-y-0`}>
          <Link href={`/${slug}/produto/${product.slug}`} className="flex w-full flex-col rounded-card focus-visible:outline-2 focus-visible:outline-store-primary">
            <ProductFrame product={product} ratio="square" fit="contain" ground="bg" priority={i < 2} art={art} className="m-2 rounded-field" />
            <span className="flex flex-1 flex-col gap-2 px-3 pb-3">
              <span className="line-clamp-2 text-sm leading-snug font-medium">{product.title}</span>
              {product.attributes.length > 0 ? (
                <span className="flex flex-col gap-0.5 text-xs text-store-text-muted">
                  {product.attributes.slice(0, 2).map((a) => (
                    <span key={a.name} className="truncate">
                      {a.name}: <span className="text-store-text">{a.value}</span>
                    </span>
                  ))}
                </span>
              ) : null}
              <span className="mt-auto flex flex-col border-t border-store-border pt-2">
                <span className="text-[11px] text-store-text-muted">Lance inicial</span>
                <span className="text-lg leading-tight font-bold tabular">{formatCents(product.starting_price_cents)}</span>
              </span>
            </span>
          </Link>
          <StatusTag status={product.status} className="absolute top-3.5 left-3.5" />
          <span className="absolute top-2.5 right-2.5">{favorite(product)}</span>
        </li>
      ))}
    </ul>
  );
}
