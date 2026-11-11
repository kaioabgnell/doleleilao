"use client";

import { useActionState } from "react";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import { forgotPassword, type FormState } from "../actions";

export function ForgotForm() {
  const [state, action] = useActionState<FormState, FormData>(forgotPassword, {});
  const error = state.errors?.email?.[0];

  if (state.success) {
    return (
      <Alert tone="success" title="Confira seu e-mail.">
        {state.success} O link vale por 60 minutos.
      </Alert>
    );
  }

  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      {state.message && !error ? <Alert tone="error" title={state.message} /> : null}
      <Field id="email" label="E-mail" error={error}>
        <input
          id="email"
          name="email"
          type="email"
          defaultValue={state.values?.email}
          autoComplete="email"
          inputMode="email"
          required
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy("email", error)}
          className={inputClass}
        />
      </Field>
      <SubmitButton pendingLabel="Enviando…">Enviar link</SubmitButton>
    </form>
  );
}
