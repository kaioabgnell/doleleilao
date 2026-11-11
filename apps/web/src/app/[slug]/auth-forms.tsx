"use client";

import Link from "next/link";
import { useActionState, useState } from "react";
import { aria, StoreField, StoreNotice, StoreSubmit, storeInput } from "@/components/store/store-ui";
import { maskCpf, maskPhone } from "@/lib/format";
import {
  forgotBidderPassword,
  loginBidder,
  registerBidder,
  resetBidderPassword,
  type StoreFormState,
} from "./acoes";

function Password({ error, hint, autoComplete }: { error?: string; hint?: string; autoComplete: "current-password" | "new-password" }) {
  const [visible, setVisible] = useState(false);
  return (
    <StoreField id="password" label="Senha" error={error} hint={hint}>
      <div className="relative">
        <input id="password" name="password" type={visible ? "text" : "password"} autoComplete={autoComplete} required {...aria("password", error, Boolean(hint))} className={`${storeInput} pr-20`} />
        <button type="button" onClick={() => setVisible((v) => !v)} aria-pressed={visible} className="absolute inset-y-0 right-0 min-w-16 px-3 text-sm font-medium text-store-link">
          {visible ? "Ocultar" : "Mostrar"}
        </button>
      </div>
    </StoreField>
  );
}

function Hidden({ slug, voltar, favoritar }: { slug: string; voltar?: string; favoritar?: string }) {
  return (
    <>
      <input type="hidden" name="slug" value={slug} />
      {voltar ? <input type="hidden" name="voltar" value={voltar} /> : null}
      {favoritar ? <input type="hidden" name="favoritar" value={favoritar} /> : null}
    </>
  );
}

/** Cadastro do participante na loja (RF-E03): nome, CPF (obrigatório), e-mail, telefone e senha. */
export function BidderRegisterForm({ slug, storeName, voltar, favoritar }: { slug: string; storeName: string; voltar?: string; favoritar?: string }) {
  const [state, action] = useActionState<StoreFormState, FormData>(registerBidder, {});
  const v = state.values ?? {};
  const err = (f: string) => state.errors?.[f]?.[0];
  const [phone, setPhone] = useState(v.phone ?? "");
  const [cpf, setCpf] = useState(v.cpf ?? "");

  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      <Hidden slug={slug} voltar={voltar} favoritar={favoritar} />
      {state.message ? <StoreNotice tone="error">{state.message}</StoreNotice> : null}
      <StoreField id="name" label="Nome completo" error={err("name")}>
        <input id="name" name="name" defaultValue={v.name} autoComplete="name" required {...aria("name", err("name"))} className={storeInput} />
      </StoreField>
      <StoreField id="cpf" label="CPF" error={err("cpf")} hint="Obrigatório para dar lances. Fica protegido e nunca aparece completo.">
        <input id="cpf" name="cpf" value={cpf} onChange={(e) => setCpf(maskCpf(e.target.value))} inputMode="numeric" placeholder="000.000.000-00" required {...aria("cpf", err("cpf"), true)} className={storeInput} />
      </StoreField>
      <StoreField id="email" label="E-mail" error={err("email")} hint="Vamos mandar um link para confirmar.">
        <input id="email" name="email" type="email" defaultValue={v.email} autoComplete="email" inputMode="email" required {...aria("email", err("email"), true)} className={storeInput} />
      </StoreField>
      <StoreField id="phone" label="Celular (WhatsApp)" error={err("phone")}>
        <input id="phone" name="phone" type="tel" value={phone} onChange={(e) => setPhone(maskPhone(e.target.value))} autoComplete="tel-national" inputMode="tel" placeholder="(11) 98765-4321" required {...aria("phone", err("phone"))} className={storeInput} />
      </StoreField>
      <Password autoComplete="new-password" error={err("password")} hint="Pelo menos 8 caracteres, com letras e números." />
      <label className="flex min-h-11 items-start gap-3 text-sm">
        <input type="checkbox" name="accept_terms" required {...aria("accept_terms", err("accept_terms"))} className="mt-0.5 size-5 shrink-0 accent-store-primary" />
        <span>
          Aceito os{" "}
          <a href={`/${slug}/termos`} target="_blank" rel="noopener" className="font-semibold text-store-link underline underline-offset-4">
            termos de uso<span className="sr-only"> (abre em nova aba)</span>
          </a>{" "}
          da {storeName} e a{" "}
          <a href="/privacidade" target="_blank" rel="noopener" className="font-semibold text-store-link underline underline-offset-4">
            política de privacidade<span className="sr-only"> (abre em nova aba)</span>
          </a>
          .
        </span>
      </label>
      {err("accept_terms") ? <p id="accept_terms-error" role="alert" className="-mt-2 text-sm font-medium text-state-outbid">{err("accept_terms")}</p> : null}
      <StoreSubmit pending="Criando sua conta…">Criar minha conta</StoreSubmit>
      <p className="text-center text-sm text-store-text-muted">
        Já tem conta?{" "}
        <Link href={`/${slug}/entrar${voltar ? `?voltar=${encodeURIComponent(voltar)}` : ""}`} className="font-semibold text-store-link underline-offset-4 hover:underline">Entrar</Link>
      </p>
    </form>
  );
}

export function BidderLoginForm({ slug, voltar, favoritar }: { slug: string; voltar?: string; favoritar?: string }) {
  const [state, action] = useActionState<StoreFormState, FormData>(loginBidder, {});
  const err = (f: string) => state.errors?.[f]?.[0];
  const signupQuery = new URLSearchParams({ ...(voltar && { voltar }), ...(favoritar && { favoritar }) }).toString();
  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      <Hidden slug={slug} voltar={voltar} favoritar={favoritar} />
      {state.message ? <StoreNotice tone="error">{state.message}</StoreNotice> : null}
      <StoreField id="email" label="E-mail" error={err("email")}>
        <input id="email" name="email" type="email" defaultValue={state.values?.email} autoComplete="email" inputMode="email" required {...aria("email", err("email"))} className={storeInput} />
      </StoreField>
      <Password autoComplete="current-password" error={err("password")} />
      <Link href={`/${slug}/esqueci-senha`} className="-mt-1 self-start text-sm font-medium text-store-link underline-offset-4 hover:underline">Esqueci minha senha</Link>
      <StoreSubmit pending="Entrando…">Entrar</StoreSubmit>
      <p className="text-center text-sm text-store-text-muted">
        Primeira vez aqui?{" "}
        <Link href={`/${slug}/cadastro${signupQuery ? `?${signupQuery}` : ""}`} className="font-semibold text-store-link underline-offset-4 hover:underline">Crie sua conta</Link>
      </p>
    </form>
  );
}

export function BidderForgotForm({ slug }: { slug: string }) {
  const [state, action] = useActionState<StoreFormState, FormData>(forgotBidderPassword, {});
  if (state.success) return <StoreNotice tone="success">{state.success} O link vale por 60 minutos.</StoreNotice>;
  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      <Hidden slug={slug} />
      <StoreField id="email" label="E-mail" error={state.errors?.email?.[0]}>
        <input id="email" name="email" type="email" defaultValue={state.values?.email} autoComplete="email" inputMode="email" required className={storeInput} />
      </StoreField>
      <StoreSubmit pending="Enviando…">Enviar link</StoreSubmit>
    </form>
  );
}

export function BidderResetForm({ slug, token, email }: { slug: string; token: string; email: string }) {
  const [state, action] = useActionState<StoreFormState, FormData>(resetBidderPassword, {});
  return (
    <form action={action} noValidate className="flex flex-col gap-4">
      <Hidden slug={slug} />
      <input type="hidden" name="token" value={token} />
      <input type="hidden" name="email" value={email} />
      {state.message ? (
        <StoreNotice tone="error">
          {state.message} <Link href={`/${slug}/esqueci-senha`} className="font-semibold text-store-link underline">Pedir um novo link</Link>
        </StoreNotice>
      ) : null}
      <p className="text-sm text-store-text-muted">Conta: <span className="font-medium text-store-text">{email}</span></p>
      <Password autoComplete="new-password" error={state.errors?.password?.[0]} hint="Pelo menos 8 caracteres, com letras e números." />
      <StoreSubmit pending="Salvando…">Salvar nova senha</StoreSubmit>
    </form>
  );
}
