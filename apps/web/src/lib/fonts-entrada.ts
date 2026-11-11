/**
 * Fontes da tela de entrada (D-53): as mesmas do site institucional (D-50), Host Grotesk + JetBrains Mono.
 * Ficam fora de `fonts.ts` para só serem baixadas nas telas que importam este módulo.
 */
import { Host_Grotesk, JetBrains_Mono } from "next/font/google";

const hostGrotesk = Host_Grotesk({ subsets: ["latin"], variable: "--font-host-grotesk", display: "swap" });

const jetbrainsMono = JetBrains_Mono({
  subsets: ["latin"],
  weight: ["400", "500"],
  variable: "--font-jetbrains-mono",
  display: "swap",
});

export const entradaFontVariables = `${hostGrotesk.variable} ${jetbrainsMono.variable}`;
