import type { Metadata } from "next";
import { LogOut } from "lucide-react";
import { connection } from "next/server";
import { Suspense } from "react";
import { NotificationBell } from "@/components/panel/notification-bell";
import { ConfirmProvider } from "@/components/ui/dialog";
import { PanelShell } from "@/components/panel/panel-shell";
import { getMe } from "@/lib/auctioneer";
import { logout } from "./actions";

export const metadata: Metadata = {
  title: { default: "Painel", template: "%s · Painel · Dole Leilão" },
  robots: { index: false },
};

/** Painel do leiloeiro: marca Dole Leilão (Montserrat/Inter, cobre/grafite), mobile first. */
export default function PanelLayout({ children }: LayoutProps<"/painel">) {
  return (
    <div className="flex flex-1">
      <PanelShell
        topbar={
          <>
            <NotificationBell tone="dark" />
            <Suspense fallback={<span className="h-4 w-24 animate-pulse rounded-full bg-dl-topbar-hover" />}>
              <AccountMenu />
            </Suspense>
          </>
        }
      >
        <ConfirmProvider>{children}</ConfirmProvider>
      </PanelShell>
    </div>
  );
}

async function AccountMenu() {
  // Conta do usuário: sempre na hora do pedido (sem isto o pré-render em dev acusa Date.now dentro do fetch).
  await connection();
  const { auctioneer } = await getMe();
  const initials = auctioneer.display_name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  return (
    <div className="flex min-w-0 items-center gap-1 border-l border-dl-topbar-hover pl-2 sm:gap-2 sm:pl-3">
      <span
        aria-hidden
        className="flex size-8 shrink-0 items-center justify-center rounded-full bg-dl-page text-xs font-semibold text-dl-copper"
      >
        {initials}
      </span>
      <span className="hidden max-w-40 truncate text-sm font-medium text-dl-on-topbar sm:inline" title={auctioneer.company_name}>
        {auctioneer.display_name}
      </span>
      <form action={logout}>
        <button
          type="submit"
          aria-label="Sair"
          title="Sair"
          className="flex size-11 items-center justify-center rounded-field text-dl-on-topbar-muted hover:bg-dl-topbar-hover hover:text-dl-on-topbar focus-visible:outline-2 focus-visible:outline-dl-on-topbar"
        >
          <LogOut aria-hidden className="size-5" />
        </button>
      </form>
    </div>
  );
}
