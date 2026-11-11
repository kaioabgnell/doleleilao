import Link from "next/link";
import type { ReactNode } from "react";
import { ProductGallery } from "@/components/store/product-gallery";
import { YouTubeFacade } from "@/components/store/youtube-facade";
import type { Product } from "@/lib/api-types";
import { formatCents } from "@/lib/money";
import { StatusTag, type TemplateKey, Wrap } from "./kit";

export interface DetailProps {
  template: TemplateKey;
  slug: string;
  product: Product;
  favorite: ReactNode;
  views: ReactNode;
  /** Situação do produto para quem vê (ainda fora de leilão, em leilão, arrematado). */
  notice: ReactNode;
}

/** Página do produto (RF-E02) no arranjo do modelo; a ficha técnica muda só a apresentação (D-27). */
export function ProductDetailLayout(props: DetailProps) {
  if (props.template === "fashion") return <FashionDetail {...props} />;
  if (props.template === "electronics") return <ElectronicsDetail {...props} />;
  return <WatchesDetail {...props} />;
}

function Crumbs({ slug, product }: { slug: string; product: Product }) {
  return (
    <nav aria-label="Você está em" className="text-sm text-store-text-muted">
      <Link href={`/${slug}`} className="inline-flex min-h-11 items-center hover:text-store-link">Início</Link>
      {product.category ? (
        <>
          <span aria-hidden className="px-2">/</span>
          <Link href={`/${slug}/categoria/${product.category.slug}`} className="inline-flex min-h-11 items-center hover:text-store-link">{product.category.name}</Link>
        </>
      ) : null}
    </nav>
  );
}

function Video({ product }: { product: Product }) {
  if (!product.youtube_video_id) return null;
  return (
    <section aria-labelledby="video" className="flex flex-col gap-3">
      <h2 id="video" className="font-store-heading text-xl font-bold">Vídeo</h2>
      <div className="max-w-3xl">
        <YouTubeFacade videoId={product.youtube_video_id} title={product.title} />
      </div>
    </section>
  );
}

function Description({ product, title = "Descrição" }: { product: Product; title?: string }) {
  if (!product.description) return null;
  return (
    <section aria-labelledby="descricao" className="flex flex-col gap-2">
      <h2 id="descricao" className="font-store-heading text-lg font-bold">{title}</h2>
      <p className="max-w-[65ch] whitespace-pre-line text-store-text-muted">{product.description}</p>
    </section>
  );
}

/* Relógios: uma peça, muito ar, ficha em lista elegante e espaçada. */
function WatchesDetail({ slug, product, favorite, views, notice }: DetailProps) {
  return (
    <main className="pb-20">
      <Wrap className="flex flex-col gap-6 pt-4">
        <Crumbs slug={slug} product={product} />
        <div className="grid gap-10 @5xl:grid-cols-[1.1fr_1fr] @5xl:gap-16">
          <ProductGallery photos={product.photos} title={product.title} />
          <div className="flex flex-col gap-8 @5xl:pt-6">
            <div className="flex flex-col gap-4">
              <div className="flex items-start justify-between gap-4">
                <h1 className="font-store-heading text-3xl leading-[1.1] text-balance @3xl:text-5xl">{product.title}</h1>
                {favorite}
              </div>
              <StatusTag status={product.status} className="w-fit px-3 py-1 text-xs" />
            </div>
            <div className="flex flex-col gap-1 border-y border-store-border py-6">
              <span className="text-xs font-medium tracking-[0.14em] text-store-text-muted uppercase">Lance inicial</span>
              <span className="text-4xl font-semibold tabular">{formatCents(product.starting_price_cents)}</span>
              <span className="text-sm text-store-text-muted">
                Incremento <span className="text-store-text tabular">{formatCents(product.increment_cents)}</span>
                {product.reference_price_cents ? <> · referência <span className="text-store-text tabular">{formatCents(product.reference_price_cents)}</span></> : null}
              </span>
            </div>
            {notice}
            {product.attributes.length > 0 ? (
              <section aria-labelledby="ficha" className="flex flex-col gap-2">
                <h2 id="ficha" className="font-store-heading text-xl">Detalhes da peça</h2>
                <dl>
                  {product.attributes.map((a) => (
                    <div key={a.name} className="flex items-baseline justify-between gap-6 border-b border-store-border py-4">
                      <dt className="text-xs tracking-[0.12em] text-store-text-muted uppercase">{a.name}</dt>
                      <dd className="text-right">{a.value}</dd>
                    </div>
                  ))}
                </dl>
              </section>
            ) : null}
            <Description product={product} title="Sobre a peça" />
            {views}
          </div>
        </div>
        <Video product={product} />
      </Wrap>
    </main>
  );
}

/* Roupa: fotos grandes empilhadas, informações fixas ao lado, ficha curta junto do título. */
function FashionDetail({ slug, product, favorite, views, notice }: DetailProps) {
  const photos = product.photos.filter((p) => p.status === "ready" && p.zoom.webp);
  return (
    <main className="pb-20">
      <Wrap wide className="flex flex-col gap-4 pt-2">
        <Crumbs slug={slug} product={product} />
        <div className="grid gap-8 @5xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] @5xl:gap-12">
          <ul aria-label="Fotos" className="-mx-4 flex snap-x snap-mandatory gap-1 overflow-x-auto [scrollbar-width:none] @3xl:mx-0 @5xl:grid @5xl:grid-cols-2 @5xl:overflow-visible">
            {photos.length === 0 ? <li className="aspect-[4/5] w-full bg-store-surface" /> : null}
            {photos.map((p, i) => (
              <li key={p.id} className={`w-[85%] shrink-0 snap-start @3xl:w-[60%] @5xl:w-auto ${i === 0 && photos.length > 2 ? "@5xl:col-span-2" : ""}`}>
                <picture>
                  {p.zoom.avif ? <source type="image/avif" srcSet={p.zoom.avif} /> : null}
                  <img src={p.zoom.webp!} alt={`${product.title}, foto ${i + 1} de ${photos.length}`} loading={i === 0 ? "eager" : "lazy"} fetchPriority={i === 0 ? "high" : undefined} className="aspect-[4/5] w-full bg-store-surface object-cover" />
                </picture>
              </li>
            ))}
          </ul>
          <div className="flex flex-col gap-6 @5xl:sticky @5xl:top-28 @5xl:self-start">
            <div className="flex flex-col gap-3">
              <div className="flex items-start justify-between gap-4">
                <h1 className="font-store-heading text-2xl leading-tight font-bold text-balance @3xl:text-3xl">{product.title}</h1>
                {favorite}
              </div>
              {product.attributes.length > 0 ? (
                <p className="text-sm text-store-text-muted">
                  {product.attributes.slice(0, 4).map((a, i) => (
                    <span key={a.name}>
                      {i > 0 ? <span aria-hidden className="px-1.5">·</span> : null}
                      {a.name}: <span className="text-store-text">{a.value}</span>
                    </span>
                  ))}
                </p>
              ) : null}
              <StatusTag status={product.status} className="w-fit px-3 py-1 text-xs" />
            </div>
            <p className="flex flex-col gap-1 @xl:flex-row @xl:items-baseline @xl:gap-3">
              <span className="text-3xl font-bold tabular">{formatCents(product.starting_price_cents)}</span>
              <span className="text-sm text-store-text-muted">
                lance inicial · incremento <span className="tabular">{formatCents(product.increment_cents)}</span>
              </span>
            </p>
            {notice}
            <Description product={product} title="Sobre a peça" />
            {product.attributes.length > 4 ? (
              <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                {product.attributes.slice(4).map((a) => (
                  <div key={a.name} className="contents">
                    <dt className="text-store-text-muted">{a.name}</dt>
                    <dd>{a.value}</dd>
                  </div>
                ))}
              </dl>
            ) : null}
            {views}
          </div>
        </div>
        <Video product={product} />
      </Wrap>
    </main>
  );
}

/* Eletrônicos: galeria com miniaturas laterais, caixa de valores e ficha técnica em tabela. */
function ElectronicsDetail({ slug, product, favorite, views, notice }: DetailProps) {
  return (
    <main className="pb-16">
      <Wrap wide className="flex flex-col gap-6 pt-2">
        <Crumbs slug={slug} product={product} />
        <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem] @5xl:gap-8">
          <div className="rounded-card border border-store-border bg-store-surface p-3">
            <ProductGallery photos={product.photos} title={product.title} thumbs="side" />
          </div>
          <div className="flex flex-col gap-4 rounded-card border border-store-border bg-store-surface p-5 @5xl:sticky @5xl:top-32 @5xl:self-start">
            <div className="flex items-start justify-between gap-3">
              <h1 className="font-store-heading text-xl leading-snug font-bold @3xl:text-2xl">{product.title}</h1>
              {favorite}
            </div>
            <StatusTag status={product.status} className="w-fit px-3 py-1 text-xs" />
            <dl className="grid grid-cols-2 gap-2">
              <div className="col-span-2 rounded-field bg-store-bg p-3">
                <dt className="text-xs text-store-text-muted">Lance inicial</dt>
                <dd className="text-3xl font-bold tabular">{formatCents(product.starting_price_cents)}</dd>
              </div>
              <div className="rounded-field bg-store-bg p-3">
                <dt className="text-xs text-store-text-muted">Incremento</dt>
                <dd className="font-semibold tabular">{formatCents(product.increment_cents)}</dd>
              </div>
              <div className="rounded-field bg-store-bg p-3">
                <dt className="text-xs text-store-text-muted">Referência</dt>
                <dd className="font-semibold tabular">{product.reference_price_cents ? formatCents(product.reference_price_cents) : "—"}</dd>
              </div>
            </dl>
            {notice}
            {views}
          </div>
        </div>
        {product.attributes.length > 0 ? (
          <section aria-labelledby="ficha" className="flex flex-col gap-3">
            <h2 id="ficha" className="font-store-heading text-xl font-bold">Ficha técnica</h2>
            <table className="w-full overflow-hidden rounded-card border border-store-border text-sm">
              <caption className="sr-only">Ficha técnica de {product.title}</caption>
              <tbody>
                {product.attributes.map((a, i) => (
                  <tr key={a.name} className={i % 2 === 0 ? "bg-store-surface" : "bg-store-bg"}>
                    <th scope="row" className="w-2/5 px-4 py-3 text-left font-medium text-store-text-muted">{a.name}</th>
                    <td className="px-4 py-3 font-medium">{a.value}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        ) : null}
        <Description product={product} />
        <Video product={product} />
      </Wrap>
    </main>
  );
}
