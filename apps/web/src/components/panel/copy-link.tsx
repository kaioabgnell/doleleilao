"use client";

import { useState } from "react";
import { buttonClass } from "@/components/ui/button-styles";

/** Link da loja com botão de copiar. */
export function CopyLink({ url }: { url: string }) {
  const [copied, setCopied] = useState(false);
  const label = url.replace(/^https?:\/\//, "");

  async function copy() {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  }

  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
      <a
        href={url}
        target="_blank"
        rel="noreferrer"
        className="min-h-11 truncate rounded-field border border-dl-line bg-dl-page px-3 py-2.5 text-sm font-medium text-dl-ink hover:border-dl-copper sm:flex-1"
      >
        {label}
      </a>
      <button type="button" onClick={copy} className={buttonClass("secondary")}>
        <span aria-live="polite">{copied ? "Copiado" : "Copiar link"}</span>
      </button>
    </div>
  );
}
