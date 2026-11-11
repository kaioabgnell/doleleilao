import type { CSSProperties } from "react";

/** Onde o chat aparece: na loja (cores do leiloeiro) ou no painel (marca Dole Leilão). */
export type ChatTone = "store" | "panel";

export interface ChatTheme {
  box: string;
  divider: string;
  text: string;
  muted: string;
  link: string;
  input: string;
  send: string;
  bubble: string;
  /** Fundo do destaque do leiloeiro. */
  staff: string;
  /** Linha do leiloeiro na conversa: cinza tirado da cor do texto (funciona no fundo claro e no Ébano). */
  staffRow: string;
  chipSolid: string;
  chip: string;
  hover: string;
  style?: CSSProperties;
}

export const chatTheme: Record<ChatTone, ChatTheme> = {
  store: {
    box: "border-store-border bg-store-surface",
    divider: "border-store-border",
    text: "text-store-text",
    muted: "text-store-text-muted",
    link: "text-store-link",
    input: "border-store-border bg-store-surface text-store-text placeholder:text-store-text-muted focus:border-store-primary focus:outline-store-primary/30",
    send: "bg-store-primary text-store-on-primary hover:bg-store-primary-hover",
    bubble: "bg-store-bg",
    staff: "bg-store-accent/15",
    staffRow: "bg-store-text/[0.07]",
    chipSolid: "bg-store-text/10 text-store-text",
    chip: "border-store-border bg-store-surface text-store-text hover:border-store-primary",
    hover: "hover:bg-store-bg",
  },
  panel: {
    box: "border-dl-line bg-dl-card",
    divider: "border-dl-line",
    text: "text-dl-ink",
    muted: "text-dl-ink-muted",
    link: "text-dl-copper",
    input: "border-dl-line bg-dl-card text-dl-ink placeholder:text-dl-ink-muted/70 focus:border-dl-copper focus:outline-dl-copper/30",
    send: "bg-dl-copper text-white hover:bg-dl-copper-hover",
    bubble: "bg-dl-page",
    staff: "bg-dl-copper/10",
    staffRow: "bg-dl-ink/[0.07]",
    chipSolid: "bg-dl-ink/10 text-dl-ink",
    chip: "border-dl-line bg-dl-card text-dl-ink hover:border-dl-copper hover:text-dl-copper",
    hover: "hover:bg-dl-page",
    // Os avatares usam as variáveis da loja; no painel, as cores da marca.
    style: { "--store-primary": "var(--dl-copper-600)", "--store-on-primary": "var(--dl-surface-card)" } as CSSProperties,
  },
};

export const chatClock = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", hour: "2-digit", minute: "2-digit" });
