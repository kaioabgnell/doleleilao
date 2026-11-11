import type { Metadata } from "next";
import { Suspense } from "react";
import type { StoreCategory } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { ProductForm } from "../product-form";

export const metadata: Metadata = { title: "Novo produto" };

export default function NewProductPage() {
  return (
    <div className="flex flex-col gap-6">
      <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Novo produto</h1>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <NewProduct />
      </Suspense>
    </div>
  );
}

async function NewProduct() {
  const categories = await panelApi<StoreCategory[]>("/auctioneer/categories");
  return <ProductForm product={null} categories={categories.ok ? categories.data : []} />;
}
