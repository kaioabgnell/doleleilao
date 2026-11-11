import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";

export const metadata: Metadata = { title: "Loja não encontrada", robots: { index: false } };

/** Endereço que não é de nenhuma loja no ar (o proxy responde com 404 e mostra esta página). */
export default function StoreNotFoundPage() {
  return (
    <main className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 px-4 py-16 text-center">
      <Image src="/brand/simbolo.svg" alt="" width={48} height={48} priority />
      <h1 className="font-brand text-2xl font-bold text-dl-ink">Loja não encontrada</h1>
      <p className="text-dl-ink-muted">
        Confira o endereço. Pode ser que a loja tenha mudado de endereço há mais de 90 dias ou ainda não esteja no ar.
      </p>
      <Link href="/" className="font-semibold text-dl-copper underline-offset-4 hover:underline">
        Ir para o Dole Leilão
      </Link>
    </main>
  );
}
