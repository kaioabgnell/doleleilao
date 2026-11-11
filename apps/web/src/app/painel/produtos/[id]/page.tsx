import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { Suspense } from "react";
import { ProductStatusBadge } from "@/components/panel/product-status-badge";
import type { Product, ProductHistoryEntry, StoreCategory } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { ProductForm } from "../product-form";
import { LockedProduct, ProductHistory } from "./product-history";

export const metadata: Metadata = { title: "Produto" };

export default function EditProductPage(props: PageProps<"/painel/produtos/[id]">) {
  return (
    <div className="flex flex-col gap-4">
      <Suspense fallback={<BackLink href="/painel/produtos" label="Produtos" />}>
        <Back searchParams={props.searchParams} />
      </Suspense>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <EditProduct params={props.params} />
      </Suspense>
    </div>
  );
}

/** Quem veio do pregão (`?pregao={lote}`) volta para ele; senão, para a lista de produtos. */
async function Back({ searchParams }: Pick<PageProps<"/painel/produtos/[id]">, "searchParams">) {
  const pregao = (await searchParams).pregao;
  return typeof pregao === "string" && /^[0-9a-z]{26}$/.test(pregao) ? (
    <BackLink href={`/painel/leiloes/${pregao}/pregao`} label="Pregão" />
  ) : (
    <BackLink href="/painel/produtos" label="Produtos" />
  );
}

function BackLink({ href, label }: { href: string; label: string }) {
  return (
    <Link href={href} className="inline-flex min-h-11 w-fit items-center gap-1 text-sm font-medium text-dl-copper hover:underline">
      <ArrowLeft aria-hidden className="size-4" />
      {label}
    </Link>
  );
}

async function EditProduct({ params }: Pick<PageProps<"/painel/produtos/[id]">, "params">) {
  const { id } = await params;
  if (!/^[0-9a-z]{26}$/.test(id)) notFound();
  const [product, categories, history] = await Promise.all([
    panelApi<Product>(`/auctioneer/products/${id}`),
    panelApi<StoreCategory[]>("/auctioneer/categories"),
    panelApi<{ data: ProductHistoryEntry[] }>(`/auctioneer/products/${id}/history`),
  ]);
  if (!product.ok) notFound();
  const entries = history.ok ? history.data.data : [];
  const locked = product.data.status === "in_auction" || product.data.status === "sold";

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">{product.data.title}</h1>
        <ProductStatusBadge status={product.data.status} />
      </div>
      {locked ? (
        <LockedProduct product={product.data} history={entries} />
      ) : (
        <>
          <ProductForm product={product.data} categories={categories.ok ? categories.data : []} />
          <ProductHistory history={entries} />
        </>
      )}
    </div>
  );
}
