/** Contraste WCAG 2.x entre duas cores hex (#RRGGBB). */

function channel(value: number): number {
  const c = value / 255;
  return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

function parse(hex: string): [number, number, number] {
  const m = /^#([0-9a-f]{6})$/i.exec(hex);
  if (!m) throw new Error(`Cor inválida: ${hex}`);
  const n = parseInt(m[1], 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function toHex([r, g, b]: [number, number, number]): string {
  return "#" + [r, g, b].map((v) => Math.round(v).toString(16).padStart(2, "0")).join("").toUpperCase();
}

export function luminance(hex: string): number {
  const [r, g, b] = parse(hex);
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function contrast(a: string, b: string): number {
  const la = luminance(a);
  const lb = luminance(b);
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** Mistura a cor com preto (amount 0..1). */
export function darken(hex: string, amount: number): string {
  const [r, g, b] = parse(hex);
  const k = 1 - amount;
  return toHex([r * k, g * k, b * k]);
}

/** Mistura a cor com branco (amount 0..1). */
export function lighten(hex: string, amount: number): string {
  const [r, g, b] = parse(hex);
  return toHex([r + (255 - r) * amount, g + (255 - g) * amount, b + (255 - b) * amount]);
}

/** Escurece a cor em passos de 5% até atingir o contraste mínimo contra o fundo. */
export function ensureContrast(fg: string, bg: string, min: number): string {
  let color = fg;
  for (let i = 0; i < 20 && contrast(color, bg) < min; i++) color = darken(color, 0.05);
  return color;
}

export const WCAG = { text: 4.5, largeText: 3, ui: 3 } as const;
