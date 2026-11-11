"use client";

import { useActionState, useState } from "react";
import {
  allowedColorsFor,
  backgrounds,
  storeColors,
  storeFonts,
  templates,
  type BackgroundKey,
  type StoreColorKey,
  type StoreFontKey,
  type TemplateKey,
} from "@doleleilao/design-tokens";
import { AddressDetails, PostalCodeField, useAddressForm } from "@/components/forms/address-fields";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { SocialKey, Storefront } from "@/lib/api-types";
import { maskPhone } from "@/lib/format";
import { saveCompanyData, type PanelFormState } from "../actions";
import { LogoUploader } from "./logo-uploader";
import { StorePreview } from "./store-preview";
import { changeBackground, switchTemplate, type ThemeChoice } from "./theme";

const templateHints: Record<TemplateKey, string> = {
  watches: "Vitrine de joalheria: uma peça em destaque, muito respiro e fundo escuro opcional.",
  fashion: "Revista de moda: fotos grandes na vertical e a peça principal em destaque.",
  electronics: "Loja técnica: busca no topo, grade compacta e ficha técnica em tabela.",
};

const socialLabels: Record<SocialKey, string> = {
  instagram: "Instagram",
  facebook: "Facebook",
  tiktok: "TikTok",
  youtube: "YouTube",
  website: "Site",
};

function initialTheme(s: Storefront): ThemeChoice | null {
  if (!s.template) return null;
  return {
    template: s.template,
    primary: s.primary_color as StoreColorKey,
    accent: s.accent_color as StoreColorKey,
    background: s.background as BackgroundKey,
    headingFont: s.heading_font as StoreFontKey,
  };
}

export function CompanyForm({ storefront }: { storefront: Storefront }) {
  const [state, action] = useActionState<PanelFormState, FormData>(saveCompanyData, {});
  const err = (field: string) => state.errors?.[field]?.[0];
  const [theme, setTheme] = useState<ThemeChoice | null>(() => initialTheme(storefront));
  const [phone, setPhone] = useState(storefront.contact_phone ?? "");
  const address = useAddressForm(storefront.address);
  const allowed = theme ? allowedColorsFor(theme.background) : [];

  return (
    <form action={action} noValidate className="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
      <div className="flex flex-col gap-6">
        {state.message ? <Alert tone="error" title={state.message} /> : null}

        <Section title="Dados da empresa" description="WhatsApp, e-mail e redes sociais aparecem no “fale com o leiloeiro” da loja.">
          {/* WhatsApp, e-mail e CEP numa linha quando cabe (a seção é um container). */}
          <div className="grid gap-4 @md:grid-cols-2 @lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_10rem]">
            <Field id="contact_phone" label="WhatsApp" error={err("contact_phone")} hint="Com DDD.">
              <input
                id="contact_phone"
                name="contact_phone"
                type="tel"
                value={phone}
                onChange={(e) => setPhone(maskPhone(e.target.value))}
                inputMode="tel"
                autoComplete="tel-national"
                placeholder="(11) 98765-4321"
                aria-invalid={err("contact_phone") ? true : undefined}
                aria-describedby={describedBy("contact_phone", err("contact_phone"), true)}
                className={inputClass}
              />
            </Field>
            <Field id="contact_email" label="E-mail de contato" error={err("contact_email")}>
              <input
                id="contact_email"
                name="contact_email"
                type="email"
                defaultValue={storefront.contact_email ?? ""}
                inputMode="email"
                autoComplete="email"
                aria-invalid={err("contact_email") ? true : undefined}
                aria-describedby={describedBy("contact_email", err("contact_email"))}
                className={inputClass}
              />
            </Field>
            <PostalCodeField
              form={address}
              skin="panel"
              label="CEP de envio (opcional)"
              hint="Não aparece na loja."
              error={err}
            />
          </div>
          <AddressDetails form={address} skin="panel" error={err} />
          <details className="group rounded-field border border-dl-line" open={Object.keys(storefront.socials).length > 0}>
            <summary className="flex min-h-11 cursor-pointer items-center px-3 text-sm font-medium text-dl-ink">
              Redes sociais <span className="ml-1 font-normal text-dl-ink-muted">(opcional)</span>
            </summary>
            <div className="flex flex-col gap-4 border-t border-dl-line p-3">
              {(Object.keys(socialLabels) as SocialKey[]).map((key) => {
                const id = `socials.${key}`;
                return (
                  <Field key={key} id={id} label={socialLabels[key]} error={err(id)}>
                    <input
                      id={id}
                      name={id}
                      type="url"
                      inputMode="url"
                      defaultValue={storefront.socials[key] ?? ""}
                      placeholder="https://"
                      aria-invalid={err(id) ? true : undefined}
                      aria-describedby={describedBy(id, err(id))}
                      className={inputClass}
                    />
                  </Field>
                );
              })}
            </div>
          </details>
        </Section>

        <Section title="Logo" description="Aparece no topo da loja. Opcional.">
          <LogoUploader logoUrl={storefront.logo.url} displayName={storefront.display_name} />
        </Section>

        <Section title="Modelo da loja" description="Dá para trocar depois sem perder produtos, banners e categorias.">
          <Choices
            name="template"
            legend="Modelo da loja"
            error={err("template")}
            value={theme?.template ?? ""}
            onChange={(t) => setTheme((current) => switchTemplate(current, t as TemplateKey))}
            options={(Object.keys(templates) as TemplateKey[]).map((key) => ({
              value: key,
              label: templates[key].name,
              hint: templateHints[key],
            }))}
          />
        </Section>

        {/* No celular, a pré-visualização fica logo depois do modelo, perto das escolhas de cor. */}
        <div className="lg:hidden">
          <StorePreview theme={theme} displayName={storefront.display_name} logoUrl={storefront.logo.url} />
        </div>

        {theme ? (
          <Section title="Aparência" description="As cores riscadas não têm contraste suficiente com o fundo escolhido.">
            <Choices
              name="background"
              legend="Fundo"
              error={err("background")}
              value={theme.background}
              onChange={(b) => setTheme(changeBackground(theme, b as BackgroundKey))}
              columns={3}
              options={templates[theme.template].backgrounds.map((key) => ({
                value: key,
                label: backgrounds[key].name,
                swatch: backgrounds[key].hex,
              }))}
            />
            <ColorPicker
              name="primary_color"
              legend="Cor primária"
              hint="Botões e links."
              value={theme.primary}
              allowed={allowed}
              error={err("primary_color")}
              onChange={(c) => setTheme({ ...theme, primary: c })}
            />
            <ColorPicker
              name="accent_color"
              legend="Cor de destaque"
              hint="Selos e detalhes."
              value={theme.accent}
              allowed={allowed}
              error={err("accent_color")}
              onChange={(c) => setTheme({ ...theme, accent: c })}
            />
            <Choices
              name="heading_font"
              legend="Fonte dos títulos"
              error={err("heading_font")}
              value={theme.headingFont}
              onChange={(f) => setTheme({ ...theme, headingFont: f as StoreFontKey })}
              options={(Object.keys(storeFonts) as StoreFontKey[]).map((key) => ({
                value: key,
                label: storeFonts[key].name,
                fontVar: storeFonts[key].cssVar,
              }))}
            />
          </Section>
        ) : null}
      </div>

      <aside className="flex flex-col gap-4 lg:sticky lg:top-32">
        <div className="hidden flex-col gap-4 lg:flex">
          <p className="text-sm font-medium text-dl-ink">Como sua loja fica</p>
          <StorePreview theme={theme} displayName={storefront.display_name} logoUrl={storefront.logo.url} />
        </div>
        {state.success ? <Alert tone="success" title={state.success} /> : null}
        <SubmitButton pendingLabel="Salvando…">Salvar</SubmitButton>
      </aside>
    </form>
  );
}

function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return (
    <section className="@container flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
      <div>
        <h2 className="font-brand text-lg font-bold text-dl-ink">{title}</h2>
        <p className="mt-0.5 text-sm text-dl-ink-muted">{description}</p>
      </div>
      {children}
    </section>
  );
}

interface Option {
  value: string;
  label: string;
  hint?: string;
  swatch?: string;
  fontVar?: string;
}

function Choices({
  name,
  legend,
  options,
  value,
  onChange,
  error,
  columns = 1,
}: {
  name: string;
  legend: string;
  options: Option[];
  value: string;
  onChange: (value: string) => void;
  error?: string;
  columns?: 1 | 3;
}) {
  return (
    <fieldset aria-describedby={error ? `${name}-error` : undefined}>
      <legend className="mb-2 text-sm font-medium text-dl-ink">{legend}</legend>
      <div className={`grid gap-2 ${columns === 3 ? "grid-cols-3" : "sm:grid-cols-2"}`}>
        {options.map((option) => (
          <label
            key={option.value}
            className="flex min-h-11 cursor-pointer items-center gap-3 rounded-field border border-dl-line p-3 has-checked:border-dl-copper has-checked:bg-dl-page has-focus-visible:outline-2 has-focus-visible:outline-dl-copper"
          >
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={value === option.value}
              onChange={() => onChange(option.value)}
              className="sr-only"
            />
            {option.swatch ? (
              <span aria-hidden className="size-6 shrink-0 rounded-full border border-dl-line" style={{ background: option.swatch }} />
            ) : null}
            <span className="flex min-w-0 flex-col">
              <span
                className="text-sm font-semibold text-dl-ink"
                style={option.fontVar ? { fontFamily: `var(${option.fontVar})` } : undefined}
              >
                {option.label}
              </span>
              {option.hint ? <span className="text-xs text-dl-ink-muted">{option.hint}</span> : null}
            </span>
          </label>
        ))}
      </div>
      {error ? (
        <p id={`${name}-error`} role="alert" className="mt-1.5 text-sm font-medium text-state-outbid">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}

function ColorPicker({
  name,
  legend,
  hint,
  value,
  allowed,
  onChange,
  error,
}: {
  name: string;
  legend: string;
  hint: string;
  value: StoreColorKey;
  allowed: StoreColorKey[];
  onChange: (color: StoreColorKey) => void;
  error?: string;
}) {
  return (
    <fieldset aria-describedby={error ? `${name}-error` : `${name}-hint`}>
      <legend className="text-sm font-medium text-dl-ink">
        {legend} <span className="font-normal text-dl-ink-muted">· {storeColors[value].name}</span>
      </legend>
      <p id={`${name}-hint`} className="mb-2 text-xs text-dl-ink-muted">
        {hint}
      </p>
      <div className="flex flex-wrap gap-2">
        {(Object.keys(storeColors) as StoreColorKey[]).map((key) => {
          const enabled = allowed.includes(key);
          return (
            <label
              key={key}
              title={enabled ? storeColors[key].name : `${storeColors[key].name}: sem contraste com este fundo`}
              className={`relative flex size-11 items-center justify-center rounded-full border-2 has-checked:border-dl-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-dl-copper ${
                enabled ? "cursor-pointer border-transparent" : "cursor-not-allowed border-transparent opacity-40"
              }`}
            >
              <input
                type="radio"
                name={name}
                value={key}
                checked={value === key}
                disabled={!enabled}
                onChange={() => onChange(key)}
                className="sr-only"
                aria-label={enabled ? storeColors[key].name : `${storeColors[key].name} (sem contraste com este fundo)`}
              />
              <span aria-hidden className="size-8 rounded-full" style={{ background: storeColors[key].hex }} />
              {!enabled ? (
                <span aria-hidden className="absolute h-0.5 w-10 rotate-45 rounded-full bg-dl-ink" />
              ) : null}
            </label>
          );
        })}
      </div>
      {error ? (
        <p id={`${name}-error`} role="alert" className="mt-1.5 text-sm font-medium text-state-outbid">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}
