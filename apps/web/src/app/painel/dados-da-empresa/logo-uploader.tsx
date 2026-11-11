"use client";

import { ImageUp, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { buttonClass } from "@/components/ui/button-styles";
import { panelRequest } from "@/lib/panel-client";
import { useConfirm } from "@/components/ui/dialog";

const OUTPUT_WIDTH = 900;
const RATIO = 3; // logo horizontal 3:1, como aparece no topo da loja

interface Crop {
  src: string;
  image: HTMLImageElement;
  zoom: number;
  x: number;
  y: number;
}

/**
 * Logo da loja (RF-B02): SVG vai direto (a API limpa); PNG/JPG/WebP passa pelo corte e enquadramento
 * aqui no navegador e sobe já recortada. Sem logo, a loja mostra o nome de exibição (D-10).
 */
export function LogoUploader({ logoUrl, displayName }: { logoUrl: string | null; displayName: string }) {
  const ask = useConfirm();
  const router = useRouter();
  const input = useRef<HTMLInputElement>(null);
  const frame = useRef<HTMLDivElement>(null);
  const drag = useRef<{ x: number; y: number; startX: number; startY: number } | null>(null);
  const [crop, setCrop] = useState<Crop | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);

  const [frameWidth, setFrameWidth] = useState(600);
  const cropping = crop !== null;
  const cropSrc = crop?.src;

  useEffect(() => {
    return () => {
      if (cropSrc) URL.revokeObjectURL(cropSrc);
    };
  }, [cropSrc]);

  // Largura do quadro de corte (a imagem é posicionada em pixels de tela).
  useEffect(() => {
    const el = frame.current;
    if (!cropping || !el) return;
    const observer = new ResizeObserver(() => setFrameWidth(el.clientWidth));
    observer.observe(el);
    return () => observer.disconnect();
  }, [cropping]);

  async function upload(file: Blob, name: string) {
    setBusy(true);
    setMessage(null);
    const form = new FormData();
    form.append("logo", file, name);
    const result = await panelRequest<{ url: string }>("storefront/logo", { method: "POST", body: form });
    setBusy(false);
    if (!result.ok) {
      if (result.status === 401) router.refresh();
      setMessage({ tone: "error", text: result.errors?.logo?.[0] ?? result.message ?? "Não foi possível enviar." });
      return;
    }
    setCrop(null);
    setMessage({ tone: "success", text: "Logo atualizada." });
    router.refresh();
  }

  function choose(file: File | undefined) {
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      setMessage({ tone: "error", text: "A logo pode ter no máximo 2 MB." });
      return;
    }
    if (file.type === "image/svg+xml" || file.name.toLowerCase().endsWith(".svg")) {
      void upload(file, file.name);
      return;
    }
    const src = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => setCrop({ src, image, zoom: 1, x: 0, y: 0 });
    image.onerror = () => setMessage({ tone: "error", text: "Não conseguimos abrir esta imagem." });
    image.src = src;
  }

  /** Escala base: a imagem inteira cabe no quadro ("contain"). */
  function baseScale(c: Crop, width: number) {
    return Math.min(width / c.image.naturalWidth, width / RATIO / c.image.naturalHeight);
  }

  function onPointerDown(e: ReactPointerEvent<HTMLDivElement>) {
    if (!crop) return;
    e.currentTarget.setPointerCapture(e.pointerId);
    drag.current = { x: crop.x, y: crop.y, startX: e.clientX, startY: e.clientY };
  }

  function onPointerMove(e: ReactPointerEvent<HTMLDivElement>) {
    if (!crop || !drag.current) return;
    const d = drag.current;
    setCrop({ ...crop, x: d.x + e.clientX - d.startX, y: d.y + e.clientY - d.startY });
  }

  async function confirm() {
    if (!crop) return;
    const width = frameWidth;
    const scale = baseScale(crop, width) * crop.zoom;
    const w = crop.image.naturalWidth * scale;
    const h = crop.image.naturalHeight * scale;
    const left = (width - w) / 2 + crop.x;
    const top = (width / RATIO - h) / 2 + crop.y;
    const k = OUTPUT_WIDTH / width;

    const canvas = document.createElement("canvas");
    canvas.width = OUTPUT_WIDTH;
    canvas.height = OUTPUT_WIDTH / RATIO;
    canvas.getContext("2d")?.drawImage(crop.image, left * k, top * k, w * k, h * k);
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, "image/png"));
    if (blob) await upload(blob, "logo.png");
  }

  const scale = crop ? baseScale(crop, frameWidth) * crop.zoom : 1;

  return (
    <div className="flex flex-col gap-3">
      {message ? <Alert tone={message.tone} title={message.text} /> : null}

      {crop ? (
        <div className="flex flex-col gap-3">
          <p className="text-sm text-dl-ink-muted">Arraste para enquadrar e use o controle para aproximar.</p>
          <div
            ref={frame}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={() => (drag.current = null)}
            className="relative aspect-[3/1] w-full cursor-grab touch-none overflow-hidden rounded-field border-2 border-dashed border-dl-copper bg-[conic-gradient(var(--dl-ink-200)_25%,transparent_0_50%,var(--dl-ink-200)_0_75%,transparent_0)] bg-[length:16px_16px] active:cursor-grabbing"
          >
            {/* eslint-disable-next-line @next/next/no-img-element -- pré-visualização local (blob) */}
            <img
              src={crop.src}
              alt="Logo para recortar"
              draggable={false}
              className="pointer-events-none absolute top-1/2 left-1/2 max-w-none select-none"
              style={{
                width: crop.image.naturalWidth * scale,
                height: crop.image.naturalHeight * scale,
                transform: `translate(calc(-50% + ${crop.x}px), calc(-50% + ${crop.y}px))`,
              }}
            />
          </div>
          <label className="flex items-center gap-3 text-sm text-dl-ink">
            Zoom
            <input
              type="range"
              min={0.5}
              max={4}
              step={0.05}
              value={crop.zoom}
              onChange={(e) => setCrop({ ...crop, zoom: Number(e.target.value) })}
              className="min-h-11 flex-1 accent-dl-copper"
            />
          </label>
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={confirm} disabled={busy} className={buttonClass("primary")}>
              {busy ? "Enviando…" : "Usar esta logo"}
            </button>
            <button type="button" onClick={() => setCrop(null)} disabled={busy} className={buttonClass("secondary")}>
              Cancelar
            </button>
          </div>
        </div>
      ) : (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="flex h-20 items-center justify-center rounded-field border border-dl-line bg-dl-page px-4 sm:w-60">
            {logoUrl ? (
              // eslint-disable-next-line @next/next/no-img-element -- logo já processada pela API
              <img src={logoUrl} alt="Logo atual" className="max-h-14 max-w-full object-contain" />
            ) : (
              <span className="font-semibold text-dl-ink">{displayName}</span>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => input.current?.click()} disabled={busy} className={buttonClass("secondary")}>
              <ImageUp aria-hidden className="size-4" />
              {busy ? "Enviando…" : logoUrl ? "Trocar logo" : "Enviar logo"}
            </button>
            {logoUrl ? (
              <button
                type="button"
                disabled={busy}
                onClick={async () => {
                  const ok = await ask({
                    title: "Remover a logo?",
                    description: "A loja volta a mostrar o nome de exibição no lugar da logo.",
                    confirmLabel: "Remover logo",
                    tone: "danger",
                  });
                  if (!ok) return;
                  setBusy(true);
                  const result = await panelRequest("storefront/logo", { method: "DELETE" });
                  setBusy(false);
                  if (result.ok) router.refresh();
                  else setMessage({ tone: "error", text: result.message ?? "Não foi possível remover." });
                }}
                className={buttonClass("ghost")}
              >
                <Trash2 aria-hidden className="size-4" />
                Remover
              </button>
            ) : null}
          </div>
          <input
            ref={input}
            type="file"
            accept="image/svg+xml,image/png,image/jpeg,image/webp"
            className="sr-only"
            tabIndex={-1}
            aria-label="Arquivo da logo"
            onChange={(e) => {
              choose(e.target.files?.[0]);
              e.target.value = "";
            }}
          />
        </div>
      )}
      <p className="text-xs text-dl-ink-muted">SVG, PNG, JPG ou WebP, até 2 MB. Sem logo, a loja mostra “{displayName}”.</p>
    </div>
  );
}
