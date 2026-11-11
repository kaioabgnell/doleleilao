import { Suspense } from "react";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { templates, type TemplateKey } from "@doleleilao/design-tokens";
import { ModelPreview } from "./model-preview";

export const metadata: Metadata = { title: "Modelo de loja", robots: { index: false } };

export function generateStaticParams() {
  return Object.keys(templates).map((modelo) => ({ modelo }));
}

/**
 * Página interna: um modelo de loja em tela cheia, com os componentes reais e os dados de exemplo.
 * Serve para gerar as capturas da seção "Modelos" do site (public/assets/img/modelo-*.webp).
 */
export default function ModelPage({ params }: PageProps<"/design-system/modelos/[modelo]">) {
  return (
    <Suspense>
      <Model params={params} />
    </Suspense>
  );
}

async function Model({ params }: { params: PageProps<"/design-system/modelos/[modelo]">["params"] }) {
  const { modelo } = await params;
  if (!(modelo in templates)) notFound();
  return <ModelPreview template={modelo as TemplateKey} />;
}
