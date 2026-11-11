# 05 — Modelo de dados (MySQL 8.4)

Convenções: `id` BIGINT UNSIGNED auto-incremento internamente + `public_id` (ULID, CHAR(26)) exposto na API. Valores em **centavos** (`BIGINT`). Datas em **UTC** (`DATETIME(6)`). Toda tabela do tenant tem `auctioneer_id` com índice. `created_at` e `updated_at` ficam implícitos.

## Identidade & Loja

| Tabela | Colunas principais | Índices / restrições |
|---|---|---|
| `auctioneers` | public_id, company_name, display_name (≤24, ≤2 palavras), slug, slug_changed_at, document_type (cpf/cnpj), document_enc, document_hash (HMAC com a APP_KEY), status (pending → active quando o dono valida o e-mail; suspended), onboarding_completed_at, onboarding_dismissed_at | UNIQUE(slug), UNIQUE(document_hash) |
| `auctioneer_users` | public_id, auctioneer_id, name, email, phone, password (Argon2id), role (owner; v2: staff), email_verified_at, terms_accepted_at | UNIQUE(email) |
| `slug_redirects` | old_slug, auctioneer_id, expires_at | UNIQUE(old_slug). A verificação de slug disponível consulta `auctioneers.slug` **e** `slug_redirects` ainda válidos |
| `storefront_settings` | auctioneer_id, template (watches/fashion/electronics; nulo até o passo 1), primary_color_key, accent_color_key, background_key, heading_font_key, logo_path, logo_kind (svg/webp/none), contact_whatsapp (começa com o telefone do cadastro), contact_email (começa com o e-mail do cadastro), socials JSON, published_at (primeira vez que ficou pública), address_* (endereço de envio opcional, T-36: postal_code CHAR(8), street, number, complement, district, city, state CHAR(2); não sai na loja pública) | UNIQUE(auctioneer_id). A loja é pública quando o leiloeiro está `active` e tem modelo. A pré-visualização (RF-B09) é feita no painel antes de salvar |
| `banners` | public_id, auctioneer_id, title (texto alternativo), image_mobile (obrigatória), image_desktop, link_url (https:// ou /caminho), position, starts_at, ends_at, active | (auctioneer_id, active, position). Até 10 por loja |
| `categories` | public_id, auctioneer_id, name (≤ 40), slug, position, image_path | UNIQUE(auctioneer_id, slug). Até 50 por loja |
| `bidders` | auctioneer_id, public_id, name, nickname (apelido no auditório, gerado do nome: "Ana S."), **cpf_enc, cpf_hash**, email, phone, password (Argon2id), photo_path (opcional), avatar_key (opcional, galeria fixa avatar-01…12), email_verified_at, profile_completed_at, last_seen_at, consent_at, address_* (endereço de entrega opcional, mesmas colunas da `storefront_settings`, T-36) | **UNIQUE(auctioneer_id, email)**, **UNIQUE(auctioneer_id, cpf_hash)** |
| `bidder_password_resets` | bidder_id (PK), token_hash, created_at | Tabela própria: o mesmo e-mail pode existir em lojas diferentes. Link válido por 60 min |
| `bans` | auctioneer_id, bidder_id, reason, banned_by, banned_at, lifted_at | (auctioneer_id, bidder_id, lifted_at) |
| `personal_access_tokens` | (Sanctum) com `abilities` = escopo | |

> As chaves de cor, fonte e fundo (`*_key`) apontam para listas fechadas no código ([07-design-system.md](07-design-system.md)). Nada de hex livre no banco. A API valida com `apps/api/resources/design/theme-catalog.json`, gerado pelo pacote `design-tokens` (mesmas regras de contraste do painel).

## Catálogo

| Tabela | Colunas principais | Índices / restrições |
|---|---|---|
| `products` | auctioneer_id, public_id, category_id, title (≤ 120), slug (fixo depois de sair do rascunho), description (≤ 5000), reference_price, starting_price (> 0), increment (centavos, livre, ≥ 100), youtube_video_id, **attributes JSON** (lista livre `[{name, value}]`, até 30), status (draft/available/in_auction/sold/unsold), views_count | UNIQUE(auctioneer_id, slug), (auctioneer_id, status) |
| `product_photos` | public_id, auctioneer_id, product_id, original_path (temporário, disco local), variants JSON (`thumb` 320 WebP, `card` 640 e `zoom` 1600 em WebP + AVIF), status (processing/ready/failed), position, width, height | (product_id, position). Até 10 por produto. Só fotos: o vídeo é o `youtube_video_id` do produto |
| `favorites` | bidder_id, product_id | PK(bidder_id, product_id) |
| `product_views` | product_id, viewer_key (bidder ou sessão), viewed_at | Desduplicação de 30 min no Redis. A tabela guarda o agregado diário em `product_view_daily(product_id, date, views)` |

## Leilão (lotes e itens)

> `auctions` = **lote de leilão** (o grupo de itens do dia). `auction_items` = cada item do lote.

| Tabela | Colunas principais | Índices / restrições |
|---|---|---|
| `auctions` | auctioneer_id, public_id, name, slug, description, cover_path e cover_thumb_path (capa enviada pelo leiloeiro; sem ela, a capa é a 1ª foto do 1º item), starts_at, status, settings JSON (item_duration_s, extend_window_s, extend_by_s, gap_s, chat_enabled, chat_visibility, chat_write, fee_payer), current_item_id, started_at, finished_at | UNIQUE(auctioneer_id, slug), (status, starts_at) |
| `auction_items` | auction_id, product_id (nulo se o produto for apagado depois), title (cópia para o histórico), position, starting_price, increment, current_price, leading_bidder_id, bids_count, status, opened_at, ends_at, paused_remaining_ms, closed_at, sequence | UNIQUE(auction_id, position), **(status, ends_at)** para o relógio. Sem coluna `version`: a trava pessimista basta (T-03) |
| `bids` | auction_item_id, bidder_id, amount, type (manual/auto), placed_at DATETIME(6), idempotency_key, voided_at, void_reason, voided_by | UNIQUE(bidder_id, idempotency_key), (auction_item_id, amount DESC) |
| `auto_bids` | auction_item_id, bidder_id, max_amount, active, created_at | UNIQUE(auction_item_id, bidder_id) |
| `awards` | auction_item_id, bidder_id, auctioneer_id, amount, awarded_at, status, notes | UNIQUE(auction_item_id). A taxa de cada arrematação fica em `award_fees` (Cobrança) |
| `auction_audit_log` | auction_id, actor_type, actor_id, action, payload JSON, created_at | Somente inserção |

> `bids` só recebe inserções. Anular um lance preenche `voided_*` e nunca apaga a linha.

## Chat

| Tabela | Colunas principais | Índices |
|---|---|---|
| `chat_messages` | public_id, auctioneer_id, auction_id, bidder_id (ou auctioneer_user_id), body (≤ 500 caracteres, texto puro), reply_to_id, deleted_at, deleted_by, created_at | (auction_id, id) |
| `chat_mutes` | auctioneer_id, auction_id, bidder_id, until (nulo = até o fim do leilão), muted_by | UNIQUE(auction_id, bidder_id) |

## Notificações, contato e analytics

| Tabela | Colunas principais |
|---|---|
| `notifications` | public_id, recipient_type (auctioneer_user/bidder), recipient_id, auctioneer_id (tenant), kind, title, body, url, data JSON, dedupe_key, read_at, created_at. Índice (recipient_type, recipient_id, id). UNIQUE(dedupe_key) evita aviso em dobro (ex.: `auction_today:{auction}:{bidder}`). Apagadas depois de 90 dias |
| `auction_participations` ("Quero participar") | auction_id, bidder_id, created_at, enabled_notified_at | UNIQUE(auction_id, bidder_id). Não é aprovação (D-44) |
| `auction_alerts` | auction_id, bidder_id, minutes_before, sent_at. UNIQUE(auction_id, bidder_id): registro dos alertas enviados. O prazo de cada pessoa fica em `bidders.alert_minutes` (padrão 30, nulo = sem alertas) |
| `winner_messages` | public_id, award_id, auctioneer_user_id, body, channels JSON (email/whatsapp), sent_at. O modelo da loja fica em `storefront_settings.winner_message_template` (nulo = texto padrão) |
| `consignment_requests` (fale com o leiloeiro) | public_id, auctioneer_id, bidder_id (opcional), name, email e/ou phone (ao menos um), description (20–2000), photos JSON (até 3, WebP), status (new/read/archived) |
| `page_visits_daily` | auctioneer_id, date, path_kind, visits, unique_visitors |
| `audit_log` | auctioneer_id, actor, action, subject, payload JSON |

## Cobrança (D-47, D-54)

| Tabela | Colunas principais | Índices / restrições |
|---|---|---|
| `fee_tiers` | min_amount, max_amount (inclusive; nulo = sem teto), rate_bps (750 = 7,5%), valid_from, valid_until (nulo = sem fim). Tabela da plataforma, sem `auctioneer_id` e **sem tela de gestão**: mudar a taxa é fechar as faixas atuais (`valid_until`) e inserir as novas. A migração já cria as 4 faixas da D-47 valendo a partir de 01/12/2026 03:00 UTC | (valid_from, min_amount) |
| `payment_cards` | auctioneer_id, brand (visa/mastercard/elo/amex/hipercard/diners), last4, holder_name, exp_month, exp_year. **Nunca** o número completo nem o CVV (sem gateway, PCI DSS) | UNIQUE(auctioneer_id): um cartão por loja, salvar de novo troca |
| `fee_charges` | public_id, auctioneer_id, auction_id (nulo se o lote for apagado), auction_name (cópia), fee_payer (auctioneer/bidder, cópia do lote), amount (soma das taxas), status (pending/paid/failed; sem gateway, sempre pending), card_brand e card_last4 (cartão na hora do débito), charged_at | UNIQUE(auction_id): um débito por lote; (auctioneer_id, created_at), (status, created_at) |
| `award_fees` | auctioneer_id, fee_charge_id, award_id (nulo se a arrematação sumir com o lote), item_title (cópia), base_amount, rate_bps, fee_amount | UNIQUE(award_id) |

> O débito nasce quando o lote termina (`AuctionFinished` → fila). Item sem venda, período gratuito (antes de 01/12/2026) ou taxa zero não geram linha; lote sem nada a cobrar não gera débito.
