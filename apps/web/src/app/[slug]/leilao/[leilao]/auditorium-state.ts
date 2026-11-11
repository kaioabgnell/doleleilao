import type { AuctionChannelEvents } from "@doleleilao/realtime-contract";
import type { AuctionItem, AuctionState, AuctionStatus, PublicAuction, RecentBid } from "@/lib/api-types";

/**
 * Estado do auditório alimentado pelos eventos (specs/06#eventos). Regras do contrato: evento com
 * `sequence` menor ou igual ao último aplicado no item é descartado; um salto de sequência marca
 * `needsSync`, e a tela busca o estado inteiro de novo.
 */
export interface AuditoriumState {
  status: AuctionStatus;
  items: AuctionItem[];
  currentItemId: string | null;
  recentBids: RecentBid[];
  /** Muda quando é preciso buscar o estado inteiro (salto de sequência, lance anulado). */
  needsSync: number;
  updatedAt: number;
}

export type AuditoriumAction =
  | { type: "sync"; state: AuctionState }
  | { type: "event"; name: string; data: unknown }
  | { type: "local"; itemId: string; patch: Partial<AuctionItem> };

export function initialState(data: PublicAuction): AuditoriumState {
  return {
    status: data.auction.status,
    items: data.auction.items ?? [],
    currentItemId: data.auction.current_item_id ?? runningItem(data.auction.items ?? [])?.id ?? null,
    recentBids: data.recent_bids,
    needsSync: 0,
    updatedAt: Date.now(),
  };
}

export function runningItem(items: AuctionItem[]): AuctionItem | undefined {
  return items.find((i) => i.status === "open" || i.status === "paused");
}

type Ev<K extends keyof AuctionChannelEvents> = AuctionChannelEvents[K];

export function reducer(state: AuditoriumState, action: AuditoriumAction): AuditoriumState {
  if (action.type === "sync") {
    // Os dados do produto não vêm no estado leve: mantém os que já temos.
    const products = new Map(state.items.map((i) => [i.id, i.product]));
    const items = action.state.items.map((i) => ({ ...i, product: i.product ?? products.get(i.id) ?? null }));
    return {
      ...state,
      status: action.state.status,
      items,
      currentItemId: action.state.current_item_id ?? runningItem(items)?.id ?? state.currentItemId,
      recentBids: action.state.recent_bids,
      updatedAt: Date.now(),
    };
  }

  if (action.type === "local") {
    return { ...state, items: state.items.map((i) => (i.id === action.itemId ? { ...i, ...action.patch } : i)) };
  }

  const now = Date.now();
  switch (action.name) {
    case "auction.started":
      return { ...state, status: "live", updatedAt: now };
    case "auction.finished":
      return { ...state, status: "finished", updatedAt: now };
  }

  const data = action.data as { item_id?: string; sequence?: number };
  if (!data?.item_id || typeof data.sequence !== "number") return state;
  const item = state.items.find((i) => i.id === data.item_id);
  if (!item) return { ...state, needsSync: state.needsSync + 1 };
  if (data.sequence <= item.sequence) return state;
  const gap = data.sequence > item.sequence + 1;

  let patch: Partial<AuctionItem> = { sequence: data.sequence };
  let next: Partial<AuditoriumState> = {};

  switch (action.name) {
    case "item.opened": {
      const e = data as Ev<"item.opened">;
      patch = { ...patch, status: "open", ends_at: e.ends_at, opened_at: e.server_now, next_minimum_cents: e.next_minimum_cents, paused_remaining_ms: null };
      next = { currentItemId: e.item_id, recentBids: [], status: "live" };
      break;
    }
    case "bid.accepted": {
      const e = data as Ev<"bid.accepted">;
      patch = {
        ...patch,
        current_price_cents: e.amount_cents,
        leader: e.bidder,
        next_minimum_cents: e.next_minimum_cents,
        bids_count: e.bids_count,
        ends_at: e.ends_at,
      };
      const bid: RecentBid = { id: `${e.item_id}-${e.sequence}`, amount_cents: e.amount_cents, type: e.type, placed_at: e.placed_at, bidder: e.bidder };
      if (state.currentItemId === e.item_id) next = { recentBids: [bid, ...state.recentBids].slice(0, 10) };
      break;
    }
    case "item.paused": {
      const e = data as Ev<"item.paused">;
      patch = { ...patch, status: "paused", paused_remaining_ms: e.remaining_ms, ends_at: null };
      break;
    }
    case "item.resumed": {
      const e = data as Ev<"item.resumed">;
      patch = { ...patch, status: "open", ends_at: e.ends_at, paused_remaining_ms: null };
      break;
    }
    case "item.closed": {
      const e = data as Ev<"item.closed">;
      patch = {
        ...patch,
        status: e.result,
        closed_at: e.server_now,
        ends_at: null,
        paused_remaining_ms: null,
        ...(e.result === "closed_sold" ? { current_price_cents: e.amount_cents, leader: e.winner } : {}),
      };
      break;
    }
    case "bid.voided": {
      const e = data as Ev<"bid.voided">;
      patch = { ...patch, current_price_cents: e.current_price_cents, leader: e.leader, next_minimum_cents: e.next_minimum_cents, bids_count: e.bids_count };
      // A lista de lances muda de forma não trivial: busca o estado.
      next = { needsSync: state.needsSync + 1 };
      break;
    }
    default:
      return state;
  }

  return {
    ...state,
    ...next,
    items: state.items.map((i) => (i.id === item.id ? { ...i, ...patch } : i)),
    needsSync: gap ? state.needsSync + 1 : (next.needsSync ?? state.needsSync),
    updatedAt: now,
  };
}
