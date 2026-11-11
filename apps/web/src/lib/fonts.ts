/**
 * Fontes self-hosted via next/font (sem chamadas ao Google em tempo de execução).
 * - Marca Dole Leilão: Montserrat (títulos) + Inter (texto e números) — D-18.
 * - Lojas: 5 fontes de título à escolha do leiloeiro — D-14.
 * As variáveis CSS batem com `storeFonts[*].cssVar` em @doleleilao/design-tokens.
 */
import { DM_Sans, Inter, Montserrat, Playfair_Display, Poppins, Roboto } from "next/font/google";

export const inter = Inter({ subsets: ["latin"], variable: "--font-inter", display: "swap" });

export const montserrat = Montserrat({
  subsets: ["latin"],
  weight: ["600", "700"],
  variable: "--font-montserrat",
  display: "swap",
});

// Fontes das lojas: não pré-carregadas. A loja só baixa a fonte que escolheu.
export const roboto = Roboto({
  subsets: ["latin"],
  weight: ["500", "700"],
  variable: "--font-roboto",
  display: "swap",
  preload: false,
});
export const poppins = Poppins({
  subsets: ["latin"],
  weight: ["500", "600", "700"],
  variable: "--font-poppins",
  display: "swap",
  preload: false,
});
export const dmSans = DM_Sans({ subsets: ["latin"], variable: "--font-dm-sans", display: "swap", preload: false });
export const playfair = Playfair_Display({
  subsets: ["latin"],
  variable: "--font-playfair",
  display: "swap",
  preload: false,
});

export const fontVariables = [inter, montserrat, roboto, poppins, dmSans, playfair]
  .map((f) => f.variable)
  .join(" ");
