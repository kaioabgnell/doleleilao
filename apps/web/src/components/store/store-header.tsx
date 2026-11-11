import Link from "next/link";
import { Suspense } from "react";
import { NotificationBell } from "@/components/panel/notification-bell";
import type { PublicStorefront } from "@/lib/api-types";
import { getBidder } from "@/lib/bidder-session";
import { AccountMenu } from "./account-menu";
import { StoreHeaderLayout } from "./templates/header";

/**
 * Topo da loja: o arranjo muda com o modelo (D-07); a conta do participante (com o sino, D-43)
 * entra como peça do servidor.
 */
export function StoreHeader({ storefront, q }: { storefront: PublicStorefront; q?: string }) {
  return (
    <StoreHeaderLayout
      template={storefront.theme.template}
      slug={storefront.slug}
      name={storefront.display_name}
      logoUrl={storefront.logo.url}
      categories={storefront.categories}
      q={q}
      account={
        <Suspense fallback={<span className="h-8 w-20 animate-pulse rounded-full bg-store-border/50" />}>
          <StoreAccount slug={storefront.slug} />
        </Suspense>
      }
    />
  );
}

async function StoreAccount({ slug }: { slug: string }) {
  const bidder = await getBidder();
  if (!bidder) {
    return (
      <div className="flex items-center gap-1">
        <Link href={`/${slug}/entrar`} className="flex min-h-11 items-center rounded-field px-3 text-sm font-medium hover:text-store-link">
          Entrar
        </Link>
        <Link href={`/${slug}/cadastro`} className="hidden min-h-11 items-center rounded-field bg-store-primary px-4 text-sm font-semibold text-store-on-primary hover:bg-store-primary-hover sm:flex">
          Cadastre-se
        </Link>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-1">
      <NotificationBell endpoint={`/${slug}/api/conta/notifications`} readEndpoint={`/${slug}/api/conta/notifications/read`} />
      <AccountMenu slug={slug} name={bidder.name} nickname={bidder.nickname} email={bidder.email} photoUrl={bidder.photo_url} avatarKey={bidder.avatar_key} />
    </div>
  );
}
