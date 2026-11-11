"use client";

import { useState } from "react";
import { describedBy, Field, inputClass } from "@/components/ui/field";

interface Props {
  id?: string;
  label?: string;
  error?: string;
  hint?: string;
  autoComplete: "current-password" | "new-password";
}

/** Senha com botão de mostrar/ocultar (sem campo de confirmação). */
export function PasswordInput({ id = "password", label = "Senha", error, hint, autoComplete }: Props) {
  const [visible, setVisible] = useState(false);
  return (
    <Field id={id} label={label} error={error} hint={hint}>
      <div className="relative">
        <input
          id={id}
          name="password"
          type={visible ? "text" : "password"}
          autoComplete={autoComplete}
          required
          minLength={autoComplete === "new-password" ? 8 : undefined}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(id, error, Boolean(hint))}
          className={`${inputClass} pr-20`}
        />
        <button
          type="button"
          onClick={() => setVisible((v) => !v)}
          aria-pressed={visible}
          className="absolute inset-y-0 right-0 min-w-16 rounded-r-field px-3 text-sm font-medium text-dl-copper hover:underline focus-visible:outline-2 focus-visible:outline-dl-copper"
        >
          {visible ? "Ocultar" : "Mostrar"}
        </button>
      </div>
    </Field>
  );
}
