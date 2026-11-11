"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { ChevronDown, LogOut } from "lucide-react";
import { logoutBidder } from "@/app/[slug]/acoes";
import { accountHref, accountSections } from "./account-sections";
import { BidderAvatar } from "./bidder-avatar";

interface Props {
  slug: string;
  name: string;
  nickname: string;
  email: string;
  photoUrl: string | null;
  avatarKey: string | null;
}

/**
 * Menu da conta no topo da loja: a foto abre as páginas da "Minha conta" e o "Sair",
 * que antes ficava escondido dentro da página da conta.
 */
export function AccountMenu({ slug, name, nickname, email, photoUrl, avatarKey }: Props) {
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const button = useRef<HTMLButtonElement>(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) return;
    const onPointer = (e: PointerEvent) => !root.current?.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== "Escape") return;
      setOpen(false);
      button.current?.focus();
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <div ref={root} className="relative">
      <button
        ref={button}
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={`Minha conta (${nickname})`}
        className="flex min-h-11 items-center gap-2 rounded-full pr-2 pl-1 hover:bg-store-border/30 focus-visible:outline-2 focus-visible:outline-store-primary sm:pr-3"
      >
        <BidderAvatar name={name} photoUrl={photoUrl} avatarKey={avatarKey} className="size-9" />
        <span className="hidden max-w-32 truncate text-sm font-medium sm:inline">{nickname}</span>
        <ChevronDown aria-hidden className={`size-4 text-store-text-muted transition-transform duration-150 motion-reduce:transition-none ${open ? "rotate-180" : ""}`} />
      </button>

      {open ? (
        <div
          id={menuId}
          className="absolute top-full right-0 z-40 mt-2 w-[min(18rem,calc(100vw-1.5rem))] origin-top-right overflow-hidden rounded-card border border-store-border bg-store-surface text-store-text shadow-xl"
        >
          <div className="flex items-center gap-3 border-b border-store-border px-4 py-3">
            <BidderAvatar name={name} photoUrl={photoUrl} avatarKey={avatarKey} className="size-10 shrink-0" />
            <div className="min-w-0">
              <p className="truncate font-semibold">{name}</p>
              <p className="truncate text-sm text-store-text-muted">{email}</p>
            </div>
          </div>
          <nav aria-label="Minha conta" className="p-1.5">
            <ul className="flex flex-col">
              {accountSections.map(({ path, label, icon: Icon }) => (
                <li key={path}>
                  <Link
                    href={accountHref(slug, path)}
                    onClick={() => setOpen(false)}
                    className="flex min-h-11 items-center gap-3 rounded-field px-3 text-sm font-medium hover:bg-store-bg focus-visible:outline-2 focus-visible:outline-store-primary"
                  >
                    <Icon aria-hidden className="size-4 text-store-text-muted" />
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
          <form action={logoutBidder} className="border-t border-store-border p-1.5">
            <input type="hidden" name="slug" value={slug} />
            <button
              type="submit"
              className="flex min-h-11 w-full items-center gap-3 rounded-field px-3 text-left text-sm font-semibold text-state-outbid hover:bg-state-outbid/10 focus-visible:outline-2 focus-visible:outline-state-outbid"
            >
              <LogOut aria-hidden className="size-4" />
              Sair da conta
            </button>
          </form>
        </div>
      ) : null}
    </div>
  );
}
