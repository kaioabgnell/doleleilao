"use client";

import Link from "next/link";
import { useActionState } from "react";
import { PasswordInput } from "@/components/forms/password-input";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import { resetPassword, type FormState } from "../actions";

export function ResetForm({ token, email }: { token: string; email: string }) {
  const [state, action] = useActionState<FormState, FormData>(resetPassword, {});

  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      {state.message ? (
        <Alert tone="error" title={state.message}>
          <Link href="/esqueci-senha" className="font-semibold text-dl-copper underline">
            Pedir um novo link
          </Link>
        </Alert>
      ) : null}
      <p className="text-sm text-dl-ink-muted">
        Conta: <span className="font-medium text-dl-ink">{email}</span>
      </p>
      <input type="hidden" name="token" value={token} />
      <input type="hidden" name="email" value={email} />
      <PasswordInput
        label="Nova senha"
        autoComplete="new-password"
        error={state.errors?.password?.[0]}
        hint="Pelo menos 8 caracteres, com letras e números."
      />
      <SubmitButton pendingLabel="Salvando…">Salvar nova senha</SubmitButton>
    </form>
  );
}
