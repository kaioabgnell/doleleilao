import type { ReactNode } from "react";

export const inputClass =
  "block min-h-11 w-full rounded-field border border-dl-line bg-dl-card px-3 text-base text-dl-ink placeholder:text-dl-ink-muted/70 focus:border-dl-copper focus:outline-2 focus:outline-offset-0 focus:outline-dl-copper/30 aria-invalid:border-state-outbid";

interface FieldProps {
  id: string;
  label: string;
  hint?: ReactNode;
  error?: string;
  children: ReactNode;
}

/**
 * Rótulo + campo + dica + erro. O campo deve usar `aria-describedby={describedBy(id, …)}`
 * e `aria-invalid` quando houver erro.
 */
export function Field({ id, label, hint, error, children }: FieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-dl-ink">
        {label}
      </label>
      {children}
      {hint && !error ? (
        <p id={`${id}-hint`} className="text-sm text-dl-ink-muted">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-sm font-medium text-state-outbid">
          {error}
        </p>
      ) : null}
    </div>
  );
}

export function describedBy(id: string, error?: string, hasHint = false): string | undefined {
  if (error) return `${id}-error`;
  return hasHint ? `${id}-hint` : undefined;
}
