"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { LogOut } from "lucide-react";
import { accountHref, accountSections } from "@/components/store/account-sections";
import { logoutBidder } from "../acoes";

/** Menu da conta: abas roláveis no celular e coluna lateral no computador. */
export function AccountNav({ slug, awardsCount }: { slug: string; awardsCount: number }) {
  const pathname = usePathname();
  const nav = useRef<HTMLElement>(null);

  // No celular as abas rolam para o lado: a aba atual fica sempre visível.
  useEffect(() => {
    const active = nav.current?.querySelector<HTMLElement>('[aria-current="page"]');
    const strip = nav.current;
    if (!active || !strip || strip.scrollWidth <= strip.clientWidth) return;
    strip.scrollTo({ left: active.offsetLeft - (strip.clientWidth - active.offsetWidth) / 2 });
  }, [pathname]);

  return (
    <nav ref={nav} aria-label="Seções da conta" className="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:sticky lg:top-24 lg:mx-0 lg:self-start lg:overflow-visible lg:px-0">
      <ul className="flex gap-2 after:w-2 after:shrink-0 after:content-[''] lg:flex-col lg:gap-1 lg:after:hidden">
        {accountSections.map(({ path, label, icon: Icon }) => {
          const href = accountHref(slug, path);
          const active = pathname === href;
          return (
            <li key={path} className="shrink-0">
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={`flex min-h-11 items-center gap-2.5 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-store-primary lg:rounded-field lg:border-transparent lg:px-3 ${
                  active ? "border-store-primary bg-store-primary text-store-on-primary" : "border-store-border bg-store-surface hover:border-store-primary lg:bg-transparent lg:hover:bg-store-surface"
                }`}
              >
                <Icon aria-hidden className="size-4 shrink-0" />
                <span className="flex-1">{label}</span>
                {path === "arrematacoes" && awardsCount > 0 ? (
                  <span className={`rounded-full px-1.5 text-xs font-bold tabular ${active ? "bg-store-on-primary text-store-primary" : "bg-store-primary text-store-on-primary"}`}>{awardsCount}</span>
                ) : null}
              </Link>
            </li>
          );
        })}
        <li className="mt-2 hidden border-t border-store-border pt-2 lg:block">
          <form action={logoutBidder}>
            <input type="hidden" name="slug" value={slug} />
            <button type="submit" className="flex min-h-11 w-full items-center gap-2.5 rounded-field px-3 text-sm font-medium text-store-text-muted hover:bg-store-surface hover:text-state-outbid focus-visible:outline-2 focus-visible:outline-store-primary">
              <LogOut aria-hidden className="size-4" />
              Sair da conta
            </button>
          </form>
        </li>
      </ul>
    </nav>
  );
}
