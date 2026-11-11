/**
 * Catálogo de temas das lojas para a API (PHP). A API valida a personalização com as mesmas
 * regras do painel sem reimplementar o cálculo de contraste: lê este JSON, gerado daqui.
 * Arquivo gerado: apps/api/resources/design/theme-catalog.json (`npm run build -w @doleleilao/design-tokens`).
 */
import { ensureContrast } from "./contrast.ts";
import { allowedColorsFor } from "./store-theme.ts";
import { backgrounds, storeColors, storeFonts, templates, type BackgroundKey } from "./tokens.ts";

export interface ThemeCatalog {
  colors: string[];
  /** Cores para os e-mails da loja (sempre em fundo branco): botão na primária e link com contraste ≥ 4,5:1. */
  email: Record<string, { button: string; onButton: string; link: string }>;
  fonts: string[];
  backgrounds: Record<string, { dark: boolean; allowedColors: string[] }>;
  templates: Record<
    string,
    {
      backgrounds: string[];
      defaults: { primary: string; accent: string; background: string; headingFont: string };
    }
  >;
}

export function buildThemeCatalog(): ThemeCatalog {
  return {
    colors: Object.keys(storeColors),
    email: Object.fromEntries(
      Object.entries(storeColors).map(([key, c]) => [key, { button: c.hex, onButton: "#FFFFFF", link: ensureContrast(c.hex, "#FFFFFF", 4.5) }]),
    ),
    fonts: Object.keys(storeFonts),
    backgrounds: Object.fromEntries(
      (Object.keys(backgrounds) as BackgroundKey[]).map((key) => [
        key,
        { dark: backgrounds[key].dark, allowedColors: allowedColorsFor(key) },
      ]),
    ),
    templates: Object.fromEntries(
      Object.entries(templates).map(([key, t]) => [
        key,
        { backgrounds: [...t.backgrounds], defaults: { ...t.defaults } },
      ]),
    ),
  };
}

export function serializeThemeCatalog(): string {
  return JSON.stringify(buildThemeCatalog(), null, 2) + "\n";
}
