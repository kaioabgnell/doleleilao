/** Datas dos leilões: guardadas em UTC, mostradas e digitadas no horário de Brasília (sem horário de verão). */

const TIME_ZONE = "America/Sao_Paulo";
const OFFSET = "-03:00";

const dayFormat = new Intl.DateTimeFormat("pt-BR", { timeZone: TIME_ZONE, weekday: "short", day: "numeric", month: "short" });
const parts = new Intl.DateTimeFormat("en-CA", {
  timeZone: TIME_ZONE,
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  hourCycle: "h23",
});

/** "20h" ou "19h30". */
export function formatHour(iso: string): string {
  const { time } = toLocalInputs(iso);
  const [h, m] = time.split(":");
  return m === "00" ? `${Number(h)}h` : `${Number(h)}h${m}`;
}

/** "sáb., 21 de nov. · 20h" */
export function formatAuctionDate(iso: string): string {
  return `${dayFormat.format(new Date(iso))} · ${formatHour(iso)}`;
}

/** "hoje às 20h", "amanhã às 20h" ou "sáb., 21 de nov. às 20h". */
export function formatRelativeStart(iso: string, now = new Date()): string {
  const day = toLocalInputs(iso).date;
  const today = toLocalInputs(now.toISOString()).date;
  const tomorrow = toLocalInputs(new Date(now.getTime() + 86_400_000).toISOString()).date;
  const when = day === today ? "hoje" : day === tomorrow ? "amanhã" : dayFormat.format(new Date(iso));
  return `${when} às ${formatHour(iso)}`;
}

/** UTC → campos `date` (AAAA-MM-DD) e `time` (HH:MM) no horário de Brasília. */
export function toLocalInputs(iso: string): { date: string; time: string } {
  const p = Object.fromEntries(parts.formatToParts(new Date(iso)).map((x) => [x.type, x.value]));
  return { date: `${p.year}-${p.month}-${p.day}`, time: `${p.hour}:${p.minute}` };
}

/** Campos do formulário (Brasília) → ISO com fuso, que a API converte para UTC. */
export function fromLocalInputs(date: string, time: string): string {
  return `${date}T${time}:00${OFFSET}`;
}

/** "01:05" (mm:ss) ou "1:02:05" acima de uma hora. */
export function formatRemaining(ms: number): string {
  const total = Math.max(0, Math.ceil(ms / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  const mm = String(m).padStart(h > 0 ? 2 : 2, "0");
  return h > 0 ? `${h}:${mm}:${String(s).padStart(2, "0")}` : `${mm}:${String(s).padStart(2, "0")}`;
}
