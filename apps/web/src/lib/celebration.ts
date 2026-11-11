/**
 * Tela do ganhador (RF-F09, D-33): confetes em canvas + "plim" curto sintetizado (sem arquivo de
 * áudio e sem locução). O áudio só toca depois do toque em "Entrar no auditório" (regra de autoplay).
 */

let audio: AudioContext | null = null;
const MUTE_KEY = "dl-auditorio-mudo";

/** Chamado no toque em "Entrar no auditório": libera o som para depois. */
export function unlockAudio(): void {
  try {
    const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctx) return;
    audio ??= new Ctx();
    void audio.resume();
  } catch {
    audio = null;
  }
}

export function isMuted(): boolean {
  try {
    return localStorage.getItem(MUTE_KEY) === "1";
  } catch {
    return false;
  }
}

export function setMuted(muted: boolean): void {
  try {
    localStorage.setItem(MUTE_KEY, muted ? "1" : "0");
  } catch {
    // preferência só desta visita
  }
}

/** "Plim": duas notas de sino (mi e si agudos) com decaimento rápido, ~0,9 s. */
export function playPlim(): void {
  if (!audio || isMuted()) return;
  const now = audio.currentTime;
  [
    { freq: 1318.5, at: 0 },
    { freq: 1975.5, at: 0.12 },
  ].forEach(({ freq, at }) => {
    const osc = audio!.createOscillator();
    const overtone = audio!.createOscillator();
    const gain = audio!.createGain();
    osc.type = "sine";
    overtone.type = "sine";
    osc.frequency.value = freq;
    overtone.frequency.value = freq * 2.01;
    gain.gain.setValueAtTime(0.0001, now + at);
    gain.gain.exponentialRampToValueAtTime(0.25, now + at + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + at + 0.8);
    osc.connect(gain);
    overtone.connect(gain);
    gain.connect(audio!.destination);
    osc.start(now + at);
    overtone.start(now + at);
    osc.stop(now + at + 0.85);
    overtone.stop(now + at + 0.85);
  });
}

/** Confetes por ~2,8 s num canvas por cima da tela. Com "reduzir movimento", não anima. */
export function fireConfetti(colors: string[]): void {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  const canvas = document.createElement("canvas");
  canvas.setAttribute("aria-hidden", "true");
  canvas.style.cssText = "position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:60";
  document.body.appendChild(canvas);
  const ctx = canvas.getContext("2d");
  if (!ctx) return canvas.remove();

  const ratio = Math.min(window.devicePixelRatio || 1, 2);
  const width = window.innerWidth;
  const height = window.innerHeight;
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  ctx.scale(ratio, ratio);

  const pieces = Array.from({ length: Math.min(160, Math.round(width / 3)) }, (_, i) => ({
    x: width / 2 + (Math.random() - 0.5) * width * 0.3,
    y: height * 0.35,
    vx: (Math.random() - 0.5) * 14,
    vy: -Math.random() * 14 - 6,
    size: 5 + Math.random() * 6,
    rotation: Math.random() * Math.PI,
    spin: (Math.random() - 0.5) * 0.3,
    color: colors[i % colors.length],
  }));

  const started = performance.now();
  const frame = (time: number) => {
    const elapsed = time - started;
    ctx.clearRect(0, 0, width, height);
    for (const p of pieces) {
      p.vy += 0.35;
      p.vx *= 0.99;
      p.x += p.vx;
      p.y += p.vy;
      p.rotation += p.spin;
      ctx.save();
      ctx.globalAlpha = Math.max(0, 1 - elapsed / 2800);
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rotation);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
      ctx.restore();
    }
    if (elapsed < 2800) requestAnimationFrame(frame);
    else canvas.remove();
  };
  requestAnimationFrame(frame);
}
