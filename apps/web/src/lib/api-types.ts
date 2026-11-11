/** Formato das respostas da API (/api/v1). Fonte da verdade: OpenAPI em http://localhost:8000/docs/api. */

export type FieldErrors = Record<string, string[]>;

export interface Problem {
  title: string;
  status: number;
  detail?: string;
  errors?: FieldErrors;
}

export interface AuctioneerUser {
  id: string;
  name: string;
  email: string;
  phone: string;
  role: "owner";
  email_verified: boolean;
}

export interface Auctioneer {
  id: string;
  company_name: string;
  display_name: string;
  slug: string;
  store_url: string;
  document_type: "cpf" | "cnpj";
  document_masked: string;
  status: "pending" | "active" | "suspended";
  slug_changed_at: string | null;
  next_slug_change_at: string | null;
}

export interface Me {
  user: AuctioneerUser;
  auctioneer: Auctioneer;
}

export interface SessionResponse extends Me {
  token: string;
  expires_at: string;
}

export type OnboardingStepKey = "company" | "products" | "payment" | "auction";
export type CompanyMissing = "email" | "contact_phone" | "template";

export interface OnboardingStep {
  key: OnboardingStepKey;
  done: boolean;
  available: boolean;
  blocked: boolean;
  /** O que falta antes deste passo: o leilão pede produto e cartão (D-55). */
  blocked_by: OnboardingStepKey[];
  missing: CompanyMissing[];
}

export interface Onboarding {
  visible: boolean;
  completed: boolean;
  done_count: number;
  total: number;
  next: OnboardingStepKey | null;
  store_url: string;
  steps: OnboardingStep[];
}

export type SocialKey = "instagram" | "facebook" | "tiktok" | "youtube" | "website";

export interface Storefront {
  template: "watches" | "fashion" | "electronics" | null;
  primary_color: string | null;
  accent_color: string | null;
  background: string | null;
  heading_font: string | null;
  logo: { kind: "svg" | "png" | "none"; url: string | null };
  display_name: string;
  contact_phone: string | null;
  contact_email: string | null;
  socials: Partial<Record<SocialKey, string>>;
  /** De onde a loja envia os produtos. Só no painel. */
  address: Address | null;
  store_url: string;
  is_public: boolean;
}

/** Endereço para envio de produtos (loja e participante). `postal_code` vem com hífen: 01310-100. */
export interface Address {
  postal_code: string;
  street: string;
  number: string;
  complement: string | null;
  district: string;
  city: string;
  state: string;
}

/** GET /postal-codes/{cep}. CEP geral de cidade vem com `street` e `district` vazios. */
export type PostalCodeResult = Pick<Address, "postal_code" | "street" | "district" | "city" | "state">;

export interface SlugAvailability {
  slug: string;
  available: boolean;
  reason: "invalid" | "reserved" | "taken" | null;
  message: string | null;
  store_url: string;
}

export interface Suggestion {
  slug: string | null;
  display_name: string | null;
}

export interface AppNotification {
  id: string;
  kind: string;
  title: string;
  body: string;
  url: string;
  read: boolean;
  created_at: string;
}

export interface NotificationPage {
  data: AppNotification[];
  unread_count: number;
  next_cursor: string | null;
}

export interface StoreBanner {
  id: string;
  title: string;
  link_url: string | null;
  image_mobile_url: string;
  image_desktop_url: string | null;
  position: number;
  starts_at: string | null;
  ends_at: string | null;
  active: boolean;
  showing_now: boolean;
}

export interface StoreCategory {
  id: string;
  name: string;
  slug: string;
  position: number;
  image_url: string | null;
}

export interface PublicStorefront {
  id: string;
  slug: string;
  display_name: string;
  store_url: string;
  logo: { kind: "svg" | "webp" | "none"; url: string | null };
  theme: {
    template: "watches" | "fashion" | "electronics";
    primary_color: string;
    accent_color: string;
    background: string;
    heading_font: string;
  };
  contact: {
    whatsapp: string | null;
    whatsapp_url: string | null;
    email: string | null;
    socials: Partial<Record<SocialKey, string>>;
  };
  banners: StoreBanner[];
  categories: StoreCategory[];
}

export type ProductStatus = "draft" | "available" | "in_auction" | "sold" | "unsold";

export interface ProductPhoto {
  id: string;
  status: "processing" | "ready" | "failed";
  position: number;
  width: number | null;
  height: number | null;
  thumb: string | null;
  card: { webp: string | null; avif: string | null };
  zoom: { webp: string | null; avif: string | null };
}

export interface Product {
  id: string;
  slug: string;
  title: string;
  description: string | null;
  category: { id: string; name: string; slug: string } | null;
  reference_price_cents: number | null;
  starting_price_cents: number;
  increment_cents: number;
  youtube_video_id: string | null;
  youtube_url: string | null;
  attributes: { name: string; value: string }[];
  status: ProductStatus;
  views_count: number;
  photos: ProductPhoto[];
  created_at?: string;
}

export interface ProductPage {
  data: Product[];
  total: number;
  page: number;
  last_page: number;
  counts?: Partial<Record<ProductStatus, number>>;
  category?: { id: string; name: string; slug: string } | null;
}

export interface Bidder {
  id: string;
  name: string;
  nickname: string;
  email: string;
  phone: string;
  cpf_masked: string;
  photo_url: string | null;
  avatar_key: string | null;
  alert_minutes: number | null;
  /** Para onde o leiloeiro envia os arremates (opcional). */
  address: Address | null;
  email_verified: boolean;
  eligible: boolean;
  missing: ("name" | "cpf" | "phone" | "email")[];
}

export interface BidderSession {
  token: string;
  expires_at: string;
  bidder: Bidder;
}

export interface Consignment {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  whatsapp_url: string | null;
  description: string;
  photos: string[];
  status: "new" | "read" | "archived";
  from_bidder: boolean;
  created_at: string;
}

/* ---------- Leilão (Fase 3) ---------- */

export type AuctionStatus = "draft" | "scheduled" | "live" | "finished" | "cancelled";
export type ItemStatus = "queued" | "open" | "paused" | "closed_sold" | "closed_unsold" | "skipped";

export interface AuctionSettings {
  item_duration_s: number;
  extend_window_s: number;
  extend_by_s: number;
  gap_s: number;
  chat_enabled: boolean;
  chat_visibility: "everyone" | "logged_in";
  chat_write: "logged_in" | "eligible";
  /** Quem arca com a taxa dos itens arrematados (D-54). O débito é sempre no cartão do leiloeiro. */
  fee_payer: FeePayer;
}

export type FeePayer = "auctioneer" | "bidder";

export interface AuctionItem {
  id: string;
  position: number;
  title: string;
  status: ItemStatus;
  starting_price_cents: number;
  increment_cents: number;
  current_price_cents: number | null;
  next_minimum_cents: number;
  bids_count: number;
  leader: { id: string; nickname: string } | null;
  opened_at: string | null;
  ends_at: string | null;
  paused_remaining_ms: number | null;
  closed_at: string | null;
  sequence: number;
  product: Product | null;
}

export interface Auction {
  id: string;
  slug: string;
  name: string;
  description: string | null;
  status: AuctionStatus;
  starts_at: string;
  started_at: string | null;
  finished_at: string | null;
  settings: Partial<AuctionSettings>;
  items_count: number;
  /** `custom`: capa enviada pelo leiloeiro. Sem ela, é a 1ª foto do 1º item. */
  cover: { thumb: string | null; card: { webp: string | null; avif: string | null }; custom?: boolean } | null;
  current_item_id: string | null;
  items?: AuctionItem[];
  participants_count?: number;
  /** Só no painel. Lote encerrado pode sair do histórico da loja. */
  visible_in_store?: boolean;
  created_at?: string;
}

export interface AuctionList {
  data: Auction[];
  counts?: Partial<Record<AuctionStatus, number>>;
  defaults?: AuctionSettings;
}

export interface RecentBid {
  id: string;
  amount_cents: number;
  type: "manual" | "auto";
  placed_at: string;
  bidder: { id: string; nickname: string; name?: string };
}

export interface AuctionLive {
  auction: Auction;
  running_item_id: string | null;
  recent_bids: RecentBid[];
  log: { action: string; actor: string; item_title: string | null; payload: Record<string, unknown>; at: string }[];
  server_now: string;
}

export interface PublicAuction {
  auction: Auction;
  recent_bids: RecentBid[];
  server_now: string;
}

export interface AuctionState {
  status: AuctionStatus;
  current_item_id: string | null;
  items: AuctionItem[];
  recent_bids: RecentBid[];
  server_now: string;
}

/** Situação do participante logado num leilão (GET /bidders/me/auctions/{a}). */
export interface BidderAuctionSituation {
  bidder_id: string;
  nickname: string;
  participating: boolean;
  eligible: boolean;
  missing: ("name" | "cpf" | "phone" | "email")[];
  banned: boolean;
  auto_bids: Record<string, number>;
}

/* ---------- Pós-leilão (Fase 5) ---------- */

export type AwardStatus = "awaiting_contact" | "negotiating" | "paid" | "delivered" | "withdrawn";

export interface WinnerMessage {
  id: string;
  body: string;
  channels: ("email" | "whatsapp")[];
  sent_at: string;
}

export interface Award {
  id: string;
  amount_cents: number;
  awarded_at: string;
  status: AwardStatus;
  status_label: string;
  notes: string | null;
  item: { id: string; title: string; position: number; thumb: string | null };
  auction: { id: string; name: string; slug: string };
  bidder: { id: string; name: string; nickname: string; email: string; phone: string; cpf_masked: string };
  messages: WinnerMessage[];
}

export interface AwardList {
  data: Award[];
  counts: Partial<Record<AwardStatus, number>>;
  auctions: { id: string; name: string; starts_at: string }[];
  statuses: { value: AwardStatus; label: string }[];
}

export interface WinnerTemplate {
  template: string;
  is_default: boolean;
  fields: { key: string; label: string }[];
}

/** Arrematação vista pelo participante ("Minha conta"). */
export interface BidderAward {
  id: string;
  amount_cents: number;
  awarded_at: string;
  status: AwardStatus;
  status_label: string;
  item: { title: string; thumb: string | null; product_slug: string | null };
  auction: { name: string; slug: string };
  messages: WinnerMessage[];
}

export interface UpcomingAuction {
  auction: Auction;
  participating: boolean;
  has_favorites: boolean;
}

/** Passagem do produto por um lote (histórico na tela do produto). */
export interface ProductHistoryEntry {
  auction: { id: string; name: string; slug: string; status: AuctionStatus; starts_at: string };
  item: {
    id: string;
    position: number;
    status: ItemStatus;
    starting_price_cents: number;
    increment_cents: number;
    current_price_cents: number | null;
    bids_count: number;
    closed_at: string | null;
  };
  award: {
    id: string;
    amount_cents: number;
    awarded_at: string;
    status: AwardStatus;
    status_label: string;
    notes: string | null;
    bidder: { name: string; nickname: string; email: string; phone: string };
  } | null;
  bids: {
    id: string;
    amount_cents: number;
    type: "manual" | "auto";
    placed_at: string;
    voided: boolean;
    void_reason: string | null;
    bidder: { name: string; nickname: string };
  }[];
}

/** Termos de uso da loja no painel (GET/PUT /auctioneer/storefront/terms). */
export interface StoreTermsSettings {
  terms: string;
  default: string;
  is_default: boolean;
  updated_at: string | null;
  max: number;
  fields: { key: string; label: string }[];
}

/* ---------- Cobrança: Configurações → Pagamento (D-47, D-54) ---------- */

export type CardBrand = "visa" | "mastercard" | "elo" | "amex" | "hipercard" | "diners";

/** O cartão salvo: só a bandeira, os 4 últimos dígitos, o nome e a validade. */
export interface PaymentCard {
  brand: CardBrand;
  brand_label: string;
  last4: string;
  holder_name: string;
  exp_month: number;
  exp_year: number;
}

export interface FeeTier {
  min_cents: number;
  /** Nulo: sem teto ("acima de"). */
  max_cents: number | null;
  /** Pontos-base: 750 = 7,5%. */
  rate_bps: number;
  valid_from: string;
}

export interface BillingSettings {
  card: PaymentCard | null;
  fees_start_at: string | null;
  /** Falso no período gratuito (D-47: até 30/11/2026). */
  fees_started: boolean;
  fee_tiers: FeeTier[];
}
