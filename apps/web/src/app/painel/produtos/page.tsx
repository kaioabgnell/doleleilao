import type { Metadata } from "next";
import Link from "next/link";
import { Eye, ImageOff, Package, Plus } from "lucide-react";
import { Suspense } from "react";
import { ProductStatusBadge } from "@/components/panel/product-status-badge";
import { buttonClass } from "@/components/ui/button-styles";
import { inputClass } from "@/components/ui/field";
import type { ProductPage } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { formatCents } from "@/lib/money";

export const metadata: Metadata = { title: "Produtos" };

const tabs = [
  { key: "", label: "Todos" },
  { key: "available", label: "Disponíveis" },
  { key: "draft", label: "Rascunhos" },
] as const;

export default function ProductsPage(props: PageProps<"/painel/produtos">) {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Produtos</h1>
          <p className="mt-1 text-dl-ink-muted">Seu catálogo. Os disponíveis aparecem na loja e podem entrar num leilão.</p>
        </div>
        <Link href="/painel/produtos/novo" className={buttonClass("primary")}>
          <Plus aria-hidden className="size-5" />
          Novo produto
        </Link>
      </div>
      <Suspense fallback={<div className="h-64 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <ProductList searchParams={props.searchParams} />
      </Suspense>
    </div>
  );
}

async function ProductList({ searchParams }: Pick<PageProps<"/painel/produtos">, "searchParams">) {
  const params = await searchParams;
  const status = typeof params.status === "string" ? params.status : "";
  const q = typeof params.q === "string" ? params.q : "";
  const page = typeof params.pagina === "string" ? Number(params.pagina) || 1 : 1;
  const query = new URLSearchParams({ ...(status && { status }), ...(q && { q }), page: String(page) });
  const result = await panelApi<ProductPage>(`/auctioneer/products?${query}`);
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar os produtos.");
  const { data, counts = {}, last_page } = result.data;
  const total = Object.values(counts).reduce((a, b) => a + (b ?? 0), 0);
  const link = (extra: Record<string, string>) => `/painel/produtos?${new URLSearchParams({ ...(status && { status }), ...(q && { q }), ...extra })}`;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav aria-label="Filtrar por status" className="flex gap-1 overflow-x-auto">
          {tabs.map((tab) => {
            const count = tab.key === "" ? total : (counts[tab.key] ?? 0);
            const active = status === tab.key;
            return (
              <Link
                key={tab.key}
                href={`/painel/produtos?${new URLSearchParams({ ...(tab.key && { status: tab.key }), ...(q && { q }) })}`}
                aria-current={active ? "page" : undefined}
                className={`flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium ${active ? "bg-dl-ink text-white" : "bg-dl-card text-dl-ink-muted hover:text-dl-ink"}`}
              >
                {tab.label} <span className="tabular opacity-70">{count}</span>
              </Link>
            );
          })}
        </nav>
        <form className="sm:w-72" role="search">
          {status ? <input type="hidden" name="status" value={status} /> : null}
          <label htmlFor="q" className="sr-only">
            Buscar produto
          </label>
          <input id="q" name="q" type="search" defaultValue={q} placeholder="Buscar pelo título" className={inputClass} />
        </form>
      </div>

      {data.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-card border border-dashed border-dl-line bg-dl-card px-6 py-12 text-center">
          <Package aria-hidden className="size-8 text-dl-copper-icon" />
          <p className="font-semibold text-dl-ink">{q || status ? "Nenhum produto encontrado" : "Nenhum produto ainda"}</p>
          <p className="max-w-sm text-sm text-dl-ink-muted">
            Cadastre fotos, valor inicial e incremento. Com pelo menos 1 foto, o produto pode ficar disponível na loja.
          </p>
          {!q && !status ? (
            <Link href="/painel/produtos/novo" className={buttonClass("primary", "mt-2")}>
              Cadastrar o primeiro produto
            </Link>
          ) : null}
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((product) => (
            <li key={product.id}>
              <Link
                href={`/painel/produtos/${product.id}`}
                className="flex gap-3 rounded-card border border-dl-line bg-dl-card p-3 hover:border-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper"
              >
                {product.photos[0]?.thumb ? (
                  // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                  <img src={product.photos[0].thumb} alt="" className="size-20 shrink-0 rounded-field object-cover" />
                ) : (
                  <span className="flex size-20 shrink-0 items-center justify-center rounded-field bg-dl-page text-dl-ink-muted">
                    <ImageOff aria-hidden className="size-6" />
                  </span>
                )}
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                  <span className="line-clamp-2 font-semibold text-dl-ink">{product.title}</span>
                  <span className="text-sm text-dl-ink-muted">
                    Lance inicial <span className="font-semibold text-dl-ink tabular">{formatCents(product.starting_price_cents)}</span>
                  </span>
                  <span className="mt-auto flex items-center gap-3">
                    <ProductStatusBadge status={product.status} />
                    <span className="flex items-center gap-1 text-xs text-dl-ink-muted tabular" title="Visualizações">
                      <Eye aria-hidden className="size-3.5" />
                      {product.views_count}
                    </span>
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      {last_page > 1 ? (
        <nav aria-label="Páginas" className="flex justify-center gap-2">
          {page > 1 ? <Link href={link({ pagina: String(page - 1) })} className={buttonClass("secondary")}>Anterior</Link> : null}
          <span className="flex min-h-11 items-center px-3 text-sm text-dl-ink-muted tabular">
            {page} de {last_page}
          </span>
          {page < last_page ? <Link href={link({ pagina: String(page + 1) })} className={buttonClass("secondary")}>Próxima</Link> : null}
        </nav>
      ) : null}
    </div>
  );
}
