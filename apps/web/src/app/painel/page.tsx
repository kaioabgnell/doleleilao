import type { Metadata } from "next";
import { ExternalLink } from "lucide-react";
import { Suspense } from "react";
import { Alert } from "@/components/ui/alert";
import { CopyLink } from "@/components/panel/copy-link";
import { EmailNotice } from "@/components/panel/email-notice";
import { OnboardingSection } from "@/components/panel/onboarding-section";
import type { Onboarding, Storefront } from "@/lib/api-types";
import { getMe, panelApi } from "@/lib/auctioneer";

export const metadata: Metadata = { title: "Início" };

const emailNotices = {
  verificado: { tone: "success", title: "E-mail confirmado!", body: "Sua conta está ativa." },
  "link-expirado": { tone: "error", title: "Este link expirou.", body: "Peça um novo no aviso abaixo." },
  "link-invalido": { tone: "error", title: "Este link não é válido.", body: "Peça um novo no aviso abaixo." },
} as const;

export default function DashboardPage(props: PageProps<"/painel">) {
  return (
    <Suspense fallback={<DashboardSkeleton />}>
      <Dashboard searchParams={props.searchParams} />
    </Suspense>
  );
}

async function Dashboard({ searchParams }: Pick<PageProps<"/painel">, "searchParams">) {
  const [me, onboardingResult, storefrontResult, params] = await Promise.all([
    getMe(),
    panelApi<Onboarding>("/auctioneer/onboarding"),
    panelApi<Storefront>("/auctioneer/storefront"),
    searchParams,
  ]);
  const firstName = me.user.name.split(" ")[0];
  const emailParam = typeof params.email === "string" ? params.email : null;
  const notice = emailParam && emailParam in emailNotices ? emailNotices[emailParam as keyof typeof emailNotices] : null;
  const welcome = params["bem-vindo"] === "1";

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">
          {welcome ? `Boas-vindas, ${firstName}!` : `Olá, ${firstName}`}
        </h1>
        <p className="mt-1 text-dl-ink-muted">{me.auctioneer.company_name}</p>
      </div>

      {notice ? (
        <Alert tone={notice.tone} title={notice.title}>
          {notice.body}
        </Alert>
      ) : null}

      {!me.user.email_verified ? <EmailNotice email={me.user.email} /> : null}

      {onboardingResult.ok && onboardingResult.data.visible ? <OnboardingSection onboarding={onboardingResult.data} /> : null}

      {storefrontResult.ok ? <StoreCard storefront={storefrontResult.data} emailVerified={me.user.email_verified} /> : null}
    </div>
  );
}

function StoreCard({ storefront, emailVerified }: { storefront: Storefront; emailVerified: boolean }) {
  const reason = !emailVerified
    ? "Ela entra no ar quando você confirmar o e-mail."
    : !storefront.template
      ? "Ela entra no ar quando você escolher o modelo da loja."
      : null;

  return (
    <section aria-labelledby="store-title" className="rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
      <div className="flex flex-wrap items-center gap-3">
        <h2 id="store-title" className="font-brand text-lg font-bold text-dl-ink">
          Sua loja
        </h2>
        {storefront.is_public ? (
          <span className="flex items-center gap-2 rounded-full bg-state-winning/10 px-2.5 py-0.5 text-xs font-semibold text-state-winning">
            <span aria-hidden className="relative flex size-2">
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-state-winning opacity-75" />
              <span className="relative inline-flex size-2 rounded-full bg-state-winning" />
            </span>
            No ar
          </span>
        ) : (
          <span className="rounded-full bg-dl-page px-2.5 py-0.5 text-xs font-semibold text-dl-ink-muted">
            Ainda não está no ar
          </span>
        )}
        <a
          href={storefront.store_url}
          target="_blank"
          rel="noreferrer"
          aria-label="Abrir a loja em uma nova aba"
          title="Abrir a loja em uma nova aba"
          className="ml-auto flex size-11 items-center justify-center rounded-field border border-dl-line text-dl-ink-muted hover:border-dl-copper hover:text-dl-copper focus-visible:outline-2 focus-visible:outline-dl-copper"
        >
          <ExternalLink aria-hidden className="size-5" />
        </a>
      </div>
      {reason ? <p className="mt-1 text-sm text-dl-ink-muted">{reason}</p> : null}
      <div className="mt-4">
        <CopyLink url={storefront.store_url} />
      </div>
    </section>
  );
}

function DashboardSkeleton() {
  return (
    <div className="flex animate-pulse flex-col gap-5" aria-busy="true" aria-label="Carregando o painel">
      <div className="h-8 w-48 rounded-full bg-dl-line/60" />
      <div className="h-64 rounded-card bg-dl-card" />
      <div className="h-32 rounded-card bg-dl-card" />
    </div>
  );
}
