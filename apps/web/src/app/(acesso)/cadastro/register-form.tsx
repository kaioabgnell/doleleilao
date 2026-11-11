"use client";

import { useActionState, useState } from "react";
import { SlugInput } from "@/components/forms/slug-input";
import { PasswordInput } from "@/components/forms/password-input";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { Suggestion } from "@/lib/api-types";
import { maskDocument, maskPhone } from "@/lib/format";
import { register, type FormState } from "../actions";

const DISPLAY_NAME_MAX = 24;

export function RegisterForm() {
  const [state, action] = useActionState<FormState, FormData>(register, {});
  const v = state.values ?? {};
  const err = (field: string) => state.errors?.[field]?.[0];

  const [documentType, setDocumentType] = useState<"cpf" | "cnpj">((v.document_type as "cpf" | "cnpj") || "cnpj");
  const [document, setDocument] = useState(v.document ?? "");
  const [phone, setPhone] = useState(v.phone ?? "");
  const [company, setCompany] = useState(v.company_name ?? "");
  const [slug, setSlug] = useState(v.slug ?? "");
  const [displayName, setDisplayName] = useState(v.display_name ?? "");
  const [slugTouched, setSlugTouched] = useState(Boolean(v.slug));
  const [displayTouched, setDisplayTouched] = useState(Boolean(v.display_name));

  /** Ao sair do nome da empresa, sugere endereço e nome de exibição (se a pessoa ainda não mexeu neles). */
  async function suggest() {
    if (company.trim().length < 2 || (slugTouched && displayTouched)) return;
    try {
      const res = await fetch(`/api/loja/sugestao?empresa=${encodeURIComponent(company)}`);
      if (!res.ok) return;
      const s = (await res.json()) as Suggestion;
      if (!slugTouched && s.slug) setSlug(s.slug);
      if (!displayTouched && s.display_name) setDisplayName(s.display_name);
    } catch {
      // sem sugestão: a pessoa preenche à mão
    }
  }

  return (
    <form action={action} noValidate className="flex flex-col gap-6">
      {state.message ? <Alert tone="error" title={state.message} /> : null}

      <fieldset className="flex flex-col gap-4">
        <legend className="mb-3 text-xs font-semibold tracking-wide text-dl-ink-muted uppercase">Sobre você</legend>
        <Field id="name" label="Seu nome" error={err("name")}>
          <input
            id="name"
            name="name"
            defaultValue={v.name}
            autoComplete="name"
            required
            aria-invalid={err("name") ? true : undefined}
            aria-describedby={describedBy("name", err("name"))}
            className={inputClass}
          />
        </Field>
        <Field id="email" label="E-mail" error={err("email")} hint="Vamos mandar um link para confirmar.">
          <input
            id="email"
            name="email"
            type="email"
            defaultValue={v.email}
            autoComplete="email"
            inputMode="email"
            required
            aria-invalid={err("email") ? true : undefined}
            aria-describedby={describedBy("email", err("email"), true)}
            className={inputClass}
          />
        </Field>
        <Field id="phone" label="WhatsApp" error={err("phone")} hint="Com DDD. Vira o contato da sua loja (dá para mudar depois).">
          <input
            id="phone"
            name="phone"
            type="tel"
            value={phone}
            onChange={(e) => setPhone(maskPhone(e.target.value))}
            autoComplete="tel-national"
            inputMode="tel"
            placeholder="(11) 98765-4321"
            required
            aria-invalid={err("phone") ? true : undefined}
            aria-describedby={describedBy("phone", err("phone"), true)}
            className={inputClass}
          />
        </Field>
        <PasswordInput autoComplete="new-password" error={err("password")} hint="Pelo menos 8 caracteres, com letras e números." />
      </fieldset>

      <fieldset className="flex flex-col gap-4">
        <legend className="mb-3 text-xs font-semibold tracking-wide text-dl-ink-muted uppercase">Sua empresa</legend>
        <div className="flex flex-col gap-1.5">
          <span id="document_type-label" className="text-sm font-medium text-dl-ink">
            Você vende como
          </span>
          <div role="radiogroup" aria-labelledby="document_type-label" className="grid grid-cols-2 gap-2">
            {(
              [
                ["cnpj", "Empresa (CNPJ)"],
                ["cpf", "Pessoa física (CPF)"],
              ] as const
            ).map(([value, label]) => (
              <label
                key={value}
                className="flex min-h-11 cursor-pointer items-center justify-center rounded-field border border-dl-line px-3 text-center text-sm font-medium text-dl-ink has-checked:border-dl-copper has-checked:bg-dl-page has-checked:text-dl-copper has-focus-visible:outline-2 has-focus-visible:outline-dl-copper"
              >
                <input
                  type="radio"
                  name="document_type"
                  value={value}
                  checked={documentType === value}
                  onChange={() => {
                    setDocumentType(value);
                    setDocument("");
                  }}
                  className="sr-only"
                />
                {label}
              </label>
            ))}
          </div>
        </div>
        <Field id="document" label={documentType === "cpf" ? "CPF" : "CNPJ"} error={err("document")}>
          <input
            id="document"
            name="document"
            value={document}
            onChange={(e) => setDocument(maskDocument(documentType, e.target.value))}
            inputMode={documentType === "cpf" ? "numeric" : "text"}
            autoCapitalize="characters"
            autoComplete="off"
            placeholder={documentType === "cpf" ? "000.000.000-00" : "00.000.000/0000-00"}
            required
            aria-invalid={err("document") ? true : undefined}
            aria-describedby={describedBy("document", err("document"))}
            className={inputClass}
          />
        </Field>
        <Field id="company_name" label="Nome da empresa de leilão" error={err("company_name")}>
          <input
            id="company_name"
            name="company_name"
            value={company}
            onChange={(e) => setCompany(e.target.value)}
            onBlur={suggest}
            autoComplete="organization"
            required
            aria-invalid={err("company_name") ? true : undefined}
            aria-describedby={describedBy("company_name", err("company_name"))}
            className={inputClass}
          />
        </Field>
      </fieldset>

      <fieldset className="flex flex-col gap-4">
        <legend className="mb-3 text-xs font-semibold tracking-wide text-dl-ink-muted uppercase">Sua loja</legend>
        <SlugInput
          value={slug}
          onChange={(s) => {
            setSlug(s);
            setSlugTouched(true);
          }}
          error={err("slug")}
        />
        <Field
          id="display_name"
          label="Nome de exibição"
          error={err("display_name")}
          hint={`Aparece no lugar da logo enquanto você não enviar uma. Até 2 palavras (${displayName.length}/${DISPLAY_NAME_MAX}).`}
        >
          <input
            id="display_name"
            name="display_name"
            value={displayName}
            onChange={(e) => {
              setDisplayName(e.target.value);
              setDisplayTouched(true);
            }}
            maxLength={DISPLAY_NAME_MAX}
            autoComplete="off"
            aria-invalid={err("display_name") ? true : undefined}
            aria-describedby={describedBy("display_name", err("display_name"), true)}
            className={inputClass}
          />
        </Field>
      </fieldset>

      <div className="flex flex-col gap-1.5">
        <label className="flex min-h-11 cursor-pointer items-start gap-3 text-sm text-dl-ink">
          <input
            type="checkbox"
            name="accept_terms"
            defaultChecked={v.accept_terms === "true"}
            required
            aria-invalid={err("accept_terms") ? true : undefined}
            aria-describedby={err("accept_terms") ? "accept_terms-error" : undefined}
            className="mt-0.5 size-5 shrink-0 accent-dl-copper"
          />
          <span>
            Li e aceito os <span className="font-semibold">termos de uso</span> e a{" "}
            <span className="font-semibold">política de privacidade</span>.
          </span>
        </label>
        {err("accept_terms") ? (
          <p id="accept_terms-error" role="alert" className="text-sm font-medium text-state-outbid">
            {err("accept_terms")}
          </p>
        ) : null}
      </div>

      <SubmitButton pendingLabel="Criando sua loja…">Criar minha loja</SubmitButton>
    </form>
  );
}
