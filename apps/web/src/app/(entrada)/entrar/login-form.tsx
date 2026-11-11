"use client";

import Link from "next/link";
import { useActionState } from "react";
import { ArrowRight } from "lucide-react";
import { login, type FormState } from "@/app/(acesso)/actions";
import { PasswordInput } from "@/components/forms/password-input";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import { describedBy, Field, inputClass } from "@/components/ui/field";

/** Passo do botão: sobe 1 px no hover e afunda ao clicar (só nesta tela, D-53). */
const submitMotion =
  "group mt-1 min-h-12 w-full transition-[color,background-color,translate,scale]! duration-150! ease-ui! hover:-translate-y-px active:translate-y-px active:scale-[.985] disabled:translate-y-0";

export function LoginForm({ voltar }: { voltar: string }) {
  const [state, action] = useActionState<FormState, FormData>(login, {});
  const err = (field: string) => state.errors?.[field]?.[0];

  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      {state.message ? <Alert tone="error" title={state.message} /> : null}
      <input type="hidden" name="voltar" value={voltar} />
      <Field id="email" label="E-mail" error={err("email")}>
        <input
          id="email"
          name="email"
          type="email"
          defaultValue={state.values?.email}
          autoComplete="email"
          inputMode="email"
          required
          aria-invalid={err("email") ? true : undefined}
          aria-describedby={describedBy("email", err("email"))}
          className={inputClass}
        />
      </Field>
      <PasswordInput autoComplete="current-password" error={err("password")} />
      <Link
        href="/esqueci-senha"
        className="-mt-1 self-start rounded-field text-sm font-medium text-dl-copper underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-dl-copper"
      >
        Esqueci minha senha
      </Link>
      <SubmitButton pendingLabel="Entrando…" className={submitMotion}>
        Entrar no painel
        <ArrowRight aria-hidden className="size-4 transition-transform duration-150 ease-ui group-hover:translate-x-[3px]" />
      </SubmitButton>
    </form>
  );
}
