import type { ReactNode } from "react";
import { ImageOff } from "lucide-react";
import type { Product, ProductStatus } from "@/lib/api-types";

/**
 * Peças comuns aos 3 modelos de loja (D-07). Os componentes dos modelos são só apresentação:
 * a loja real e a pré-visualização do painel usam os mesmos. As medidas reagem ao tamanho da
 * loja (container queries `@3xl:` ≈ tablet, `@5xl:` ≈ computador), não da janela, para a
 * pré-visualização mostrar o celular e o computador de verdade.
 */

export type TemplateKey = "watches" | "fashion" | "electronics";

/** Ilustração no lugar da foto (pré-visualização do painel, sem fotos reais). */
export type ArtFor = (productId: string) => ReactNode;

/**
 * Moldura da foto. `contain`: a peça inteira sobre um fundo neutro, com respiro — fica bem com foto
 * de celular (Relógios, Eletrônicos). `cover`: a foto preenche o quadro (Roupa, editorial).
 */
export function ProductFrame({
  product,
  ratio,
  fit,
  priority = false,
  art,
  size = "card",
  ground = "surface",
  className = "",
}: {
  product: Pick<Product, "id" | "title" | "photos">;
  ratio: "square" | "portrait" | "landscape";
  fit: "contain" | "cover";
  priority?: boolean;
  art?: ArtFor;
  size?: "card" | "zoom";
  /** Fundo atrás da foto: a superfície da loja ou o fundo da página. */
  ground?: "surface" | "bg";
  className?: string;
}) {
  const aspect = ratio === "portrait" ? "aspect-[4/5]" : ratio === "landscape" ? "aspect-[4/3]" : "aspect-square";
  const photo = product.photos.find((p) => p.status === "ready");
  const source = photo ? (size === "zoom" ? photo.zoom : photo.card) : null;
  const pad = fit === "contain" ? "p-[8%]" : "";

  return (
    <span className={`relative block overflow-hidden ${ground === "bg" ? "bg-store-bg" : "bg-store-surface"} ${aspect} ${className}`}>
      {art ? (
        <span aria-hidden className={`absolute inset-0 block ${fit === "contain" ? "p-[12%]" : "p-[6%]"}`}>
          {art(product.id)}
        </span>
      ) : source?.webp ? (
        <picture>
          {source.avif ? <source type="image/avif" srcSet={source.avif} /> : null}
          <img
            src={source.webp}
            alt={product.title}
            loading={priority ? "eager" : "lazy"}
            fetchPriority={priority ? "high" : undefined}
            decoding="async"
            className={`absolute inset-0 size-full ${fit === "contain" ? "object-contain" : "object-cover"} ${pad} transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100`}
          />
        </picture>
      ) : (
        <span className="absolute inset-0 flex items-center justify-center text-store-text-muted">
          <ImageOff aria-hidden className="size-6" />
        </span>
      )}
    </span>
  );
}

const statusText: Partial<Record<ProductStatus, string>> = { in_auction: "Em leilão", sold: "Arrematado" };

/** Selo de estado do produto, nas cores fixas de estado (D-05). */
export function StatusTag({ status, className = "" }: { status: ProductStatus; className?: string }) {
  const text = statusText[status];
  if (!text) return null;
  return (
    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold text-white ${status === "sold" ? "bg-state-closed" : "bg-state-live"} ${className}`}>
      {text}
    </span>
  );
}

/** Container da loja: largura máxima e respiro lateral iguais dos dois lados. */
export function Wrap({ children, wide = false, className = "" }: { children: ReactNode; wide?: boolean; className?: string }) {
  return <div className={`mx-auto w-full px-4 @3xl:px-6 ${wide ? "max-w-[80rem]" : "max-w-[72rem]"} ${className}`}>{children}</div>;
}
