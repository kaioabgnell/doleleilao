"use client";

import { Heart } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";

/**
 * Favoritar (RF-E05). Sem login, leva para entrar levando o pedido junto (aplicado depois do login).
 */
export function FavoriteButton({
  slug,
  productId,
  initial,
  loggedIn,
  className = "",
}: {
  slug: string;
  productId: string;
  initial: boolean;
  loggedIn: boolean;
  className?: string;
}) {
  const router = useRouter();
  const [favorite, setFavorite] = useState(initial);
  const [busy, setBusy] = useState(false);

  async function toggle() {
    if (!loggedIn) {
      const voltar = window.location.pathname + window.location.search;
      router.push(`/${slug}/entrar?${new URLSearchParams({ voltar, favoritar: productId })}`);
      return;
    }
    setBusy(true);
    const next = !favorite;
    setFavorite(next);
    const res = await fetch(`/${slug}/api/conta/favorites/${productId}`, { method: next ? "PUT" : "DELETE" }).catch(() => null);
    if (!res?.ok) setFavorite(!next);
    setBusy(false);
  }

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={busy}
      aria-pressed={favorite}
      aria-label={favorite ? "Tirar dos favoritos" : "Favoritar"}
      title={favorite ? "Tirar dos favoritos" : "Favoritar"}
      className={`flex size-11 items-center justify-center rounded-full bg-store-surface/90 text-store-text shadow-sm transition-transform active:scale-90 focus-visible:outline-2 focus-visible:outline-store-primary ${className}`}
    >
      <Heart aria-hidden className={`size-5 ${favorite ? "fill-state-outbid text-state-outbid" : ""}`} />
    </button>
  );
}
