import type { ReactNode } from "react";

/**
 * Avatar do participante (D-38): foto, ou um dos 12 símbolos neutros da galeria (sem gênero ou idade),
 * ou as iniciais. O fundo usa a cor primária da loja.
 */
const glyphs: Record<string, ReactNode> = {
  "avatar-01": (
    <g stroke="currentColor" strokeWidth="3" strokeLinecap="round">
      <circle cx="32" cy="32" r="8" fill="currentColor" stroke="none" />
      {Array.from({ length: 8 }, (_, i) => {
        const a = (i * Math.PI) / 4;
        return <line key={i} x1={Math.round((32 + 13 * Math.cos(a)) * 100) / 100} y1={Math.round((32 + 13 * Math.sin(a)) * 100) / 100} x2={Math.round((32 + 18 * Math.cos(a)) * 100) / 100} y2={Math.round((32 + 18 * Math.sin(a)) * 100) / 100} />;
      })}
    </g>
  ),
  "avatar-02": <path d="M38 16a16 16 0 1 0 10 28A13 13 0 0 1 38 16z" fill="currentColor" />,
  "avatar-03": <path d="M32 14l5.3 11.2 12.2 1.6-9 8.4 2.3 12.1L32 41.4l-10.8 5.9 2.3-12.1-9-8.4 12.2-1.6z" fill="currentColor" />,
  "avatar-04": <g fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round"><path d="M18 46C18 28 30 18 46 18c0 16-10 28-28 28z" fill="currentColor" stroke="none" /><path d="M20 44l18-18" className="opacity-40" /></g>,
  "avatar-05": <path d="M12 46l13-20 8 11 6-8 13 17z" fill="currentColor" />,
  "avatar-06": <g fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round"><path d="M14 28c4-5 8-5 12 0s8 5 12 0 8-5 12 0" /><path d="M14 40c4-5 8-5 12 0s8 5 12 0 8-5 12 0" /></g>,
  "avatar-07": <path d="M32 14c8 11 13 18 13 25a13 13 0 0 1-26 0c0-7 5-14 13-25z" fill="currentColor" />,
  "avatar-08": <g fill="currentColor">{[0, 72, 144, 216, 288].map((d) => <circle key={d} cx={Math.round((32 + 9 * Math.cos((d * Math.PI) / 180)) * 100) / 100} cy={Math.round((32 + 9 * Math.sin((d * Math.PI) / 180)) * 100) / 100} r="7" />)}<circle cx="32" cy="32" r="4" className="opacity-60" /></g>,
  "avatar-09": <path d="M35 12L18 36h12l-3 16 17-24H32z" fill="currentColor" />,
  "avatar-10": <g fill="none" stroke="currentColor" strokeWidth="3" strokeLinejoin="round"><path d="M20 24h24l6 8-18 18-18-18z" fill="currentColor" stroke="none" /><path d="M14 32h36M26 24l6 26 6-26" className="opacity-40" /></g>,
  "avatar-11": <path d="M22 44h22a10 10 0 0 0 0-20 13 13 0 0 0-25 3 9 9 0 0 0 3 17z" fill="currentColor" />,
  "avatar-12": <path d="M32 12l4 16 16 4-16 4-4 16-4-16-16-4 16-4z" fill="currentColor" />,
};

export const AVATAR_KEYS = Object.keys(glyphs);

export function AvatarGlyph({ avatarKey, className = "size-10" }: { avatarKey: string; className?: string }) {
  return (
    <svg viewBox="0 0 64 64" aria-hidden className={`${className} rounded-full`}>
      <circle cx="32" cy="32" r="32" fill="var(--store-primary)" />
      <g className="text-store-on-primary" color="var(--store-on-primary)">{glyphs[avatarKey]}</g>
    </svg>
  );
}

export function BidderAvatar({
  name,
  photoUrl,
  avatarKey,
  className = "size-10",
}: {
  name: string;
  photoUrl: string | null;
  avatarKey: string | null;
  className?: string;
}) {
  if (photoUrl) {
    // eslint-disable-next-line @next/next/no-img-element -- foto já processada (WebP 512 px)
    return <img src={photoUrl} alt="" className={`${className} rounded-full object-cover`} />;
  }
  if (avatarKey && glyphs[avatarKey]) return <AvatarGlyph avatarKey={avatarKey} className={className} />;
  const initials = name.split(" ").filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
  return (
    <span aria-hidden className={`${className} flex items-center justify-center rounded-full bg-store-primary text-sm font-semibold text-store-on-primary`}>
      {initials}
    </span>
  );
}
