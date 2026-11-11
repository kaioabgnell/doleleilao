import type { TemplateKey } from "@doleleilao/design-tokens";
import type { ReactNode } from "react";

/**
 * Ilustrações da pré-visualização (uma família por modelo de loja). Desenhadas em SVG com as
 * variáveis da loja (--store-*), então acompanham as cores e o fundo escolhidos, inclusive o Ébano.
 */

const ink = "var(--store-text)";
const muted = "var(--store-text-muted)";
const line = "var(--store-border)";
const surface = "var(--store-surface)";
const primary = "var(--store-primary)";
const accent = "var(--store-accent)";

/** Arredonda coordenadas calculadas: servidor e navegador geram exatamente o mesmo SVG (hidratação). */
const r2 = (n: number) => Math.round(n * 100) / 100;

function Svg({ children, label }: { children: ReactNode; label: string }) {
  return (
    <svg viewBox="0 0 120 120" role="img" aria-label={label} className="size-full" fill="none">
      {children}
    </svg>
  );
}

/* ---------- Relógios ---------- */

function WristWatch() {
  const indices = Array.from({ length: 12 }, (_, i) => {
    const a = (i * Math.PI) / 6;
    const major = i % 3 === 0;
    const r1 = major ? 14.5 : 16.5;
    return (
      <line
        key={i}
        x1={r2(60 + r1 * Math.sin(a))}
        y1={r2(60 - r1 * Math.cos(a))}
        x2={r2(60 + 19 * Math.sin(a))}
        y2={r2(60 - 19 * Math.cos(a))}
        stroke={major ? ink : muted}
        strokeWidth={major ? 2 : 1}
        strokeLinecap="round"
      />
    );
  });

  return (
    <Svg label="Relógio de pulso">
      {/* pulseira de couro */}
      <path d="M46 8h28l-2 26H48z" fill={primary} />
      <path d="M48 86h24l2 26H46z" fill={primary} />
      <path d="M50 12v18M70 12v18M50 90v18M70 90v18" stroke={surface} strokeOpacity=".45" strokeWidth=".8" strokeDasharray="2 2" />
      <rect x="55" y="98" width="10" height="4" rx="1" fill={surface} fillOpacity=".55" />
      {/* caixa, luneta e mostrador */}
      <rect x="85" y="55.5" width="6" height="9" rx="1.6" fill={muted} />
      <circle cx="60" cy="60" r="29" fill={muted} />
      <circle cx="60" cy="60" r="25.5" fill={ink} fillOpacity=".12" />
      <circle cx="60" cy="60" r="23" fill={surface} />
      <circle cx="60" cy="60" r="23" stroke={line} />
      {indices}
      {/* data */}
      <rect x="70" y="56.5" width="7" height="7" rx="1" fill={surface} stroke={muted} strokeWidth=".8" />
      {/* ponteiros: 10h10 */}
      <line x1="60" y1="60" x2="49.5" y2="52" stroke={ink} strokeWidth="2.6" strokeLinecap="round" />
      <line x1="60" y1="60" x2="74" y2="47" stroke={ink} strokeWidth="1.8" strokeLinecap="round" />
      <line x1="60" y1="66" x2="60" y2="41" stroke={accent} strokeWidth=".9" strokeLinecap="round" />
      <circle cx="60" cy="60" r="2.2" fill={accent} />
    </Svg>
  );
}

function PocketWatch() {
  return (
    <Svg label="Relógio de bolso">
      {/* corrente */}
      <path d="M60 22c-10-6-22-4-30 4s-10 20-4 30" stroke={accent} strokeWidth="2" strokeDasharray="3 2.4" strokeLinecap="round" />
      <circle cx="27" cy="59" r="3.5" stroke={accent} strokeWidth="2" />
      {/* argola e coroa */}
      <circle cx="60" cy="17" r="6" stroke={muted} strokeWidth="2.5" />
      <rect x="55.5" y="22" width="9" height="8" rx="2" fill={muted} />
      {/* caixa e mostrador */}
      <circle cx="60" cy="68" r="34" fill={primary} />
      <circle cx="60" cy="68" r="29.5" fill={surface} />
      <circle cx="60" cy="68" r="27" stroke={line} />
      {["XII", "III", "VI", "IX"].map((n, i) => {
        const a = (i * Math.PI) / 2;
        return (
          <text
            key={n}
            x={r2(60 + 21 * Math.sin(a))}
            y={r2(68 - 21 * Math.cos(a) + 2.6)}
            textAnchor="middle"
            fontSize="7"
            fontFamily="Georgia, serif"
            fill={ink}
          >
            {n}
          </text>
        );
      })}
      {/* pequenos segundos */}
      <circle cx="60" cy="79" r="6" stroke={muted} strokeWidth=".9" />
      <line x1="60" y1="79" x2="62.5" y2="75" stroke={accent} strokeWidth=".9" strokeLinecap="round" />
      {/* ponteiros */}
      <path d="M60 68l-1.4-15h2.8z" fill={ink} />
      <path d="M60 68l12-6" stroke={ink} strokeWidth="2.2" strokeLinecap="round" />
      <circle cx="60" cy="68" r="2" fill={ink} />
    </Svg>
  );
}

/* ---------- Roupa ---------- */

function Blazer() {
  return (
    <Svg label="Blazer no cabide">
      {/* cabide */}
      <path d="M60 22v-4a5 5 0 1 0-5-5" stroke={muted} strokeWidth="2" strokeLinecap="round" />
      <path d="M60 22L24 38h72z" stroke={muted} strokeWidth="2" strokeLinejoin="round" />
      {/* blazer */}
      <path
        d="M46 30l14 8 14-8 20 10 6 50-12 4-2-30-2 50H36l-2-50-2 30-12-4 6-50z"
        fill={primary}
      />
      {/* lapelas e abertura */}
      <path d="M46 30l14 30 14-30" stroke={surface} strokeOpacity=".35" strokeWidth="1.4" strokeLinejoin="round" />
      <path d="M52 34l8 26M68 34l-8 26" stroke={ink} strokeOpacity=".18" strokeWidth="5" />
      <path d="M60 38v74" stroke={ink} strokeOpacity=".25" strokeWidth="1" />
      {/* camisa e botões */}
      <path d="M54 34l6 4 6-4-6 26z" fill={surface} />
      <circle cx="62.5" cy="74" r="1.8" fill={accent} />
      <circle cx="62.5" cy="86" r="1.8" fill={accent} />
      {/* bolsos */}
      <path d="M38 84h12M70 84h12M72 52h9" stroke={surface} strokeOpacity=".4" strokeWidth="1.4" strokeLinecap="round" />
    </Svg>
  );
}

function Handbag() {
  return (
    <Svg label="Bolsa">
      {/* alça */}
      <path d="M40 46c0-22 40-22 40 0" stroke={ink} strokeOpacity=".75" strokeWidth="4" strokeLinecap="round" />
      <rect x="35" y="42" width="8" height="8" rx="2" fill={muted} />
      <rect x="77" y="42" width="8" height="8" rx="2" fill={muted} />
      {/* corpo */}
      <path d="M26 50h68l8 52a6 6 0 0 1-6 7H24a6 6 0 0 1-6-7z" fill={primary} />
      {/* aba e fecho */}
      <path d="M26 50h68l-4 24H30z" fill={ink} fillOpacity=".14" />
      <path d="M30 74h60" stroke={surface} strokeOpacity=".35" strokeWidth="1" strokeDasharray="2 2" />
      <rect x="54" y="68" width="12" height="11" rx="2.5" fill={accent} />
      <rect x="57.5" y="71.5" width="5" height="4" rx="1" fill={surface} fillOpacity=".6" />
      {/* costura */}
      <path d="M23 100h74" stroke={surface} strokeOpacity=".3" strokeWidth="1" strokeDasharray="2 2" />
    </Svg>
  );
}

/* ---------- Eletrônicos ---------- */

function Smartphone() {
  return (
    <Svg label="Smartphone">
      <rect x="36" y="10" width="48" height="100" rx="10" fill={ink} fillOpacity=".9" />
      <rect x="39.5" y="13.5" width="41" height="93" rx="7" fill={primary} />
      {/* tela: papel de parede em ondas */}
      <path d="M39.5 70c10-8 22-8 41 0v29.5a7 7 0 0 1-7 7h-27a7 7 0 0 1-7-7z" fill={accent} fillOpacity=".85" />
      <path d="M39.5 82c14-9 26-6 41 2" stroke={surface} strokeOpacity=".35" strokeWidth="1.2" />
      {/* ilha da câmera e relógio */}
      <rect x="52" y="17" width="16" height="5" rx="2.5" fill={ink} />
      <text x="60" y="42" textAnchor="middle" fontSize="11" fontWeight="600" fill={surface} fillOpacity=".95">
        9:41
      </text>
      <rect x="48" y="47" width="24" height="2" rx="1" fill={surface} fillOpacity=".55" />
      {/* barra inferior */}
      <rect x="52" y="100" width="16" height="2" rx="1" fill={surface} fillOpacity=".8" />
      {/* botões laterais */}
      <rect x="84" y="34" width="2" height="14" rx="1" fill={muted} />
      <rect x="34" y="30" width="2" height="9" rx="1" fill={muted} />
      <rect x="34" y="42" width="2" height="9" rx="1" fill={muted} />
    </Svg>
  );
}

function Headphones() {
  return (
    <Svg label="Fone de ouvido">
      {/* arco */}
      <path d="M24 72V60a36 36 0 0 1 72 0v12" stroke={ink} strokeOpacity=".85" strokeWidth="7" strokeLinecap="round" />
      <path d="M30 60a30 30 0 0 1 60 0" stroke={primary} strokeWidth="3" strokeLinecap="round" />
      {/* conchas */}
      <rect x="14" y="64" width="24" height="38" rx="10" fill={primary} />
      <rect x="82" y="64" width="24" height="38" rx="10" fill={primary} />
      <rect x="30" y="68" width="10" height="30" rx="5" fill={ink} fillOpacity=".8" />
      <rect x="80" y="68" width="10" height="30" rx="5" fill={ink} fillOpacity=".8" />
      {/* detalhes */}
      <circle cx="26" cy="83" r="4" fill={accent} />
      <circle cx="94" cy="83" r="4" fill={accent} />
      <path d="M20 94h8M92 94h8" stroke={surface} strokeOpacity=".4" strokeWidth="1.2" strokeLinecap="round" />
    </Svg>
  );
}

export const templateIllustrations: Record<
  TemplateKey,
  { featured: () => ReactNode; next: () => ReactNode; items: [string, string] }
> = {
  watches: { featured: WristWatch, next: PocketWatch, items: ["Relógio automático", "Relógio de bolso"] },
  fashion: { featured: Blazer, next: Handbag, items: ["Blazer de lã", "Bolsa de couro"] },
  electronics: { featured: Smartphone, next: Headphones, items: ["Smartphone 256 GB", "Fone sem fio"] },
};
