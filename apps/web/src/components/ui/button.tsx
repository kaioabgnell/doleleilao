"use client";

import type { ButtonHTMLAttributes } from "react";
import { useFormStatus } from "react-dom";
import { buttonClass, type ButtonVariant } from "./button-styles";

export { buttonClass };

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  pendingLabel?: string;
}

/** Botão de envio que se desabilita enquanto a ação do formulário roda. */
export function SubmitButton({ variant = "primary", pendingLabel, children, className = "", ...rest }: Props) {
  const { pending } = useFormStatus();
  return (
    <button
      type="submit"
      disabled={pending || rest.disabled}
      aria-busy={pending}
      className={buttonClass(variant, className)}
      {...rest}
    >
      {pending ? (
        <>
          <span
            aria-hidden
            className="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
          />
          {pendingLabel ?? children}
        </>
      ) : (
        children
      )}
    </button>
  );
}
