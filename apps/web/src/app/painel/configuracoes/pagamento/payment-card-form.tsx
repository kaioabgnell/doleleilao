"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { CreditCard, Lock, RefreshCw } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { describedBy, Field, inputClass } from "@/components/ui/field";
import type { BillingSettings, FieldErrors, PaymentCard } from "@/lib/api-types";
import { BRAND_LABEL, cvvLength, detectBrand, maskCardNumber, maskExpiry } from "@/lib/credit-card";
import { panelRequest } from "@/lib/panel-client";

/**
 * Cartão do leiloeiro (D-54). Sem gateway por enquanto: a API confere o número e o código de
 * segurança e guarda só a bandeira, os 4 últimos dígitos, o nome e a validade.
 */
export function PaymentCardForm({ card: initial }: { card: PaymentCard | null }) {
  const router = useRouter();
  const [card, setCard] = useState(initial);
  const [editing, setEditing] = useState(initial === null);
  const [number, setNumber] = useState("");
  const [holder, setHolder] = useState("");
  const [expiry, setExpiry] = useState("");
  const [cvv, setCvv] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const brand = detectBrand(number.replace(/\D/g, ""));
  const csc = cvvLength(brand);
  const err = (key: string) => errors[key]?.[0];

  function reset() {
    setNumber("");
    setHolder("");
    setExpiry("");
    setCvv("");
    setErrors({});
  }

  async function onSubmit() {
    setBusy(true);
    setErrors({});
    setMessage(null);
    const result = await panelRequest<BillingSettings>("billing/card", {
      method: "PUT",
      body: { number, holder_name: holder, expiry, cvv },
    });
    setBusy(false);
    if (!result.ok || !result.data) {
      setErrors(result.errors ?? {});
      setMessage({ tone: "error", text: result.message ?? "Não foi possível salvar o cartão." });
      if (result.status === 401) router.refresh();
      return;
    }
    // O número e o CVV saem da tela assim que o cartão é salvo.
    reset();
    setCard(result.data.card);
    setEditing(false);
    setMessage({ tone: "success", text: card ? "Cartão trocado." : "Cartão salvo. Você já pode agendar seus leilões." });
    router.refresh();
  }

  return (
    <section aria-labelledby="card-title" className="flex flex-col gap-5 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
      <div>
        <h2 id="card-title" className="font-brand text-lg font-bold text-dl-ink">
          Cartão de crédito
        </h2>
        <p className="mt-1 text-sm text-dl-ink-muted">Onde a taxa dos itens arrematados é debitada, depois de cada leilão.</p>
      </div>

      {message ? <Alert tone={message.tone} title={message.text} /> : null}

      {card && !editing ? (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
          <SavedCard card={card} />
          <button
            type="button"
            className={buttonClass("secondary", "sm:mb-1")}
            onClick={() => {
              setMessage(null);
              setEditing(true);
            }}
          >
            <RefreshCw aria-hidden className="size-4" />
            Trocar cartão
          </button>
        </div>
      ) : (
        <form
          className="flex flex-col gap-4"
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            void onSubmit();
          }}
        >
          {/* A bandeira fica na linha de ajuda: dentro do campo ela cortava o número no celular. */}
          <Field
            id="card-number"
            label="Número do cartão"
            hint={
              <span aria-live="polite" className="inline-flex items-center gap-1.5">
                <CreditCard aria-hidden className="size-4" />
                {brand ? `Bandeira: ${BRAND_LABEL[brand]}` : "Visa, Mastercard, Elo, American Express, Hipercard ou Diners"}
              </span>
            }
            error={err("number")}
          >
            <input
              id="card-number"
              className={`${inputClass} tabular`}
              inputMode="numeric"
              autoComplete="cc-number"
              placeholder="0000 0000 0000 0000"
              value={number}
              onChange={(e) => setNumber(maskCardNumber(e.target.value))}
              maxLength={23}
              required
              aria-invalid={!!err("number")}
              aria-describedby={describedBy("card-number", err("number"), true)}
            />
          </Field>

          <Field id="card-holder" label="Nome impresso no cartão" error={err("holder_name")}>
            <input
              id="card-holder"
              className={`${inputClass} uppercase`}
              autoComplete="cc-name"
              autoCapitalize="characters"
              spellCheck={false}
              value={holder}
              onChange={(e) => setHolder(e.target.value)}
              maxLength={60}
              required
              aria-invalid={!!err("holder_name")}
              aria-describedby={describedBy("card-holder", err("holder_name"))}
            />
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field id="card-expiry" label="Validade" hint="Mês e ano: MM/AA" error={err("expiry")}>
              <input
                id="card-expiry"
                className={`${inputClass} tabular`}
                inputMode="numeric"
                autoComplete="cc-exp"
                placeholder="MM/AA"
                value={expiry}
                onChange={(e) => setExpiry(maskExpiry(e.target.value))}
                maxLength={5}
                required
                aria-invalid={!!err("expiry")}
                aria-describedby={describedBy("card-expiry", err("expiry"), true)}
              />
            </Field>
            <Field
              id="card-cvv"
              label="Código de segurança (CVV)"
              hint={brand === "amex" ? "4 dígitos, na frente do cartão" : "3 dígitos, no verso do cartão"}
              error={err("cvv")}
            >
              <input
                id="card-cvv"
                className={`${inputClass} tabular`}
                inputMode="numeric"
                autoComplete="cc-csc"
                value={cvv}
                onChange={(e) => setCvv(e.target.value.replace(/\D/g, "").slice(0, csc))}
                maxLength={csc}
                required
                aria-invalid={!!err("cvv")}
                aria-describedby={describedBy("card-cvv", err("cvv"), true)}
              />
            </Field>
          </div>

          <p className="flex items-start gap-2 text-sm text-dl-ink-muted">
            <Lock aria-hidden className="mt-0.5 size-4 shrink-0" />
            Por segurança, o número completo e o código de segurança não ficam guardados: só a bandeira, os 4 últimos dígitos, o nome e a validade.
          </p>

          <div className="flex flex-wrap gap-2">
            <button type="submit" className={buttonClass("primary")} disabled={busy}>
              {busy ? "Salvando…" : card ? "Salvar novo cartão" : "Salvar cartão"}
            </button>
            {card ? (
              <button
                type="button"
                className={buttonClass("ghost")}
                disabled={busy}
                onClick={() => {
                  reset();
                  setMessage(null);
                  setEditing(false);
                }}
              >
                Cancelar
              </button>
            ) : null}
          </div>
        </form>
      )}
    </section>
  );
}

/** O cartão salvo, desenhado como um cartão: bandeira, final, nome e validade. */
function SavedCard({ card }: { card: PaymentCard }) {
  const expiry = `${String(card.exp_month).padStart(2, "0")}/${String(card.exp_year).slice(-2)}`;
  return (
    <div
      role="group"
      aria-label={`Cartão ${card.brand_label} terminado em ${card.last4}, validade ${expiry}`}
      className="flex aspect-[1.586] w-full max-w-sm flex-col justify-between rounded-card bg-dl-stage p-5 text-dl-on-stage shadow-md"
    >
      <div className="flex items-start justify-between gap-3">
        <CreditCard aria-hidden className="size-7 text-dl-on-stage-muted" />
        <span className="text-sm font-semibold">{card.brand_label}</span>
      </div>
      <p aria-hidden className="text-lg font-medium tracking-[0.18em] tabular sm:text-xl">
        •••• •••• •••• {card.last4}
      </p>
      <div aria-hidden className="flex items-end justify-between gap-3 text-sm">
        <span className="min-w-0">
          <span className="block text-xs text-dl-on-stage-muted">Nome</span>
          <span className="block truncate font-medium">{card.holder_name}</span>
        </span>
        <span className="shrink-0 text-right">
          <span className="block text-xs text-dl-on-stage-muted">Validade</span>
          <span className="block font-medium tabular">{expiry}</span>
        </span>
      </div>
    </div>
  );
}
