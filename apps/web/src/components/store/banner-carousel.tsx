"use client";

import { useEffect, useRef, useState } from "react";
import type { StoreBanner } from "@/lib/api-types";

/**
 * Banners da loja (RF-B06): imagem do celular (4:5) e, a partir de 768 px, a do computador (3:1).
 * Rolagem com o dedo (scroll-snap) e marcadores; sem troca automática.
 */
export function BannerCarousel({ banners }: { banners: StoreBanner[] }) {
  const track = useRef<HTMLUListElement>(null);
  const [current, setCurrent] = useState(0);

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) setCurrent(Number((entry.target as HTMLElement).dataset.index));
        }
      },
      { root: el, threshold: 0.6 },
    );
    el.querySelectorAll("li").forEach((li) => observer.observe(li));
    return () => observer.disconnect();
  }, [banners]);

  function goTo(index: number) {
    track.current?.children[index]?.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "start" });
  }

  return (
    <section aria-roledescription="carrossel" aria-label="Destaques">
      <ul ref={track} className="flex snap-x snap-mandatory gap-3 overflow-x-auto rounded-card [scrollbar-width:none]">
        {banners.map((banner, index) => {
          const image = (
            <picture>
              {banner.image_desktop_url ? <source media="(min-width: 768px)" srcSet={banner.image_desktop_url} /> : null}
              { }
              <img
                src={banner.image_mobile_url}
                alt={banner.title}
                loading={index === 0 ? "eager" : "lazy"}
                className={`aspect-[4/5] w-full rounded-card object-cover ${banner.image_desktop_url ? "md:aspect-[3/1]" : "md:aspect-[16/9]"}`}
              />
            </picture>
          );
          return (
            <li key={banner.id} data-index={index} className="w-full shrink-0 snap-start" aria-roledescription="slide" aria-label={`${index + 1} de ${banners.length}`}>
              {banner.link_url ? <a href={banner.link_url}>{image}</a> : image}
            </li>
          );
        })}
      </ul>
      {banners.length > 1 ? (
        <div className="mt-3 flex justify-center gap-1">
          {banners.map((banner, index) => (
            <button
              key={banner.id}
              type="button"
              onClick={() => goTo(index)}
              aria-label={`Ir para o destaque ${index + 1}`}
              aria-current={current === index}
              className="flex size-11 items-center justify-center"
            >
              <span className={`block h-2 rounded-full transition-all ${current === index ? "w-6 bg-store-primary" : "w-2 bg-store-border"}`} />
            </button>
          ))}
        </div>
      ) : null}
    </section>
  );
}
