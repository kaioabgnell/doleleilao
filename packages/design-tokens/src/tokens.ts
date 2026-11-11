/**
 * Fonte única dos tokens de design do Dole Leilão.
 * Decisões: specs/07-design-system.md (D-01 a D-20, D-38).
 * Consumido pela web (CSS gerado em dist/tokens.css) e, na v2, pelo app React Native.
 */

/** Marca Dole Leilão — extraída de refs/logo-doleleilao.png (D-12). */
export const brand = {
  copper400: "#B07A4F", // só decorativo/degradê (3,7:1 com branco — não usar em texto)
  copper500: "#9C6642", // ícones, textos >= 24px
  copper600: "#8A5A38", // botões primários e links (5,8:1)
  copper800: "#7A4A2B", // hover/pressionado
  gradient: "linear-gradient(135deg, #B07A4F 0%, #9C6642 50%, #7A4A2B 100%)",
} as const;

/** Escala neutra "ink", puxada para o azul-esverdeado do grafite da logo. */
export const ink = {
  50: "#F4F6F6",
  100: "#E8ECEC",
  200: "#D3D9DA",
  300: "#B4BEC0",
  400: "#8C989B",
  500: "#667376",
  600: "#515E61",
  700: "#3E4A4D",
  800: "#343F42",
  900: "#2F3B3E",
  950: "#1E2628", // palco escuro da tela de entrada (D-53)
} as const;

/** Destaque das faixas grafite (site e tela de entrada, D-49 e D-53). Só sobre ink-900/950, nunca no fundo claro. */
export const highlight = {
  lime: "#C5F36A",
} as const;

export const surface = {
  page: "#F6F5F2",
  card: "#FFFFFF",
} as const;

/** Cores de estado do leilão — fixas em todas as lojas (D-05). */
export const state = {
  winning: "#1F7A4D",
  outbid: "#C0362C",
  live: "#C0362C",
  closed: "#6B7280",
  onState: "#FFFFFF",
} as const;

/** Paleta do leiloeiro: 10 cores para primária e destaque (D-04, D-13). */
export const storeColors = {
  copper: { name: "Cobre", hex: "#8A5A38" },
  graphite: { name: "Grafite", hex: "#2F3B3E" },
  black: { name: "Preto", hex: "#111111" },
  navy: { name: "Azul-marinho", hex: "#1E3A5F" },
  blue: { name: "Azul", hex: "#1D5FBF" },
  emerald: { name: "Esmeralda", hex: "#0F6B4F" },
  wine: { name: "Vinho", hex: "#7A1F2B" },
  purple: { name: "Roxo", hex: "#5B3A8C" },
  petrol: { name: "Petróleo", hex: "#0E5E6F" },
  "dark-gold": { name: "Dourado-escuro", hex: "#8A6A12" },
} as const;
export type StoreColorKey = keyof typeof storeColors;

/** Fontes de título do leiloeiro (D-14). `cssVar` casa com a variável criada pelo next/font. */
export const storeFonts = {
  inter: { name: "Inter", cssVar: "--font-inter" },
  roboto: { name: "Roboto", cssVar: "--font-roboto" },
  poppins: { name: "Poppins", cssVar: "--font-poppins" },
  "dm-sans": { name: "DM Sans", cssVar: "--font-dm-sans" },
  playfair: { name: "Playfair Display", cssVar: "--font-playfair" },
} as const;
export type StoreFontKey = keyof typeof storeFonts;

export type BackgroundKey =
  | "white"
  | "ivory"
  | "ebony"
  | "sand"
  | "mist"
  | "ice"
  | "light-graphite";

export const backgrounds: Record<BackgroundKey, { name: string; hex: string; dark: boolean }> = {
  white: { name: "Branco", hex: "#FFFFFF", dark: false },
  ivory: { name: "Marfim", hex: "#F7F4EE", dark: false },
  ebony: { name: "Ébano", hex: "#14161A", dark: true },
  sand: { name: "Areia", hex: "#F4EFE9", dark: false },
  mist: { name: "Névoa", hex: "#F2F2F2", dark: false },
  ice: { name: "Gelo", hex: "#F3F5F8", dark: false },
  "light-graphite": { name: "Grafite-claro", hex: "#EEF0F2", dark: false },
};

/** Os 3 modelos de loja (D-07, D-15): fundos permitidos e sugestões padrão. */
export const templates = {
  watches: {
    name: "Relógios",
    backgrounds: ["white", "ivory", "ebony"],
    defaults: { primary: "copper", accent: "dark-gold", background: "ivory", headingFont: "playfair" },
  },
  fashion: {
    name: "Roupa",
    backgrounds: ["white", "sand", "mist"],
    defaults: { primary: "black", accent: "wine", background: "white", headingFont: "dm-sans" },
  },
  electronics: {
    name: "Eletrônicos",
    backgrounds: ["white", "ice", "light-graphite"],
    defaults: { primary: "blue", accent: "navy", background: "ice", headingFont: "inter" },
  },
} as const satisfies Record<
  string,
  {
    name: string;
    backgrounds: readonly BackgroundKey[];
    defaults: {
      primary: StoreColorKey;
      accent: StoreColorKey;
      background: BackgroundKey;
      headingFont: StoreFontKey;
    };
  }
>;
export type TemplateKey = keyof typeof templates;

/** Texto sobre fundos escuros (Ébano). */
export const onDark = { text: "#F2EFEA", textMuted: "#B9B4AC" } as const;

export const typography = {
  brandHeading: "Montserrat",
  body: "Inter",
  /** Escala mobile first, base 16px. */
  scale: [12, 14, 16, 18, 20, 24, 30, 36, 48],
} as const;

export const space = [0, 4, 8, 12, 16, 24, 32, 48, 64] as const;

export const radius = { field: 6, card: 10, pill: 999 } as const;

/** Galeria de avatares do participante (D-38). Os SVGs são desenhados na Fase 2. */
export const avatarKeys = [
  "avatar-01", "avatar-02", "avatar-03", "avatar-04", "avatar-05", "avatar-06",
  "avatar-07", "avatar-08", "avatar-09", "avatar-10", "avatar-11", "avatar-12",
] as const;
export type AvatarKey = (typeof avatarKeys)[number];
