"use client";

import { useEffect, useState } from "react";
import type { SlugAvailability } from "@/lib/api-types";
import { slugify } from "@/lib/format";
import { describedBy, Field, inputClass } from "@/components/ui/field";

type Checked = { slug: string; result: SlugAvailability | null };

interface Props {
  value: string;
  onChange: (slug: string) => void;
  error?: string;
  label?: string;
  /** Endereço atual da loja (troca de endereço): não precisa checar. */
  current?: string;
}

/** Campo do endereço da loja com o prefixo do site e a checagem de disponibilidade ao vivo. */
export function SlugInput({ value, onChange, error, label = "Endereço da loja", current }: Props) {
  const [checked, setChecked] = useState<Checked | null>(null);
  const skip = value.length < 3 || value === current;

  useEffect(() => {
    if (skip) return;
    const controller = new AbortController();
    const timer = setTimeout(async () => {
      try {
        const res = await fetch(`/api/loja/endereco?slug=${encodeURIComponent(value)}`, { signal: controller.signal });
        if (!res.ok) throw new Error(String(res.status));
        setChecked({ slug: value, result: (await res.json()) as SlugAvailability });
      } catch {
        if (!controller.signal.aborted) setChecked({ slug: value, result: null });
      }
    }, 400);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [value, skip]);

  // O estado mostrado é derivado: só vale o resultado do endereço que está no campo agora.
  const check = skip ? "idle" : checked?.slug === value ? (checked.result ? "done" : "failed") : "checking";
  const result = checked?.slug === value ? checked.result : null;

  const status =
    check === "checking" ? (
      <span className="text-dl-ink-muted">Conferindo…</span>
    ) : check === "done" && result ? (
      result.available ? (
        <span className="font-medium text-state-winning">Disponível</span>
      ) : (
        <span className="font-medium text-state-outbid">{result.message}</span>
      )
    ) : (
      <span className="text-dl-ink-muted">Letras minúsculas, números e hífen. De 3 a 40 caracteres.</span>
    );

  return (
    <Field id="slug" label={label} error={error} hint={<span aria-live="polite">{status}</span>}>
      <div className="flex min-h-11 items-stretch overflow-hidden rounded-field border border-dl-line bg-dl-card focus-within:border-dl-copper focus-within:outline-2 focus-within:outline-dl-copper/30">
        <span className="flex items-center border-r border-dl-line bg-dl-page px-3 text-sm text-dl-ink-muted select-none">
          doleleilao.com.br/
        </span>
        <input
          id="slug"
          name="slug"
          value={value}
          onChange={(e) => onChange(slugify(e.target.value))}
          autoComplete="off"
          autoCapitalize="none"
          spellCheck={false}
          inputMode="url"
          maxLength={40}
          required
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy("slug", error, true)}
          className={`${inputClass} min-w-0 rounded-none border-0 px-2 focus:outline-none`}
        />
      </div>
    </Field>
  );
}
