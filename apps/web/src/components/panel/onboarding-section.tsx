import Link from "next/link";
import type { CompanyMissing, Onboarding, OnboardingStep, OnboardingStepKey } from "@/lib/api-types";
import { buttonClass } from "@/components/ui/button-styles";
import { CopyLink } from "./copy-link";
import { DismissOnboardingButton } from "./dismiss-onboarding-button";

/** Textos dos primeiros passos (D-42, D-55, RF-A10 a RF-A13). A API manda só o estado de cada um. */
const copy: Record<OnboardingStepKey, { title: string; description: string; cta: string; href: string }> = {
  company: {
    title: "Complete os dados da sua empresa",
    description: "Contato e modelo da loja. Logo, cores e fonte são opcionais.",
    cta: "Completar dados",
    href: "/painel/dados-da-empresa",
  },
  products: {
    title: "Cadastre produtos",
    description: "Fotos, valor inicial e incremento de cada item.",
    cta: "Cadastrar produto",
    href: "/painel/produtos/novo",
  },
  payment: {
    title: "Configure o pagamento",
    description: "Cadastre o cartão de crédito onde a taxa dos itens arrematados é debitada. É obrigatório para agendar leilões; cadastro e uso continuam grátis.",
    cta: "Cadastrar cartão",
    href: "/painel/configuracoes/pagamento",
  },
  auction: {
    title: "Cadastre o leilão",
    description: "Escolha a data, a hora e os produtos do seu primeiro lote.",
    cta: "Criar lote",
    href: "/painel/leiloes/novo",
  },
};

/** "Cadastre um produto e o cartão de crédito primeiro." */
const blockerLabels: Partial<Record<OnboardingStepKey, string>> = {
  products: "um produto",
  payment: "o cartão de crédito",
};

function blockedMessage(blockedBy: OnboardingStepKey[]): string {
  const parts = blockedBy.map((key) => blockerLabels[key]).filter(Boolean);
  return parts.length > 0 ? `Cadastre ${parts.join(" e ")} primeiro.` : "Conclua os passos anteriores primeiro.";
}

const missingLabels: Record<CompanyMissing, string> = {
  email: "Confirme seu e-mail",
  contact_phone: "Informe o WhatsApp de contato",
  template: "Escolha o modelo da loja",
};

export function OnboardingSection({ onboarding }: { onboarding: Onboarding }) {
  if (onboarding.completed) return <Completed onboarding={onboarding} />;

  return (
    <section aria-labelledby="onboarding-title" className="rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="onboarding-title" className="font-brand text-xl font-bold text-dl-ink">
          Comece seu primeiro leilão
        </h2>
        <p className="text-sm font-medium text-dl-ink-muted tabular">
          {onboarding.done_count} de {onboarding.total}
        </p>
      </div>
      <div
        role="progressbar"
        aria-label="Primeiros passos concluídos"
        aria-valuemin={0}
        aria-valuemax={onboarding.total}
        aria-valuenow={onboarding.done_count}
        className="mt-3 h-1.5 overflow-hidden rounded-full bg-dl-page"
      >
        <div
          className="h-full rounded-full bg-dl-copper transition-[width] duration-500"
          style={{ width: `${(onboarding.done_count / onboarding.total) * 100}%` }}
        />
      </div>

      <ol className="mt-5 flex flex-col gap-3">
        {onboarding.steps.map((step, index) => (
          <Step key={step.key} step={step} number={index + 1} isNext={onboarding.next === step.key} />
        ))}
      </ol>
    </section>
  );
}

function Step({ step, number, isNext }: { step: OnboardingStep; number: number; isNext: boolean }) {
  const text = copy[step.key];
  const status = step.done ? "Feito" : !step.available ? "Em breve" : isNext ? "Próximo passo" : "Pendente";

  return (
    <li
      className={`flex gap-4 rounded-card border p-4 ${
        isNext && step.available && !step.done ? "border-dl-copper bg-dl-page" : "border-dl-line"
      }`}
    >
      <span
        aria-label={step.done ? `Passo ${number}, feito` : `Passo ${number}`}
        className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
          step.done ? "bg-state-winning text-white" : "border border-dl-line bg-dl-card text-dl-ink-muted"
        }`}
      >
        {step.done ? "✓" : number}
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <h3 className={`font-semibold ${step.done ? "text-dl-ink-muted line-through decoration-dl-line" : "text-dl-ink"}`}>
            {text.title}
          </h3>
          <span className="text-xs font-medium tracking-wide text-dl-ink-muted uppercase">{status}</span>
        </div>
        {!step.done ? <p className="text-sm text-dl-ink-muted">{text.description}</p> : null}

        {!step.done && step.missing.length > 0 ? (
          <ul className="mt-1 flex flex-col gap-1 text-sm text-dl-ink">
            {step.missing.map((item) => (
              <li key={item} className="flex items-center gap-2">
                <span aria-hidden className="size-1.5 rounded-full bg-dl-copper-icon" />
                {missingLabels[item]}
              </li>
            ))}
          </ul>
        ) : null}

        {!step.done && step.blocked && step.available ? (
          <p className="text-sm text-dl-ink-muted">{blockedMessage(step.blocked_by ?? [])}</p>
        ) : null}

        {!step.done && step.available && !step.blocked ? (
          <Link
            href={text.href}
            className={buttonClass(isNext ? "primary" : "secondary", "mt-2 self-start")}
          >
            {text.cta}
          </Link>
        ) : null}
      </div>
    </li>
  );
}

function Completed({ onboarding }: { onboarding: Onboarding }) {
  return (
    <section aria-labelledby="onboarding-done" className="rounded-card border border-state-winning/30 bg-dl-card p-5 sm:p-6">
      <h2 id="onboarding-done" className="font-brand text-xl font-bold text-dl-ink">
        Tudo pronto! Seu primeiro leilão está agendado
      </h2>
      <p className="mt-1 text-dl-ink-muted">Compartilhe o link da sua loja com seus clientes.</p>
      <div className="mt-4">
        <CopyLink url={onboarding.store_url} />
      </div>
      <div className="mt-4">
        <DismissOnboardingButton />
      </div>
    </section>
  );
}
