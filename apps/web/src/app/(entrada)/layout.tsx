import Image from "next/image";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { LiveShowcase } from "@/components/login/live-showcase";
import { entradaFontVariables } from "@/lib/fonts-entrada";

/**
 * Tela de entrada do leiloeiro (D-53): formulário à esquerda e, à direita, o palco com o pregão
 * funcionando. No celular o palco vem depois do formulário.
 */
export default function EntradaLayout({ children }: LayoutProps<"/">) {
  return (
    <div
      className={`${entradaFontVariables} flex flex-1 flex-col bg-dl-page font-display text-dl-ink lg:grid lg:min-h-dvh lg:grid-cols-[minmax(440px,5fr)_7fr]`}
    >
      <div className="flex flex-col px-5 pt-5 pb-6 sm:px-10 sm:pt-8 lg:min-h-dvh lg:px-12 xl:px-14">
        <header className="flex items-center justify-between gap-4">
          <Link href="/" aria-label="Dole Leilão, página inicial" className="-m-1 rounded-field p-1 focus-visible:outline-2 focus-visible:outline-dl-copper">
            <Image src="/brand/logo-horizontal.svg" alt="Dole Leilão" width={147} height={56} priority />
          </Link>
          <Link
            href="/"
            className="group inline-flex min-h-11 items-center gap-1.5 rounded-field px-1 text-sm font-medium text-dl-ink-muted transition-colors duration-150 hover:text-dl-ink focus-visible:outline-2 focus-visible:outline-dl-copper"
          >
            <ArrowLeft aria-hidden className="size-4 transition-transform duration-150 ease-ui group-hover:-translate-x-[3px]" />
            Voltar ao site
          </Link>
        </header>

        <main id="conteudo" className="flex flex-1 flex-col justify-center py-10 sm:py-14 lg:py-10">
          <div className="mx-auto w-full max-w-[400px]">{children}</div>
        </main>

        <footer className="flex flex-wrap items-center justify-center gap-x-4 text-[13px] text-dl-ink-muted lg:justify-start">
          <span>© 2026 Dole Leilão</span>
          <Link href="/privacidade" className="inline-flex min-h-11 items-center underline-offset-4 hover:text-dl-ink hover:underline">
            Privacidade
          </Link>
          <a href="/credits.html" className="inline-flex min-h-11 items-center underline-offset-4 hover:text-dl-ink hover:underline">
            Créditos das fotos
          </a>
        </footer>
      </div>

      <LiveShowcase />
    </div>
  );
}
