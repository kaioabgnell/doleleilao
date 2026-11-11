import Link from "next/link";
import type { ReactNode } from "react";
import { ArrowRight, Search } from "lucide-react";
import { BannerCarousel } from "@/components/store/banner-carousel";
import type { Auction, AuctionItem, Product, StoreBanner, StoreCategory } from "@/lib/api-types";
import { formatAuctionDate } from "@/lib/auction-time";
import { formatCents } from "@/lib/money";
import { ProductGridLayout } from "./cards";
import { type ArtFor, ProductFrame, type TemplateKey, Wrap } from "./kit";
import { AuctionWhen } from "./when";

export interface Featured {
  auction: Auction;
  items: AuctionItem[];
}

export interface HomeProps {
  template: TemplateKey;
  slug: string;
  name: string;
  banners: StoreBanner[];
  categories: StoreCategory[];
  /** Leilão em destaque: o ao vivo ou o próximo (a home prioriza o leilão, PRODUCT.md). */
  featured: Featured | null;
  others: Auction[];
  products: Product[];
  q: string;
  pagination: ReactNode;
  favorite: (product: Product) => ReactNode;
  art?: ArtFor;
  /** Prévia no painel: fica dentro de outro formulário, então a busca não pode ser um <form>. */
  preview?: boolean;
}

const primaryButton =
  "inline-flex min-h-12 items-center justify-center gap-2 rounded-field bg-store-primary px-6 font-semibold text-store-on-primary transition-[background-color,transform] duration-200 hover:bg-store-primary-hover active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-store-primary motion-reduce:transition-none";
const textLink = "inline-flex min-h-11 items-center gap-1.5 font-semibold text-store-link underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-store-primary";

/** Home da loja (RF-E01) no arranjo do modelo escolhido (D-07). */
export function StoreHomeLayout(props: HomeProps) {
  if (props.template === "fashion") return <FashionHome {...props} />;
  if (props.template === "electronics") return <ElectronicsHome {...props} />;
  return <WatchesHome {...props} />;
}

function auctionHref(slug: string, auction: Auction) {
  return `/${slug}/leilao/${auction.slug}`;
}

function minimumOf(items: AuctionItem[]): number | null {
  return items.length ? Math.min(...items.map((i) => i.starting_price_cents)) : null;
}

function itemProduct(item: AuctionItem): Pick<Product, "id" | "title" | "photos"> {
  return item.product ?? { id: item.id, title: item.title, photos: [] };
}

/** Capa enviada pelo leiloeiro. Sem ela, a home mostra as fotos dos itens. */
function customCover(auction: Auction): string | null {
  return auction.cover?.custom ? auction.cover.card.webp : null;
}

function CoverImage({ src, className = "", priority = false }: { src: string; className?: string; priority?: boolean }) {
  return (
    // eslint-disable-next-line @next/next/no-img-element -- capa já processada pela API
    <img src={src} alt="" loading={priority ? "eager" : "lazy"} fetchPriority={priority ? "high" : undefined} decoding="async" className={`object-cover ${className}`} />
  );
}

function callToAction(featured: Featured) {
  return featured.auction.status === "live" ? "Assistir ao pregão" : "Ver o leilão";
}

const searchInput =
  "block min-h-11 w-full rounded-field border border-store-border bg-store-surface pr-3 pl-9 text-base text-store-text placeholder:text-store-text-muted focus:border-store-primary focus:outline-2 focus:outline-store-primary/30";

function SearchField({ slug, q, className = "", preview = false }: { slug: string; q: string; className?: string; preview?: boolean }) {
  const icon = <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-store-text-muted" />;
  if (preview) {
    return (
      <div className={`relative ${className}`}>
        {icon}
        <input type="search" readOnly tabIndex={-1} placeholder="Buscar na loja" className={searchInput} />
      </div>
    );
  }
  return (
    <form role="search" action={`/${slug}`} className={`relative ${className}`}>
      <label htmlFor={`q-${slug}`} className="sr-only">Buscar produtos</label>
      {icon}
      <input id={`q-${slug}`} name="q" type="search" defaultValue={q} placeholder="Buscar na loja" className={searchInput} />
    </form>
  );
}

function EmptyCatalog({ q }: { q: string }) {
  return (
    <p className="rounded-card border border-store-border bg-store-surface p-8 text-center text-store-text-muted">
      {q ? "Nenhum produto encontrado. Tente outra palavra." : "Os produtos desta loja aparecem aqui em breve."}
    </p>
  );
}

function Consign({ slug, align = "left" }: { slug: string; align?: "left" | "center" }) {
  return (
    <section aria-labelledby="leiloar" className={`flex flex-col gap-3 ${align === "center" ? "items-center text-center" : ""}`}>
      <h2 id="leiloar" className="font-store-heading text-xl font-bold">Tem algo para leiloar?</h2>
      <p className="max-w-md text-store-text-muted">Conte o que você tem e mande fotos. O leiloeiro responde por WhatsApp ou e-mail.</p>
      <Link href={`/${slug}/quero-leiloar`} className={textLink}>
        Falar com o leiloeiro <ArrowRight aria-hidden className="size-4" />
      </Link>
    </section>
  );
}

/* ---------------- Relógios: vitrine de joalheria ---------------- */

function WatchesHome({ slug, name, banners, categories, featured, others, products, q, pagination, favorite, art, preview }: HomeProps) {
  const lead = featured?.items[0];
  const cover = featured ? customCover(featured.auction) : null;
  const min = featured ? minimumOf(featured.items) : null;
  return (
    <main className="flex flex-col gap-16 pb-20 @3xl:gap-24">
      {featured && (lead || cover) ? (
        <section aria-labelledby="destaque" className="pt-4 @3xl:pt-12">
          <Wrap className="grid gap-8 @3xl:grid-cols-[1.15fr_1fr] @3xl:items-center @3xl:gap-16">
            <Link href={auctionHref(slug, featured.auction)} className="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-store-primary" tabIndex={-1} aria-hidden>
              {cover ? (
                <CoverImage src={cover} priority className="aspect-square w-full rounded-card @3xl:aspect-[4/5]" />
              ) : lead ? (
                <ProductFrame product={itemProduct(lead)} ratio="square" fit="contain" size="zoom" priority art={art} className="w-full rounded-card @3xl:aspect-[4/5]" />
              ) : null}
            </Link>
            <div className="flex flex-col gap-6 @3xl:max-w-md">
              <h1 id="destaque" className="font-store-heading text-4xl leading-[1.05] text-balance @3xl:text-5xl @5xl:text-6xl">{featured.auction.name}</h1>
              <AuctionWhen startsAt={featured.auction.starts_at} live={featured.auction.status === "live"} variant="serif" />
              <p className="text-store-text-muted">
                {featured.items.length} {featured.items.length === 1 ? "peça" : "peças"}
                {min !== null ? <> · lances a partir de <span className="text-store-text tabular">{formatCents(min)}</span></> : null}
              </p>
              <div className="flex flex-wrap items-center gap-4">
                <Link href={auctionHref(slug, featured.auction)} className={primaryButton}>{callToAction(featured)}</Link>
                <a href="#colecao" className={textLink}>Ver a coleção</a>
              </div>
              {featured.items.length > 1 ? (
                <ul className="mt-2 grid grid-cols-3 gap-3 border-t border-store-border pt-6" aria-label="Nesta sessão">
                  {featured.items.slice(1, 4).map((item) => (
                    <li key={item.id} className="flex flex-col gap-2">
                      <ProductFrame product={itemProduct(item)} ratio="square" fit="contain" art={art} className="rounded-field" />
                      <span className="line-clamp-2 text-xs text-store-text-muted">{item.title}</span>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          </Wrap>
        </section>
      ) : (
        <section className="pt-12 text-center @3xl:pt-20">
          <Wrap className="flex flex-col items-center gap-4">
            <h1 className="font-store-heading text-4xl text-balance @3xl:text-6xl">{name}</h1>
            <p className="max-w-md text-store-text-muted">Leilões online com lances ao vivo. O próximo leilão aparece aqui assim que for marcado.</p>
          </Wrap>
        </section>
      )}

      {banners.length > 0 ? <Wrap><BannerCarousel banners={banners} /></Wrap> : null}

      <section id="colecao" aria-labelledby="colecao-titulo" className="scroll-mt-24">
        <Wrap className="flex flex-col gap-10">
          <div className="flex flex-col items-center gap-5 text-center">
            <h2 id="colecao-titulo" className="font-store-heading text-3xl @3xl:text-4xl">{q ? `Resultados para “${q}”` : "A coleção"}</h2>
            {categories.length > 0 ? (
              <nav aria-label="Categorias" className="flex flex-wrap justify-center gap-x-1 text-sm tracking-[0.08em] uppercase">
                {categories.map((c) => (
                  <Link key={c.id} href={`/${slug}/categoria/${c.slug}`} className="flex min-h-11 items-center px-3 text-store-text-muted underline-offset-[6px] transition-colors hover:text-store-text hover:underline">{c.name}</Link>
                ))}
              </nav>
            ) : null}
            <SearchField slug={slug} q={q} className="w-full max-w-sm" preview={preview} />
          </div>
          {products.length > 0 ? <ProductGridLayout template="watches" slug={slug} products={products} favorite={favorite} art={art} /> : <EmptyCatalog q={q} />}
          {pagination}
        </Wrap>
      </section>

      {others.length > 0 ? (
        <section aria-labelledby="agenda">
          <Wrap className="flex flex-col gap-6">
            <h2 id="agenda" className="font-store-heading text-center text-3xl">Próximos leilões</h2>
            <ul className="divide-y divide-store-border border-y border-store-border">
              {others.map((a) => (
                <li key={a.id}>
                  <Link href={auctionHref(slug, a)} className="group flex flex-col gap-1 py-5 @3xl:flex-row @3xl:items-center @3xl:gap-8">
                    <span className="w-56 shrink-0 text-sm text-store-text-muted tabular">{formatAuctionDate(a.starts_at)}</span>
                    <span className="font-store-heading flex-1 text-xl group-hover:text-store-link">{a.name}</span>
                    <span className="text-sm text-store-text-muted">{a.items_count} {a.items_count === 1 ? "peça" : "peças"}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Wrap>
        </section>
      ) : null}

      <Wrap><Consign slug={slug} align="center" /></Wrap>
    </main>
  );
}

/* ---------------- Roupa: editorial ---------------- */

function FashionHome({ slug, name, banners, categories, featured, others, products, q, pagination, favorite, art, preview }: HomeProps) {
  const looks = featured?.items.slice(0, 3) ?? [];
  const cover = featured ? customCover(featured.auction) : null;
  return (
    <main className="flex flex-col gap-14 pb-20 @3xl:gap-20">
      {featured && (cover || looks.length > 0) ? (
        <section aria-labelledby="destaque" className="relative">
          {/* Celular: só a capa (ou a primeira foto), com o bloco do leilão por cima da borda de baixo (cabe na primeira tela). */}
          {cover ? (
            <CoverImage src={cover} priority className="aspect-[4/5] w-full @3xl:aspect-[21/9]" />
          ) : (
            <ul className={`grid grid-cols-1 gap-1 ${looks.length >= 3 ? "@3xl:grid-cols-3" : looks.length === 2 ? "@3xl:grid-cols-2" : ""}`}>
              {looks.map((item, i) => (
                <li key={item.id} className={`group ${i > 0 ? "hidden @3xl:block" : ""}`}>
                  <ProductFrame product={itemProduct(item)} ratio="portrait" fit="cover" size="zoom" priority={i === 0} art={art} className="w-full" />
                </li>
              ))}
            </ul>
          )}
          <Wrap wide className="@3xl:absolute @3xl:inset-x-0 @3xl:bottom-8">
            <div className="relative -mt-28 flex flex-col gap-4 bg-store-surface p-5 shadow-[0_12px_40px_-12px_rgb(0_0_0/0.35)] @3xl:mt-0 @3xl:max-w-md @3xl:p-8">
              <h1 id="destaque" className="font-store-heading text-3xl leading-tight font-bold text-balance @3xl:text-4xl">{featured.auction.name}</h1>
              <AuctionWhen startsAt={featured.auction.starts_at} live={featured.auction.status === "live"} variant="inline" />
              <Link href={auctionHref(slug, featured.auction)} className={`${primaryButton} w-full @3xl:w-fit`}>
                {callToAction(featured)} <ArrowRight aria-hidden className="size-4" />
              </Link>
            </div>
          </Wrap>
        </section>
      ) : banners.length > 0 ? (
        <Wrap wide className="pt-4"><BannerCarousel banners={banners} /></Wrap>
      ) : (
        <Wrap wide className="pt-12"><h1 className="font-store-heading text-5xl font-bold uppercase @3xl:text-7xl">{name}</h1></Wrap>
      )}

      {featured && banners.length > 0 ? <Wrap wide><BannerCarousel banners={banners} /></Wrap> : null}

      {categories.some((c) => c.image_url) ? (
        <section aria-labelledby="categorias">
          <Wrap wide className="flex flex-col gap-5">
            <h2 id="categorias" className="font-store-heading text-2xl font-bold">Categorias</h2>
            <ul className="grid grid-cols-2 gap-1 @3xl:grid-cols-4">
              {categories.slice(0, 8).map((c) => (
                <li key={c.id}>
                  <Link href={`/${slug}/categoria/${c.slug}`} className="group relative block aspect-[4/5] overflow-hidden bg-store-surface">
                    {c.image_url ? (
                      // eslint-disable-next-line @next/next/no-img-element -- imagem já processada
                      <img src={c.image_url} alt="" className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none" />
                    ) : null}
                    <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-4 pt-12 font-semibold text-white">{c.name}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Wrap>
        </section>
      ) : null}

      <section aria-labelledby="pecas">
        <Wrap wide className="flex flex-col gap-6">
          <div className="flex flex-col gap-3 @3xl:flex-row @3xl:items-end @3xl:justify-between">
            <h2 id="pecas" className="font-store-heading text-2xl font-bold @3xl:text-3xl">{q ? `Resultados para “${q}”` : "Peças da loja"}</h2>
            <SearchField slug={slug} q={q} className="@3xl:w-72" preview={preview} />
          </div>
          {products.length > 0 ? <ProductGridLayout template="fashion" slug={slug} products={products} favorite={favorite} art={art} /> : <EmptyCatalog q={q} />}
          {pagination}
        </Wrap>
      </section>

      {others.length > 0 ? (
        <section aria-labelledby="agenda">
          <Wrap wide className="flex flex-col gap-5">
            <h2 id="agenda" className="font-store-heading text-2xl font-bold">Próximos leilões</h2>
            <ul className="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] @3xl:mx-0 @3xl:grid @3xl:grid-cols-3 @3xl:px-0">
              {others.map((a) => (
                <li key={a.id} className="w-64 shrink-0 snap-start @3xl:w-auto">
                  <Link href={auctionHref(slug, a)} className="group flex flex-col gap-2">
                    <span className="relative block aspect-[4/5] overflow-hidden bg-store-surface">
                      {a.cover?.card.webp ? (
                        // eslint-disable-next-line @next/next/no-img-element -- capa já processada
                        <img src={a.cover.card.webp} alt="" loading="lazy" className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none" />
                      ) : null}
                    </span>
                    <span className="font-semibold group-hover:text-store-link">{a.name}</span>
                    <span className="text-sm text-store-text-muted tabular">{formatAuctionDate(a.starts_at)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Wrap>
        </section>
      ) : null}

      <Wrap wide><Consign slug={slug} /></Wrap>
    </main>
  );
}

/* ---------------- Eletrônicos: loja técnica ---------------- */

function ElectronicsHome({ slug, banners, featured, others, products, q, pagination, favorite, art }: HomeProps) {
  const cover = featured ? customCover(featured.auction) : null;
  return (
    <main className="flex flex-col gap-10 pt-5 pb-16 @3xl:gap-14 @3xl:pt-8">
      {featured ? (
        <section aria-labelledby="destaque">
          {/* Com capa, o cartão fica mais largo: capa à esquerda e dados à direita (no celular, capa em cima). */}
          <Wrap wide className={`grid gap-3 ${cover ? "@5xl:grid-cols-[42rem_minmax(0,1fr)]" : "@5xl:grid-cols-[22rem_minmax(0,1fr)]"}`}>
            <div className={`flex flex-col gap-4 rounded-card border border-store-border bg-store-surface p-5 ${cover ? "@3xl:flex-row @3xl:gap-5" : ""}`}>
              {cover ? (
                <Link href={auctionHref(slug, featured.auction)} tabIndex={-1} aria-hidden className="relative block aspect-[16/10] shrink-0 overflow-hidden rounded-field @3xl:aspect-auto @3xl:w-[45%]">
                  <CoverImage src={cover} priority className="absolute inset-0 size-full" />
                </Link>
              ) : null}
              <div className="flex min-w-0 flex-1 flex-col gap-4">
                <h1 id="destaque" className="font-store-heading text-2xl leading-tight font-bold text-balance">{featured.auction.name}</h1>
                <AuctionWhen startsAt={featured.auction.starts_at} live={featured.auction.status === "live"} variant="blocks" />
                <dl className="grid grid-cols-2 gap-2 text-sm">
                  <div className="rounded-field bg-store-bg px-3 py-2">
                    <dt className="text-store-text-muted">Itens</dt>
                    <dd className="text-lg font-bold tabular">{featured.items.length}</dd>
                  </div>
                  <div className="rounded-field bg-store-bg px-3 py-2">
                    <dt className="text-store-text-muted">A partir de</dt>
                    <dd className="text-lg font-bold tabular">{minimumOf(featured.items) === null ? "—" : formatCents(minimumOf(featured.items)!)}</dd>
                  </div>
                </dl>
                <Link href={auctionHref(slug, featured.auction)} className={`${primaryButton} mt-auto w-full`}>{callToAction(featured)}</Link>
              </div>
            </div>
            <ul aria-label="Itens deste leilão" className="-mx-4 flex snap-x gap-2 self-start overflow-x-auto px-4 pb-1 [scrollbar-width:thin] @5xl:mx-0 @5xl:px-0">
              {featured.items.slice(0, 10).map((item, i) => (
                <li key={item.id} className="w-40 shrink-0 snap-start @3xl:w-48">
                  <Link href={auctionHref(slug, featured.auction)} className="group flex h-full flex-col rounded-card border border-store-border bg-store-surface p-2 transition-colors hover:border-store-primary">
                    <ProductFrame product={itemProduct(item)} ratio="square" fit="contain" ground="bg" priority={i < 2} art={art} className="rounded-field" />
                    <span className="mt-2 text-[11px] text-store-text-muted tabular">Item {item.position}</span>
                    <span className="line-clamp-2 text-sm leading-snug font-medium">{item.title}</span>
                    <span className="mt-auto pt-1.5 text-sm font-bold tabular">{formatCents(item.starting_price_cents)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Wrap>
        </section>
      ) : null}

      {banners.length > 0 ? <Wrap wide><BannerCarousel banners={banners} /></Wrap> : null}

      <section aria-labelledby="produtos">
        <Wrap wide className="flex flex-col gap-4">
          <div className="flex items-baseline justify-between gap-3">
            {featured ? (
              <h2 id="produtos" className="font-store-heading text-xl font-bold @3xl:text-2xl">{q ? `Resultados para “${q}”` : "Produtos"}</h2>
            ) : (
              <h1 id="produtos" className="font-store-heading text-xl font-bold @3xl:text-2xl">{q ? `Resultados para “${q}”` : "Produtos"}</h1>
            )}
            <span className="text-sm text-store-text-muted tabular">{products.length} {products.length === 1 ? "item" : "itens"}</span>
          </div>
          {products.length > 0 ? <ProductGridLayout template="electronics" slug={slug} products={products} favorite={favorite} art={art} /> : <EmptyCatalog q={q} />}
          {pagination}
        </Wrap>
      </section>

      {others.length > 0 ? (
        <section aria-labelledby="agenda">
          <Wrap wide className="flex flex-col gap-3">
            <h2 id="agenda" className="font-store-heading text-xl font-bold">Próximos leilões</h2>
            <ul className="divide-y divide-store-border rounded-card border border-store-border bg-store-surface">
              {others.map((a) => (
                <li key={a.id}>
                  <Link href={auctionHref(slug, a)} className="flex min-h-14 items-center gap-4 px-4 py-3 hover:bg-store-bg">
                    <span className="rounded-field bg-store-bg px-2.5 py-1 text-sm font-semibold tabular">{formatAuctionDate(a.starts_at)}</span>
                    <span className="flex-1 font-medium">{a.name}</span>
                    <span className="hidden text-sm text-store-text-muted @3xl:inline">{a.items_count} itens</span>
                    <ArrowRight aria-hidden className="size-4 text-store-text-muted" />
                  </Link>
                </li>
              ))}
            </ul>
          </Wrap>
        </section>
      ) : null}

      <Wrap wide><Consign slug={slug} /></Wrap>
    </main>
  );
}
