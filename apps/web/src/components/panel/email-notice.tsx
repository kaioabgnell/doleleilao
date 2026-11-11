"use client";

import { useActionState } from "react";
import { resendVerification, type PanelFormState } from "@/app/painel/actions";
import { SubmitButton } from "@/components/ui/button";

/** Aviso de e-mail não confirmado (RF-A04), com reenvio do link. */
export function EmailNotice({ email }: { email: string }) {
  const [state, action] = useActionState<PanelFormState>(resendVerification, {});

  return (
    <section className="flex flex-col gap-3 rounded-card border border-dl-line bg-dl-card p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="text-sm">
        <p className="font-semibold text-dl-ink">Confirme seu e-mail para a loja entrar no ar</p>
        <p className="mt-0.5 text-dl-ink-muted" aria-live="polite">
          {state.success ?? state.message ?? `Mandamos um link para ${email}.`}
        </p>
      </div>
      <form action={action} className="shrink-0">
        <SubmitButton variant="secondary" pendingLabel="Enviando…">
          Reenviar link
        </SubmitButton>
      </form>
    </section>
  );
}
