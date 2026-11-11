/**
 * Roteiro da demonstração da tela de entrada (D-53): um item do lote, do lance pelo celular até a
 * arrematação, com os rótulos das telas reais (painel do pregão e auditório).
 *
 * Tudo é derivado do tempo `t` (ms dentro do ciclo), sem estado acumulado: o mesmo `t` sempre desenha
 * o mesmo quadro, e o servidor desenha o quadro final (`FINAL_MS`), que é também o que aparece com
 * "reduzir movimento".
 */

export const CYCLE_MS = 16_500;
/** Quadro final: item vendido, celular com "Você é o ganhador!", aviso no sino. */
export const FINAL_MS = 15_000;
/** Perto do fim do ciclo o quadro escurece um pouco e é reescrito (nunca fica vazio). */
export const DIM_FROM_MS = 16_150;

export const ITEM = {
  title: "Relógio de pulso bicolor",
  position: "Item 1 de 8",
  photo: "/assets/img/photo-f131189124-500.webp",
  thumb: "/assets/img/photo-f131189124-240.webp",
};

/** Quem está com o celular na demonstração. */
export const PHONE_USER = "marina.s";
const INCREMENT_CENTS = 5_000;
const AUTO_MAX_CENTS = 195_000;
const AUTO_BAR_FROM_CENTS = 170_000;
const BIDS_BEFORE = 10;
const START_SECONDS = 8;
const EXTENSION_SECONDS = 15;

export interface Bid {
  at: number;
  id: string;
  who: string;
  cents: number;
  auto?: boolean;
}

const BIDS: Bid[] = [
  { at: -4, id: "b11", who: "carla.m", cents: 170_000 },
  { at: -3, id: "b12", who: "marina.s", cents: 175_000 },
  { at: -2, id: "b13", who: "joao_relogios", cents: 180_000 },
  { at: -1, id: "b14", who: "carla.m", cents: 185_000, auto: true },
  { at: 1_600, id: "b15", who: "marina.s", cents: 190_000 },
  { at: 3_200, id: "b16", who: "carla.m", cents: 195_000, auto: true },
  { at: 6_200, id: "b17", who: "marina.s", cents: 200_000 },
];

export interface ChatMessage {
  at: number;
  id: string;
  who: string;
  text: string;
  staff?: boolean;
}

const CHAT: ChatMessage[] = [
  { at: -2, id: "m1", who: "ana.souza", text: "Eu quero esse!" },
  { at: -1, id: "m2", who: "Leiloeiro", text: "Peça revisada, com caixa.", staff: true },
  { at: 4_600, id: "m3", who: "marina.s", text: "Vou cobrir!" },
  { at: 13_800, id: "m4", who: "Leiloeiro", text: "Parabéns, marina.s!", staff: true },
];

/** Toques no botão "Dar lance" do celular (400 ms antes de o lance chegar ao painel). */
const TAPS = [1_200, 5_800];
const TAP_MS = 450;
/** Lances que chegam com menos de 15 s no relógio: o tempo volta para 15 s (prorrogação). */
const EXTENSIONS = [1_600, 3_200, 6_200];

/** O leiloeiro encerra o item pelo painel e confirma a venda. */
const CURSOR_IN = 7_600;
const PRESS_CLOSE = 8_500;
const CONFIRM_OPEN = 8_700;
const PRESS_CONFIRM = 9_200;
const CONFIRM_CLOSE = 9_500;
const CURSOR_OUT = 10_000;
/** "Dole 1", "Dole 2", "Dole 3" (1 s cada, como no auditório) e o resultado. */
const CLOSE_AT = 9_800;
const CALL_STEP_MS = 1_000;
const SOLD_AT = CLOSE_AT + 3 * CALL_STEP_MS;
const TOAST_AT = 13_400;

export type ItemStatus = "live" | "closing" | "sold";
export type CursorPhase = "hidden" | "toClose" | "pressClose" | "toConfirm" | "pressConfirm" | "leaving";
export type AutoBidState = "active" | "covered" | "exceeded";

export interface Frame {
  bids: Bid[];
  bidCount: number;
  priceCents: number;
  nextMinimumCents: number;
  leader: string;
  seconds: number;
  /** Quantas prorrogações já aconteceram (troca a chave do selo "Prorrogado" para ele reaparecer). */
  extensions: number;
  status: ItemStatus;
  /** 1, 2 ou 3 durante o fechamento; 0 fora dele. */
  call: 0 | 1 | 2 | 3;
  /** Toque em andamento no celular (o número do toque), ou null. */
  tap: number | null;
  cursor: CursorPhase;
  confirmOpen: boolean;
  chat: ChatMessage[];
  autoBid: AutoBidState;
  /** 0 a 1: quanto do lance automático de carla.m já foi usado. */
  autoBidUsed: number;
  toast: boolean;
}

function lastAtOrBefore(list: number[], t: number): number | undefined {
  let found: number | undefined;
  for (const at of list) if (at <= t) found = at;
  return found;
}

function secondsAt(t: number): number {
  if (t >= CLOSE_AT) return 0;
  const extendedAt = lastAtOrBefore(EXTENSIONS, t);
  const left = extendedAt === undefined ? START_SECONDS - Math.floor(t / 1000) : EXTENSION_SECONDS - Math.floor((t - extendedAt) / 1000);
  return Math.max(0, left);
}

function cursorAt(t: number): CursorPhase {
  if (t < CURSOR_IN || t >= CURSOR_OUT) return "hidden";
  if (t < PRESS_CLOSE) return "toClose";
  if (t < CONFIRM_OPEN) return "pressClose";
  if (t < PRESS_CONFIRM) return "toConfirm";
  if (t < CONFIRM_CLOSE) return "pressConfirm";
  return "leaving";
}

export function frameAt(t: number): Frame {
  const bids = BIDS.filter((b) => b.at <= t).reverse();
  const top = bids[0]!;
  const status: ItemStatus = t >= SOLD_AT ? "sold" : t >= CLOSE_AT ? "closing" : "live";
  const tapAt = TAPS.findIndex((at) => t >= at && t < at + TAP_MS);
  const autoBid: AutoBidState = top.cents > AUTO_MAX_CENTS ? "exceeded" : t >= 3_200 ? "covered" : "active";

  return {
    // Uma linha a mais que a área visível: é ela que desliza para fora quando chega um lance novo.
    bids: bids.slice(0, 5),
    bidCount: BIDS_BEFORE + bids.length,
    priceCents: top.cents,
    nextMinimumCents: top.cents + INCREMENT_CENTS,
    leader: top.who,
    seconds: secondsAt(t),
    extensions: EXTENSIONS.filter((at) => at <= t).length,
    status,
    call: status === "closing" ? (Math.min(3, Math.floor((t - CLOSE_AT) / CALL_STEP_MS) + 1) as 1 | 2 | 3) : 0,
    tap: tapAt === -1 ? null : tapAt + 1,
    cursor: cursorAt(t),
    confirmOpen: t >= CONFIRM_OPEN && t < CONFIRM_CLOSE,
    chat: CHAT.filter((m) => m.at <= t).slice(-3),
    autoBid,
    autoBidUsed: Math.min(1, (Math.min(top.cents, AUTO_MAX_CENTS) - AUTO_BAR_FROM_CENTS) / (AUTO_MAX_CENTS - AUTO_BAR_FROM_CENTS)),
    toast: t >= TOAST_AT,
  };
}

export const AUTO_BID = { who: "carla.m", maxCents: AUTO_MAX_CENTS };

/** "00:07" */
export function clock(seconds: number): string {
  return `00:${String(seconds).padStart(2, "0")}`;
}
