"use client";

import type { ReactNode } from "react";
import { useFormStatus } from "react-dom";

/** Campos e botões da loja: usam as cores do leiloeiro (--store-*), legíveis também no fundo Ébano. */
export const storeInput =
  "block min-h-11 w-full rounded-field border border-store-border bg-store-surface px-3 text-base text-store-text placeholder:text-store-text-muted focus:border-store-primary focus:outline-2 focus:outline-offset-0 focus:outline-store-primary/30 aria-invalid:border-state-outbid";

export const storeButton =
  "inline-flex min-h-11 items-center justify-center gap-2 rounded-field bg-store-primary px-5 font-semibold text-store-on-primary hover:bg-store-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-store-primary disabled:opacity-60";

export const storeButtonOutline =
  "inline-flex min-h-11 items-center justify-center gap-2 rounded-field border border-store-border px-5 font-semibold text-store-text hover:border-store-primary focus-visible:outline-2 focus-visible:outline-store-primary disabled:opacity-60";

export function StoreField({ id, label, hint, error, children }: { id: string; label: string; hint?: ReactNode; error?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">{label}</label>
      {children}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-sm font-medium text-state-outbid">{error}</p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-sm text-store-text-muted">{hint}</p>
      ) : null}
    </div>
  );
}

export function StoreSubmit({ children, pending }: { children: ReactNode; pending: string }) {
  const { pending: isPending } = useFormStatus();
  return (
    <button type="submit" disabled={isPending} aria-busy={isPending} className={storeButton}>
      {isPending ? pending : children}
    </button>
  );
}

export function StoreNotice({ tone = "info", children }: { tone?: "info" | "success" | "error"; children: ReactNode }) {
  const marker = tone === "success" ? "bg-state-winning" : tone === "error" ? "bg-state-outbid" : "bg-store-primary";
  return (
    <div role={tone === "error" ? "alert" : "status"} className="flex gap-3 rounded-card border border-store-border bg-store-surface p-4 text-sm">
      <span aria-hidden className={`mt-1.5 size-2 shrink-0 rounded-full ${marker}`} />
      <div>{children}</div>
    </div>
  );
}

export function aria(id: string, error?: string, hint = false) {
  return { "aria-invalid": error ? true : undefined, "aria-describedby": error ? `${id}-error` : hint ? `${id}-hint` : undefined } as const;
}
