"use client";

import { dismissOnboarding } from "@/app/painel/actions";
import { SubmitButton } from "@/components/ui/button";

export function DismissOnboardingButton() {
  return (
    <form action={dismissOnboarding}>
      <SubmitButton variant="ghost" pendingLabel="Ocultando…">
        Ocultar
      </SubmitButton>
    </form>
  );
}
