"use client";

import { useActionState, useState } from "react";
import { SlugInput } from "@/components/forms/slug-input";
import { Alert } from "@/components/ui/alert";
import { SubmitButton } from "@/components/ui/button";
import type { Auctioneer } from "@/lib/api-types";
import { formatDate } from "@/lib/format";
import { changeSlug, type PanelFormState } from "../actions";

/** Troca do endereço da loja (RF-A06): 1 vez a cada 30 dias, o antigo redireciona por 90 dias. */
export function SlugForm({ auctioneer }: { auctioneer: Auctioneer }) {
  const [state, action] = useActionState<PanelFormState, FormData>(changeSlug, {});
  const [slug, setSlug] = useState(auctioneer.slug);
  const lockedUntil =
    auctioneer.next_slug_change_at && new Date(auctioneer.next_slug_change_at) > new Date()
      ? auctioneer.next_slug_change_at
      : null;

  return (
    <section aria-labelledby="slug-title" className="flex flex-col gap-4 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6 lg:max-w-[calc(100%-364px)]">
      <div>
        <h2 id="slug-title" className="font-brand text-lg font-bold text-dl-ink">
          Endereço da loja
        </h2>
        <p className="mt-0.5 text-sm text-dl-ink-muted">
          Pode ser trocado 1 vez a cada 30 dias. O endereço antigo continua levando para a sua loja por 90 dias.
        </p>
      </div>

      {state.success ? <Alert tone="success" title={state.success} /> : null}

      {lockedUntil ? (
        <Alert title={`Próxima troca a partir de ${formatDate(lockedUntil)}.`}>
          Endereço atual: doleleilao.com.br/{auctioneer.slug}
        </Alert>
      ) : (
        <form action={action} noValidate className="flex flex-col gap-4">
          <SlugInput
            label="Novo endereço"
            value={slug}
            current={auctioneer.slug}
            onChange={setSlug}
            error={state.errors?.slug?.[0]}
          />
          <SubmitButton variant="secondary" pendingLabel="Trocando…" disabled={slug === auctioneer.slug} className="self-start">
            Trocar endereço
          </SubmitButton>
        </form>
      )}
    </section>
  );
}
