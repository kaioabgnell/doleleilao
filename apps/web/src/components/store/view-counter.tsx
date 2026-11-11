"use client";

import { Eye } from "lucide-react";
import { useEffect, useState } from "react";

/** Registra a visualização (RF-C04) e mostra o total atualizado. */
export function ViewCounter({ slug, productSlug, initial }: { slug: string; productSlug: string; initial: number }) {
  const [views, setViews] = useState(initial);

  useEffect(() => {
    const controller = new AbortController();
    fetch(`/${slug}/api/visualizacao/${productSlug}`, { method: "POST", signal: controller.signal })
      .then((r) => r.json())
      .then((data: { views: number | null }) => data.views !== null && setViews(data.views))
      .catch(() => undefined);
    return () => controller.abort();
  }, [slug, productSlug]);

  return (
    <span className="flex items-center gap-1.5 text-sm text-store-text-muted tabular">
      <Eye aria-hidden className="size-4" />
      {views} {views === 1 ? "visualização" : "visualizações"}
    </span>
  );
}
