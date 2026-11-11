"use client";

import { Play } from "lucide-react";
import { useState } from "react";

/**
 * Vídeo do produto com fachada leve (D-26, RF-C03): só a miniatura até a pessoa tocar.
 * O player do YouTube (youtube-nocookie) carrega depois do toque e não pesa no LCP.
 */
export function YouTubeFacade({ videoId, title }: { videoId: string; title: string }) {
  const [playing, setPlaying] = useState(false);

  if (playing) {
    return (
      <iframe
        src={`https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0`}
        title={`Vídeo: ${title}`}
        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
        allowFullScreen
        className="aspect-video w-full rounded-card"
      />
    );
  }

  return (
    <button
      type="button"
      onClick={() => setPlaying(true)}
      aria-label={`Assistir ao vídeo de ${title}`}
      className="group relative block aspect-video w-full overflow-hidden rounded-card bg-store-text focus-visible:outline-2 focus-visible:outline-store-primary"
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- miniatura do YouTube */}
      <img src={`https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`} alt="" loading="lazy" className="size-full object-cover opacity-90 transition-opacity group-hover:opacity-100" />
      <span className="absolute inset-0 m-auto flex size-16 items-center justify-center rounded-full bg-store-primary text-store-on-primary shadow-lg">
        <Play aria-hidden className="ml-1 size-7 fill-current" />
      </span>
    </button>
  );
}
