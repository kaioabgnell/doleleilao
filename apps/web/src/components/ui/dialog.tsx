"use client";

import { createContext, useCallback, useContext, useEffect, useId, useRef, useState, type ReactNode } from "react";
import { CircleAlert, CircleHelp, X } from "lucide-react";
import { buttonClass } from "./button-styles";

/**
 * Modal do painel (design system Dole Leilão): `<dialog>` nativo, então o foco fica preso dentro,
 * Esc fecha e o fundo escurece. No celular abre como folha de baixo para cima; no computador, centralizado.
 * Respeita "reduzir movimento".
 */
export function Modal({
  open,
  onClose,
  title,
  description,
  icon,
  tone = "default",
  children,
  footer,
  size = "md",
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: ReactNode;
  icon?: ReactNode;
  tone?: "default" | "danger";
  children?: ReactNode;
  footer?: ReactNode;
  size?: "md" | "lg";
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const descriptionId = useId();

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      dialog.showModal();
      // Foco inicial: o elemento marcado com data-autofocus (senão o <dialog> escolheria o "fechar").
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
    if (!open && dialog.open) dialog.close();
  }, [open]);

  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      aria-describedby={description ? descriptionId : undefined}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      onClick={(e) => {
        // Clique no fundo escurecido fecha (o próprio <dialog> recebe o clique fora da caixa).
        if (e.target === e.currentTarget) onClose();
      }}
      className={`m-auto w-[calc(100%-2rem)] ${size === "lg" ? "max-w-2xl" : "max-w-md"} max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-card border-0 bg-dl-card p-0 text-dl-ink shadow-2xl backdrop:bg-dl-ink/45 max-sm:inset-x-0 max-sm:top-auto max-sm:bottom-0 max-sm:m-0 max-sm:w-full max-sm:max-w-full max-sm:rounded-b-none max-sm:pb-[env(safe-area-inset-bottom)] open:animate-[dl-dialog-in_160ms_ease-out] motion-reduce:open:animate-none`}
    >
      {open ? (
        <div className="flex flex-col gap-4 p-5 sm:p-6">
          <div className="flex items-start gap-3">
            {icon !== null ? (
              <span
                aria-hidden
                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${tone === "danger" ? "bg-state-outbid/10 text-state-outbid" : "bg-dl-copper/10 text-dl-copper"}`}
              >
                {icon ?? (tone === "danger" ? <CircleAlert className="size-5" /> : <CircleHelp className="size-5" />)}
              </span>
            ) : null}
            <div className="min-w-0 flex-1 pt-1.5">
              <h2 id={titleId} className="font-brand text-lg font-bold text-dl-ink">
                {title}
              </h2>
              {description ? (
                <div id={descriptionId} className="mt-1 text-sm text-dl-ink-muted">
                  {description}
                </div>
              ) : null}
            </div>
            <button
              type="button"
              onClick={onClose}
              className="-mt-1 -mr-2 flex size-10 shrink-0 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-dl-ink focus-visible:outline-2 focus-visible:outline-dl-copper"
              aria-label="Fechar"
            >
              <X aria-hidden className="size-5" />
            </button>
          </div>
          {children}
          {footer ? <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{footer}</div> : null}
        </div>
      ) : null}
    </dialog>
  );
}

/** Botão de ação perigosa (excluir, banir, anular), nas cores de estado fixas. */
export const dangerButton =
  "inline-flex min-h-11 items-center justify-center gap-2 rounded-field bg-state-outbid px-5 text-base font-semibold text-white transition-colors hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-state-outbid disabled:cursor-not-allowed disabled:opacity-60";

export interface ConfirmOptions {
  title: string;
  description?: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: "default" | "danger";
  icon?: ReactNode;
}

type Confirm = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<Confirm | null>(null);

/**
 * Confirmação padrão do sistema, no lugar do `window.confirm`:
 * `if (!(await confirm({ title: "Apagar?", tone: "danger" }))) return;`
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [request, setRequest] = useState<(ConfirmOptions & { resolve: (ok: boolean) => void }) | null>(null);

  const confirm = useCallback<Confirm>((options) => new Promise((resolve) => setRequest({ ...options, resolve })), []);

  function settle(ok: boolean) {
    request?.resolve(ok);
    setRequest(null);
  }

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <Modal
        open={request !== null}
        onClose={() => settle(false)}
        title={request?.title ?? ""}
        description={request?.description}
        tone={request?.tone}
        icon={request?.icon}
        footer={
          <>
            {/* Ação perigosa: o foco começa em "Cancelar", para um Enter distraído não apagar nada. */}
            <button type="button" className={buttonClass("secondary")} onClick={() => settle(false)} data-autofocus={request?.tone === "danger" ? "" : undefined}>
              {request?.cancelLabel ?? "Cancelar"}
            </button>
            <button type="button" className={request?.tone === "danger" ? dangerButton : buttonClass("primary")} onClick={() => settle(true)} data-autofocus={request?.tone === "danger" ? undefined : ""}>
              {request?.confirmLabel ?? "Confirmar"}
            </button>
          </>
        }
      />
    </ConfirmContext.Provider>
  );
}

export function useConfirm(): Confirm {
  const confirm = useContext(ConfirmContext);
  if (!confirm) throw new Error("useConfirm precisa estar dentro de <ConfirmProvider>.");
  return confirm;
}
