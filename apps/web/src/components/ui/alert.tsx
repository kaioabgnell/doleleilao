import type { ReactNode } from "react";

type Tone = "info" | "success" | "error";

const tones: Record<Tone, string> = {
  info: "border-dl-line bg-dl-card text-dl-ink",
  success: "border-state-winning/30 bg-state-winning/8 text-dl-ink",
  error: "border-state-outbid/30 bg-state-outbid/8 text-dl-ink",
};

const markers: Record<Tone, string> = {
  info: "bg-dl-copper-icon",
  success: "bg-state-winning",
  error: "bg-state-outbid",
};

export function Alert({ tone = "info", title, children }: { tone?: Tone; title?: string; children?: ReactNode }) {
  return (
    <div role={tone === "error" ? "alert" : "status"} className={`flex gap-3 rounded-card border p-4 ${tones[tone]}`}>
      <span aria-hidden className={`mt-1.5 size-2 shrink-0 rounded-full ${markers[tone]}`} />
      <div className="flex flex-col gap-1 text-sm">
        {title ? <p className="font-semibold">{title}</p> : null}
        {children ? <div className="text-dl-ink-muted">{children}</div> : null}
      </div>
    </div>
  );
}
