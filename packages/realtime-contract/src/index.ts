/**
 * Contrato do tempo real (specs/06-api-e-tempo-real.md#eventos). O servidor é a fonte da verdade:
 * o lance entra por HTTP e os eventos abaixo chegam pelo WebSocket (Reverb, protocolo Pusher).
 * Emitidos por `apps/api/src/Auction/Infrastructure/BroadcastAuctionEvents.php`.
 *
 * Datas em ISO 8601 UTC com milissegundos. Valores em centavos. `sequence` cresce por item: o
 * cliente descarta eventos com sequência menor ou igual à última aplicada e, se detectar um salto,
 * busca o estado de novo (`GET /storefronts/{slug}/auctions/{auction}/state`).
 */

export const channels = {
  /** Público: pregão do lote (itens, lances, cronômetro). */
  auction: (auctionId: string) => `auction.${auctionId}`,
  /** Privado: avisos só do participante ("superado", "automático esgotado", "você venceu", moderação). */
  bidder: (bidderId: string) => `private-bidder.${bidderId}`,
  /** Chat do lote: público, ou privado quando o leiloeiro escolheu "só logados" (RF-D03). */
  chat: (auctionId: string, loggedInOnly: boolean) => `${loggedInOnly ? "private-" : ""}auction.${auctionId}.chat`,
} as const;

export type BidderRef = { id: string; nickname: string };

export type ItemResult = "closed_sold" | "closed_unsold" | "skipped";

export type AuctionChannelEvents = {
  "item.opened": {
    auction_id: string;
    item_id: string;
    starting_price_cents: number;
    increment_cents: number;
    next_minimum_cents: number;
    ends_at: string;
    sequence: number;
    server_now: string;
  };
  "bid.accepted": {
    auction_id: string;
    item_id: string;
    amount_cents: number;
    bidder: BidderRef;
    type: "manual" | "auto";
    placed_at: string;
    next_minimum_cents: number;
    bids_count: number;
    ends_at: string;
    /** O lance caiu na janela final e empurrou o fim (regra 7). */
    extended: boolean;
    sequence: number;
    server_now: string;
  };
  "item.paused": { auction_id: string; item_id: string; remaining_ms: number; sequence: number; server_now: string };
  "item.resumed": { auction_id: string; item_id: string; ends_at: string; sequence: number; server_now: string };
  "item.closed": {
    auction_id: string;
    item_id: string;
    result: ItemResult;
    amount_cents: number | null;
    winner: BidderRef | null;
    sequence: number;
    server_now: string;
  };
  "bid.voided": {
    auction_id: string;
    item_id: string;
    current_price_cents: number | null;
    leader: BidderRef | null;
    next_minimum_cents: number;
    bids_count: number;
    sequence: number;
    server_now: string;
  };
  "auction.started": { auction_id: string; server_now: string };
  "auction.finished": { auction_id: string; server_now: string };
};

export type BidderChannelEvents = {
  "bidder.outbid": { item_id: string; new_price_cents: number };
  "bidder.autobid_exhausted": { item_id: string; max_amount_cents: number };
  /** Dispara os confetes e o "plim" (RF-F09, D-33). */
  "bidder.won": { item_id: string; award_id: string; amount_cents: number; title: string };
  /** Silenciado no chat de um lote (`until` nulo = até o fim do leilão). */
  "bidder.muted": { auction_id: string; until: string | null };
  "bidder.unmuted": { auction_id: string; until: null };
  /** Banido na loja (RF-G02): o auditório encerra a sessão na hora. */
  "bidder.banned": Record<string, never>;
};

/** Mensagem do chat (texto puro: o cliente escapa e não transforma links em links). */
export type ChatMessage = {
  id: string;
  body: string | null;
  deleted: boolean;
  created_at: string;
  author: { type: "bidder" | "auctioneer"; id: string | null; nickname: string; avatar_key: string | null; photo_url: string | null; name?: string };
  reply_to: { id: string; author: string; excerpt: string | null; deleted: boolean } | null;
};

export type ChatChannelEvents = {
  "chat.message": ChatMessage;
  "chat.message_deleted": { id: string };
};

export type AuctionEventName = keyof AuctionChannelEvents;
export type BidderEventName = keyof BidderChannelEvents;

export const auctionEvents = [
  "item.opened",
  "bid.accepted",
  "item.paused",
  "item.resumed",
  "item.closed",
  "bid.voided",
  "auction.started",
  "auction.finished",
] as const satisfies readonly AuctionEventName[];

export const bidderEvents = [
  "bidder.outbid",
  "bidder.autobid_exhausted",
  "bidder.won",
  "bidder.muted",
  "bidder.unmuted",
  "bidder.banned",
] as const satisfies readonly BidderEventName[];
