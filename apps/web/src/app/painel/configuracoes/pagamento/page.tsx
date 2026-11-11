import type { Metadata } from "next";
import { Suspense } from "react";
import type { BillingSettings } from "@/lib/api-types";
import { panelApi } from "@/lib/auctioneer";
import { formatRate, tierRange } from "@/lib/credit-card";
import { formatCents } from "@/lib/money";
import { PaymentCardForm } from "./payment-card-form";

export const metadata: Metadata = { title: "Pagamento" };

/** Configurações → Pagamento (D-54): o cartão onde a taxa é debitada e as faixas da taxa (D-47). */
export default function PaymentPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <p className="text-sm font-medium text-dl-ink-muted">Configurações</p>
        <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Pagamento</h1>
        <p className="mt-1 max-w-3xl text-dl-ink-muted">
          Cadastrar e usar o Dole Leilão é grátis. Você paga só uma taxa sobre cada item arrematado, debitada neste cartão. Ele é necessário para agendar leilões.
        </p>
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-card bg-dl-card" aria-busy="true" />}>
        <PaymentContent />
      </Suspense>
    </div>
  );
}

async function PaymentContent() {
  const result = await panelApi<BillingSettings>("/auctioneer/billing");
  if (!result.ok) throw new Error(result.problem.detail ?? "Não foi possível carregar o pagamento.");
  const { card, fee_tiers: tiers, fees_start_at: startsAt, fees_started: started } = result.data;
  const example = tiers.find((t) => t.min_cents === 0 && t.max_cents !== null);

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
      <PaymentCardForm card={card} />

      <section aria-labelledby="fee-title" className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-4 sm:p-6">
        <div>
          <h2 id="fee-title" className="font-brand text-lg font-bold text-dl-ink">
            Como funciona a taxa
          </h2>
          <p className="mt-1 text-sm text-dl-ink-muted">Uma alíquota só por item, pela faixa do valor arrematado. Item sem venda não paga nada.</p>
        </div>

        {tiers.length > 0 ? (
          <table className="w-full text-sm">
            <caption className="sr-only">Faixas da taxa por item arrematado</caption>
            <thead>
              <tr className="border-b border-dl-line text-left text-dl-ink-muted">
                <th scope="col" className="py-2 font-medium">
                  Valor arrematado
                </th>
                <th scope="col" className="py-2 text-right font-medium">
                  Taxa
                </th>
              </tr>
            </thead>
            <tbody>
              {tiers.map((tier) => (
                <tr key={`${tier.valid_from}-${tier.min_cents}`} className="border-b border-dl-line last:border-0">
                  <td className="py-2.5 text-dl-ink tabular">{tierRange(tier)}</td>
                  <td className="py-2.5 text-right font-semibold text-dl-ink tabular">{formatRate(tier.rate_bps)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}

        <ul className="flex flex-col gap-2 text-sm text-dl-ink-muted">
          {example && example.max_cents !== null ? (
            <li>
              Exemplo: item arrematado por <span className="text-dl-ink tabular">{formatCents(example.max_cents)}</span> paga{" "}
              <span className="text-dl-ink tabular">{formatCents(Math.round((example.max_cents * example.rate_bps) / 10000))}</span> de taxa.
            </li>
          ) : null}
          <li>Quando o leilão termina, as taxas dos itens vendidos são somadas num débito só, neste cartão.</li>
          <li>No cadastro de cada leilão você escolhe se assume a taxa ou se repassa ao vencedor.</li>
          {startsAt && !started ? (
            <li className="font-medium text-dl-ink">Leilões até {lastFreeDay(startsAt)} não pagam taxa.</li>
          ) : null}
        </ul>
      </section>
    </div>
  );
}

/** Véspera do início da cobrança, no horário de Brasília: "30/11/2026". */
function lastFreeDay(iso: string): string {
  return new Date(new Date(iso).getTime() - 1).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" });
}
