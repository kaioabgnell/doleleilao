/** Estilos dos botões (módulo neutro: usado por componentes de servidor e de cliente). */
export type ButtonVariant = "primary" | "secondary" | "ghost";

const base =
  "inline-flex min-h-11 items-center justify-center gap-2 rounded-field px-5 text-base font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-dl-copper disabled:cursor-not-allowed disabled:opacity-60";

const variants: Record<ButtonVariant, string> = {
  primary: "bg-dl-copper text-white hover:bg-dl-copper-hover",
  secondary: "border border-dl-line bg-dl-card text-dl-ink hover:border-dl-copper hover:text-dl-copper",
  ghost: "text-dl-copper hover:bg-dl-page underline-offset-4 hover:underline",
};

export function buttonClass(variant: ButtonVariant = "primary", extra = ""): string {
  return `${base} ${variants[variant]} ${extra}`;
}
