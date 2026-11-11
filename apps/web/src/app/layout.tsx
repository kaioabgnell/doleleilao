import type { Metadata, Viewport } from "next";
import Script from "next/script";
import { fontVariables } from "@/lib/fonts";
import { restoreSidebarScript } from "@/lib/panel-menu";
import { SITE_URL } from "@/lib/site-url";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: "Dole Leilão",
    template: "%s · Dole Leilão",
  },
  description: "Sua loja de leilões online, com auditório ao vivo, lances em tempo real e chat.",
};

export const viewport: Viewport = {
  themeColor: "#F6F5F2",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    // suppressHydrationWarning: o script abaixo pode marcar <html data-sidebar> antes da hidratação.
    <html lang="pt-BR" className={`${fontVariables} h-full antialiased`} suppressHydrationWarning>
      <body className="flex min-h-full flex-col font-sans">
        {children}
        <Script id="dl-painel-menu" strategy="beforeInteractive">
          {restoreSidebarScript}
        </Script>
      </body>
    </html>
  );
}
