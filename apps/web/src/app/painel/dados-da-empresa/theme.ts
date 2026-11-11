import {
  allowedColorsFor,
  templates,
  type BackgroundKey,
  type StoreColorKey,
  type StoreFontKey,
  type TemplateKey,
} from "@doleleilao/design-tokens";

export interface ThemeChoice {
  template: TemplateKey;
  primary: StoreColorKey;
  accent: StoreColorKey;
  background: BackgroundKey;
  headingFont: StoreFontKey;
}

/** Mesmas regras da API (StoreTheme): o painel mostra o resultado antes de salvar. */
export function switchTemplate(current: ThemeChoice | null, template: TemplateKey): ThemeChoice {
  const defaults = templates[template].defaults;
  if (!current) return { template, ...defaults };
  const allowedBackgrounds: readonly BackgroundKey[] = templates[template].backgrounds;
  const background = allowedBackgrounds.includes(current.background) ? current.background : defaults.background;
  return adjustColors({ ...current, template, background });
}

export function changeBackground(current: ThemeChoice, background: BackgroundKey): ThemeChoice {
  return adjustColors({ ...current, background });
}

/** Troca as cores sem contraste com o fundo pelos padrões do modelo. */
function adjustColors(theme: ThemeChoice): ThemeChoice {
  const allowed = allowedColorsFor(theme.background);
  const defaults = templates[theme.template].defaults;
  const pick = (color: StoreColorKey, fallback: StoreColorKey): StoreColorKey =>
    allowed.includes(color) ? color : allowed.includes(fallback) ? fallback : (allowed[0] ?? fallback);
  return { ...theme, primary: pick(theme.primary, defaults.primary), accent: pick(theme.accent, defaults.accent) };
}
