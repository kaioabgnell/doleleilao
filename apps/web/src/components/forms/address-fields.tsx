"use client";

import { Lock } from "lucide-react";
import { useEffect, useRef, useState, type ComponentType, type ReactNode } from "react";
import { flushSync } from "react-dom";
import { StoreField, storeInput } from "@/components/store/store-ui";
import { Field, inputClass } from "@/components/ui/field";
import { BRAZIL_STATES } from "@/lib/address";
import type { Address, PostalCodeResult, Problem } from "@/lib/api-types";
import { maskPostalCode, onlyDigits } from "@/lib/format";

type FieldComponent = ComponentType<{ id: string; label: string; hint?: ReactNode; error?: string; children: ReactNode }>;
type Skin = "panel" | "store";

/** Painel usa as cores da marca; a loja, as do tema do leiloeiro. */
const skins: Record<Skin, { Field: FieldComponent; input: string; locked: string; icon: string }> = {
  panel: { Field, input: inputClass, locked: "read-only:cursor-default read-only:bg-dl-page", icon: "text-dl-ink-muted" },
  store: { Field: StoreField, input: storeInput, locked: "read-only:cursor-default read-only:bg-store-bg", icon: "text-store-text-muted" },
};

type Lookup = "idle" | "loading" | "found" | "not-found" | "unavailable";
type Fields = Omit<Address, "postal_code" | "complement"> & { complement: string };
/** Campos que vêm do CEP e ficam bloqueados. */
type AutoKey = "street" | "district" | "city" | "state";
type Locks = Record<AutoKey, boolean>;

const fromAddress = (a: Address | null): Fields => ({
  street: a?.street ?? "",
  number: a?.number ?? "",
  complement: a?.complement ?? "",
  district: a?.district ?? "",
  city: a?.city ?? "",
  state: a?.state ?? "",
});

/** Bloqueia o que veio preenchido; o que o CEP não trouxe (CEP da cidade toda) a pessoa preenche. */
const locksFor = (values: Pick<Fields, AutoKey>): Locks => ({
  street: values.street !== "",
  district: values.district !== "",
  city: values.city !== "",
  state: values.state !== "",
});

const UNLOCKED: Locks = { street: false, district: false, city: false, state: false };

export interface AddressForm {
  postalCode: string;
  fields: Fields;
  locked: Locks;
  lookup: { status: Lookup; detail?: string };
  showDetails: boolean;
  changePostalCode: (value: string) => void;
  setField: (key: keyof Fields, value: string) => void;
}

/**
 * Estado do endereço pelo CEP: ao completar os 8 números, busca em `/api/cep/{cep}`, preenche rua,
 * bairro, cidade e UF (bloqueados) e leva o cursor ao número. Esses campos só mudam trocando o CEP.
 * Se o serviço de CEP estiver fora, libera tudo para preencher à mão. Sem CEP, o endereço é apagado ao salvar.
 */
export function useAddressForm(initial: Address | null): AddressForm {
  const [postalCode, setPostalCode] = useState(initial?.postal_code ?? "");
  const [fields, setFields] = useState<Fields>(() => fromAddress(initial));
  const [locked, setLocked] = useState<Locks>(() => locksFor(fromAddress(initial)));
  const [lookup, setLookup] = useState<{ status: Lookup; detail?: string }>({ status: "idle" });
  const [showDetails, setShowDetails] = useState(initial !== null);
  const lookedUp = useRef(initial ? onlyDigits(initial.postal_code) : "");
  const pending = useRef<AbortController | null>(null);

  useEffect(() => () => pending.current?.abort(), []);

  async function search(digits: string) {
    pending.current?.abort();
    const controller = new AbortController();
    pending.current = controller;
    lookedUp.current = digits;
    setLookup({ status: "loading" });

    try {
      const res = await fetch(`/api/cep/${digits}`, { signal: controller.signal });
      const body = (await res.json().catch(() => null)) as PostalCodeResult | Problem | null;
      if (res.status === 404) {
        setLookup({ status: "not-found" });
        setShowDetails(false);
        return;
      }
      if (!res.ok) {
        setLookup({ status: "unavailable", detail: (body as Problem | null)?.detail });
        setLocked(UNLOCKED);
        setShowDetails(true);
        return;
      }
      const found = body as PostalCodeResult;
      const auto = { street: found.street, district: found.district, city: found.city, state: found.state };
      const stillTyping = document.activeElement?.id === "address.postal_code";
      // Renderiza os campos já preenchidos antes de levar o cursor (só se a pessoa ainda está no CEP).
      flushSync(() => {
        setFields((current) => ({ ...current, ...auto }));
        setLocked(locksFor(auto));
        setLookup({ status: "found" });
        setShowDetails(true);
      });
      if (stillTyping) document.getElementById(found.street ? "address.number" : "address.street")?.focus();
    } catch {
      if (controller.signal.aborted) return;
      setLookup({ status: "unavailable" });
      setLocked(UNLOCKED);
      setShowDetails(true);
    }
  }

  function changePostalCode(value: string) {
    const masked = maskPostalCode(value);
    const digits = onlyDigits(masked);
    setPostalCode(masked);
    if (digits.length === 8) {
      if (digits !== lookedUp.current) void search(digits);
    } else {
      pending.current?.abort();
      lookedUp.current = "";
      setLookup({ status: "idle" });
      setShowDetails(false);
    }
  }

  return {
    postalCode,
    fields,
    locked,
    lookup,
    showDetails,
    changePostalCode,
    setField: (key, value) => setFields((current) => ({ ...current, [key]: value })),
  };
}

interface PartProps {
  form: AddressForm;
  skin: Skin;
  error: (field: string) => string | undefined;
}

function aria(id: string, error?: string) {
  return { id, name: id, "aria-invalid": error ? true : undefined, "aria-describedby": error ? `${id}-error` : undefined } as const;
}

/** Campo do CEP (`address.postal_code`), com o andamento da busca embaixo. */
export function PostalCodeField({ form, skin, error, label, hint }: PartProps & { label: string; hint: string }) {
  const { Field: SkinField, input } = skins[skin];
  const { lookup, fields } = form;
  const complete = onlyDigits(form.postalCode).length === 8;

  const status = !complete
    ? hint
    : lookup.status === "loading"
      ? "Buscando o endereço…"
      : lookup.status === "found"
        ? fields.street
          ? "Endereço preenchido pelo CEP."
          : "CEP geral da cidade: preencha a rua e o bairro."
        : lookup.status === "unavailable"
          ? (lookup.detail ?? "Não conseguimos buscar o CEP agora. Preencha o endereço abaixo.")
          : hint;
  const fieldError = error("address.postal_code") ?? (complete && lookup.status === "not-found" ? "Não achamos este CEP. Confira os números." : undefined);

  return (
    <SkinField id="address.postal_code" label={label} error={fieldError} hint={<span role="status">{status}</span>}>
      <input
        {...aria("address.postal_code", fieldError)}
        value={form.postalCode}
        onChange={(e) => form.changePostalCode(e.target.value)}
        inputMode="numeric"
        autoComplete="postal-code"
        placeholder="00000-000"
        aria-busy={lookup.status === "loading" || undefined}
        aria-describedby={fieldError ? "address.postal_code-error" : "address.postal_code-hint"}
        className={input}
      />
    </SkinField>
  );
}

/**
 * Endereço, número, complemento, bairro, cidade e UF, em duas linhas quando há espaço
 * (o layout segue a largura do formulário, não a da tela). Só aparece com o CEP completo.
 */
export function AddressDetails({ form, skin, error }: PartProps) {
  const { Field: SkinField, input, locked: lockedClass, icon } = skins[skin];
  const { fields, locked } = form;
  if (!form.showDetails) return null;

  const auto = (key: AutoKey, autoComplete: string, maxLength: number) => {
    const id = `address.${key}`;
    const props = { ...aria(id, error(id)), value: fields[key], autoComplete, maxLength };
    if (!locked[key]) {
      return <input {...props} onChange={(e) => form.setField(key, e.target.value)} className={input} />;
    }
    return (
      <div className="relative">
        <input {...props} readOnly tabIndex={-1} title="Preenchido pelo CEP. Para mudar, troque o CEP." className={`${input} ${lockedClass} pr-9`} />
        <Lock aria-hidden className={`pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 ${icon}`} />
      </div>
    );
  };

  return (
    <div className="@container grid grid-cols-12 gap-4">
      <div className="col-span-12 @lg:col-span-6">
        <SkinField id="address.street" label="Endereço" error={error("address.street")}>
          {auto("street", "address-line1", 120)}
        </SkinField>
      </div>
      <div className="col-span-4 @lg:col-span-2">
        <SkinField id="address.number" label="Número" error={error("address.number")}>
          <input
            {...aria("address.number", error("address.number"))}
            value={fields.number}
            onChange={(e) => form.setField("number", e.target.value)}
            maxLength={10}
            className={input}
          />
        </SkinField>
      </div>
      <div className="col-span-8 @lg:col-span-4">
        <SkinField id="address.complement" label="Complemento (opcional)" error={error("address.complement")}>
          <input
            {...aria("address.complement", error("address.complement"))}
            value={fields.complement}
            onChange={(e) => form.setField("complement", e.target.value)}
            autoComplete="address-line2"
            maxLength={60}
            className={input}
          />
        </SkinField>
      </div>
      <div className="col-span-12 @lg:col-span-5">
        <SkinField id="address.district" label="Bairro" error={error("address.district")}>
          {auto("district", "address-level3", 60)}
        </SkinField>
      </div>
      <div className="col-span-8 @lg:col-span-5">
        <SkinField id="address.city" label="Cidade" error={error("address.city")}>
          {auto("city", "address-level2", 60)}
        </SkinField>
      </div>
      <div className="col-span-4 @lg:col-span-2">
        <SkinField id="address.state" label="UF" error={error("address.state")}>
          {locked.state ? (
            auto("state", "address-level1", 2)
          ) : (
            <select
              {...aria("address.state", error("address.state"))}
              value={fields.state}
              onChange={(e) => form.setField("state", e.target.value)}
              autoComplete="address-level1"
              className={input}
            >
              <option value="">–</option>
              {BRAZIL_STATES.map((uf) => (
                <option key={uf} value={uf}>
                  {uf}
                </option>
              ))}
            </select>
          )}
        </SkinField>
      </div>
    </div>
  );
}
