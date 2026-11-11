import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ArrowRight, Gavel } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { LoginForm } from "./login-form";

export const metadata: Metadata = {
  title: "Entrar no painel",
  description: "Entre no painel do leiloeiro do Dole Leilão para cuidar dos lotes, conduzir o pregão ao vivo e ver as arrematações.",
};

const notices: Record<string, { tone: "success" | "info" | "error"; title: string; body?: string }> = {
  alterada: { tone: "success", title: "Senha alterada.", body: "Entre com a nova senha." },
  expirou: { tone: "info", title: "Sua sessão terminou.", body: "Entre de novo para continuar." },
  suspensa: { tone: "error", title: "Esta conta está suspensa.", body: "Fale com o suporte do Dole Leilão." },
  saiu: { tone: "info", title: "Você saiu da conta." },
  verificado: { tone: "success", title: "E-mail confirmado!", body: "Entre para continuar configurando sua loja." },
};

export default function LoginPage(props: PageProps<"/entrar">) {
  return (
    <>
      <p className="animate-rise font-code text-[12px] tracking-[0.14em] text-dl-copper uppercase">Painel do leiloeiro</p>
      {/* O título sobe por trás de uma máscara (entrada da tela, uma vez). */}
      <h1 className="mt-3 overflow-hidden pb-[0.12em] text-[clamp(2.25rem,1.7rem+1.8vw,3rem)] leading-[1.04] font-medium tracking-[-0.04em] text-dl-ink">
        <span className="block animate-line-up">Entrar no painel</span>
      </h1>
      <p className="mt-2 animate-rise text-base leading-relaxed text-dl-ink-muted [animation-delay:360ms]">
        Cuide dos lotes, conduza o pregão ao vivo e veja as arrematações da sua loja.
      </p>

      <div className="mt-8 animate-rise [animation-delay:420ms]">
        <Suspense fallback={<LoginForm voltar="/painel" />}>
          <LoginWithParams searchParams={props.searchParams} />
        </Suspense>
      </div>

      <div className="mt-8 flex animate-rise flex-col gap-3 border-t border-dl-line pt-6 [animation-delay:480ms]">
        <p className="text-sm text-dl-ink-muted">
          Ainda não tem loja?{" "}
          <Link
            href="/cadastro"
            className="group inline-flex items-center gap-1 rounded-field font-semibold text-dl-copper underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-dl-copper"
          >
            Criar loja grátis
            <ArrowRight aria-hidden className="size-3.5 transition-transform duration-150 ease-ui group-hover:translate-x-[3px]" />
          </Link>
        </p>
        <p className="flex gap-2 text-[13px] leading-snug text-dl-ink-muted">
          <Gavel aria-hidden className="mt-px size-4 shrink-0 text-dl-copper-icon" />
          Vai dar lances? Entre pela loja do leiloeiro, no link que ele divulgou.
        </p>
      </div>
    </>
  );
}

async function LoginWithParams({ searchParams }: Pick<PageProps<"/entrar">, "searchParams">) {
  const params = await searchParams;
  const voltar = typeof params.voltar === "string" ? params.voltar : "/painel";
  const key =
    typeof params.senha === "string" && params.senha === "alterada"
      ? "alterada"
      : typeof params.motivo === "string"
        ? params.motivo
        : voltar.includes("email=verificado")
          ? "verificado"
          : null;
  const notice = key ? notices[key] : undefined;

  return (
    <div className="flex flex-col gap-5">
      {notice ? (
        <Alert tone={notice.tone} title={notice.title}>
          {notice.body}
        </Alert>
      ) : null}
      <LoginForm voltar={voltar} />
    </div>
  );
}
