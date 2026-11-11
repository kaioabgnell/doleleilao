/**
 * Resolve a personalização do leiloeiro (modelo, cores, fundo, fonte) em variáveis CSS `--store-*`.
 * Regras: specs/07-design-system.md#personalização-do-leiloeiro (D-04, D-07, D-13, D-15).
 */
import { contrast, darken, ensureContrast, lighten, WCAG } from "./contrast.ts";
import {
  backgrounds,
  onDark,
  ink,
  storeColors,
  storeFonts,
  surface,
  templates,
  type BackgroundKey,
  type StoreColorKey,
  type StoreFontKey,
  type TemplateKey,
} from "./tokens.ts";

export interface StoreThemeInput {
  template: TemplateKey;
  primary: StoreColorKey;
  accent: StoreColorKey;
  background: BackgroundKey;
  headingFont: StoreFontKey;
}

export type ThemeIssue =
  | { code: "background-not-in-template"; background: BackgroundKey; template: TemplateKey }
  | { code: "low-contrast-on-background"; role: "primary" | "accent"; color: StoreColorKey; ratio: number };

/** Uma cor pode ser primária/destaque num fundo se o botão (cor sólida) tiver >= 3:1 contra ele. */
export function isColorAllowedOn(color: StoreColorKey, background: BackgroundKey): boolean {
  return contrast(storeColors[color].hex, backgrounds[background].hex) >= WCAG.ui;
}

/** Cores que o painel deve mostrar habilitadas para o fundo escolhido. */
export function allowedColorsFor(background: BackgroundKey): StoreColorKey[] {
  return (Object.keys(storeColors) as StoreColorKey[]).filter((c) => isColorAllowedOn(c, background));
}

export function validateStoreTheme(input: StoreThemeInput): ThemeIssue[] {
  const issues: ThemeIssue[] = [];
  const allowedBackgrounds: readonly BackgroundKey[] = templates[input.template].backgrounds;
  if (!allowedBackgrounds.includes(input.background)) {
    issues.push({ code: "background-not-in-template", background: input.background, template: input.template });
  }
  for (const role of ["primary", "accent"] as const) {
    const color = input[role];
    const ratio = contrast(storeColors[color].hex, backgrounds[input.background].hex);
    if (ratio < WCAG.ui) issues.push({ code: "low-contrast-on-background", role, color, ratio });
  }
  return issues;
}

/** Variáveis CSS da loja. Lança erro se a combinação for inválida (o painel já impede isso antes). */
export function resolveStoreTheme(input: StoreThemeInput): Record<`--store-${string}`, string> {
  const issues = validateStoreTheme(input);
  if (issues.length > 0) {
    throw new Error(`Tema de loja inválido: ${JSON.stringify(issues)}`);
  }
  const bg = backgrounds[input.background];
  const primary = storeColors[input.primary].hex;
  const accent = storeColors[input.accent].hex;
  const text = bg.dark ? onDark.text : ink[900];
  const textMuted = bg.dark ? onDark.textMuted : ink[600];
  const surfaceCard = bg.dark ? lighten(bg.hex, 0.06) : surface.card;
  const border = bg.dark ? lighten(bg.hex, 0.16) : ink[200];

  // Link: no fundo claro, escurece a primária até 4,5:1. No escuro, usa o tom claro da primária.
  const link = bg.dark
    ? ensureLighter(primary, bg.hex, WCAG.text)
    : ensureContrast(primary, bg.hex, WCAG.text);

  return {
    "--store-primary": primary,
    "--store-primary-hover": darken(primary, 0.12),
    "--store-on-primary": "#FFFFFF",
    "--store-accent": accent,
    "--store-on-accent": "#FFFFFF",
    "--store-bg": bg.hex,
    "--store-surface": surfaceCard,
    "--store-border": border,
    "--store-text": text,
    "--store-text-muted": textMuted,
    "--store-link": link,
    "--store-font-heading": `var(${storeFonts[input.headingFont].cssVar})`,
  };
}

function ensureLighter(fg: string, bg: string, min: number): string {
  let color = fg;
  for (let i = 1; i <= 20 && contrast(color, bg) < min; i++) color = lighten(fg, i * 0.05);
  return color;
}
