# 06 — Rotas, API e tempo real

## Rotas de página (web)

| URL | Tela | Acesso |
|---|---|---|
| `doleleilao.com.br/` | Site institucional | Público |
| `/cadastro`, `/entrar` | Cadastro e login do **leiloeiro** | Público |
| `/painel/...` | Painel admin do leiloeiro (`/painel/configuracoes/pagamento`: cartão da taxa, D-54) | Leiloeiro |
| `/{slug}` | Home da loja | Público |
| `/{slug}/categoria/{categoria}` | Produtos da categoria | Público |
| `/{slug}/produto/{produto}` | Página do produto | Público |
| `/{slug}/leiloes` | Próximos leilões e encerrados | Público |
| `/{slug}/leilao/{leilao}` | **Auditório** do lote | Público assiste. Lance e chat exigem login |
| `/{slug}/cadastro`, `/{slug}/entrar` | Cadastro e login do **participante** naquela loja | Público |
| `/{slug}/minha-conta/...` | Dados, favoritos, habilitações, alertas, arrematações, mensagens | Participante |
| `/{slug}/quero-leiloar` | Fale com o leiloeiro | Público |

## API REST `/api/v1`

Formato JSON (campos em snake_case, sem envelope `data` nos objetos). Erros no padrão `application/problem+json`, com `errors: {campo: [mensagens]}` no 422. Paginação por cursor. IDs públicos em ULID. Valores em centavos (`amount_cents`) com a moeda implícita BRL.

### Público (por loja)
```
GET    /storefronts/{slug}                         # tema, modelo, contato, banners no ar, categorias. Endereço antigo → 301 { redirect_to }
GET    /storefronts/{slug}/status                  # { state: live|moved|missing, slug }: consulta leve do proxy do site (301/404 reais)
GET    /storefronts/{slug}/products?category=&q=
GET    /storefronts/{slug}/products/{product}          # {product} = slug do produto
POST   /storefronts/{slug}/products/{product}/views    # header X-Viewer-Key (visitante); 1 por pessoa a cada 30 min
GET    /storefronts/{slug}/auctions?status=upcoming|finished
GET    /storefronts/{slug}/auctions/{auction}          # {auction} = endereço do leilão ou id; itens com produto + server_now
GET    /storefronts/{slug}/auctions/{auction}/state    # snapshot leve do auditório (itens sem produto, lances recentes, server_now)
GET    /storefronts/{slug}/auctions/{auction}/chat?before=   # 50 por página + access {can_read, can_write, reason, muted_until, channel}; token do participante opcional
POST   /storefronts/{slug}/consignment-requests
```

### Participante (token `bidder:{auctioneer}`)
```
POST   /storefronts/{slug}/bidders                 # cadastro (já devolve o token)
POST   /storefronts/{slug}/sessions                # login (só vale na loja do cadastro)
POST   /storefronts/{slug}/password/forgot | reset
GET    /bidders/email/verify/{bidder}/{hash}       # link assinado (24 h) → /{slug}/minha-conta?email=…
DELETE /bidders/me/sessions/current
POST   /bidders/me/email/verification
GET    /bidders/me      PATCH /bidders/me    POST /bidders/me/photo    PUT /bidders/me/avatar   # { avatar_key }
GET|PUT|DELETE /bidders/me/favorites/{product}
GET    /bidders/me/eligibility          # cadastro completo? o que falta (CPF, telefone, e-mail…)
GET|POST|DELETE /bidders/me/alerts
GET    /bidders/me/awards              # arrematações com status e as mensagens da loja (RF-E08)
GET    /bidders/me/upcoming-auctions   # leilões agendados/ao vivo em que marcou "Quero participar" ou tem favoritos
PATCH  /bidders/me                     # inclui alert_minutes: 15|30|60|120|null (RF-E06) e address (T-36)
GET    /bidders/me/notifications?cursor=   POST /bidders/me/notifications/read   # { ids? } sem ids = todas
GET    /bidders/me/auctions/{auction}     # situação no leilão: participando, habilitado, o que falta, banido, automáticos
PUT|DELETE /bidders/me/participations/{auction}   # "Quero participar" (RF-E10)
POST   /items/{item}/bids                 # header Idempotency-Key obrigatório; 422 traz minimum_cents; 403 traz reason (incomplete|banned)
PUT    /items/{item}/auto-bid             # { max_amount_cents }
DELETE /items/{item}/auto-bid
POST   /bidders/me/auctions/{auction}/chat   # { body, reply_to_id? }; 403 traz reason (login|incomplete|muted|banned|closed|disabled); 429 = limite
POST   /broadcasting/auth             # canal private-bidder.{id}: só o próprio participante, nunca banido
DELETE /bidders/me                      # LGPD: exclusão
GET    /bidders/me/export               # LGPD: exportação
```

### Leiloeiro (token `auctioneer`)
```
POST   /auctioneers                       # cadastro (já devolve o token)
GET    /auctioneers/slug-availability?slug=   # logado, ignora os endereços da própria loja
GET    /postal-codes/{cep}                    # endereço do CEP (ViaCEP, BrasilAPI de reserva): 404 = não existe, 503 = serviço fora (T-36)
GET    /auctioneers/suggestions?company_name= # endereço livre + nome de exibição sugeridos
POST   /auctioneer/sessions               # login → { token, expires_at, user, auctioneer }
DELETE /auctioneer/sessions/current       # logout
POST   /auctioneer/password/forgot | reset
GET    /auctioneer/email/verify/{user}/{hash}  # link assinado do e-mail (24 h) → redireciona para /painel?email=…
POST   /auctioneer/email/verification     # reenviar o link
GET    /auctioneer/me
GET    /auctioneer/notifications?cursor=  # { data, unread_count, next_cursor }
POST   /auctioneer/notifications/read     # { ids? } sem ids = todas
GET    /auctioneer/onboarding             # primeiros passos (company, products, payment, auction): {steps:[{key, done, blocked, blocked_by, missing}], completed, visible}
POST   /auctioneer/onboarding/dismiss     # ocultar a seção (só depois de concluída)
GET|PATCH  /auctioneer/storefront         # modelo, cores, fundo, fonte, contato, address (T-36)
PUT    /auctioneer/storefront/slug        # troca de endereço (1 a cada 30 dias)
POST|DELETE /auctioneer/storefront/logo      # multipart `logo`: SVG (sanitizado) ou imagem (vira WebP)
GET|POST /auctioneer/banners   POST|DELETE /auctioneer/banners/{id}   PUT /auctioneer/banners/order        # multipart; editar usa POST
GET|POST /auctioneer/categories   POST|DELETE /auctioneer/categories/{id}   PUT /auctioneer/categories/order
GET|POST /auctioneer/products   GET|PATCH|DELETE /auctioneer/products/{p}   # valores em *_cents
GET    /auctioneer/products/attribute-names?q=     # autocompletar da ficha técnica
POST   /auctioneer/products/{p}/photos     # multipart photos[] (vídeo é só o link do YouTube no produto)
PUT    /auctioneer/products/{p}/photos/order   DELETE /auctioneer/products/{p}/photos/{photo}
CRUD   /auctioneer/auctions            # lotes; `items: [{product_id, starting_price_cents?, increment_cents?}]` substitui a lista (ordem = posição)
GET    /auctioneer/auctions/selectable-products?q=&auction=   # disponíveis ou sem venda, fora de outro lote
POST   /auctioneer/auctions/{a}/schedule | unschedule | cancel   # agendar exige o cartão (422 em `payment_card`, D-54)
POST   /auctioneer/auctions/{a}/cover     # multipart { cover } (JPG/PNG/WebP, até 10 MB): capa própria, vira WebP 1600 px + miniatura; DELETE tira. Qualquer status
GET    /auctioneer/auctions/{a}/live       # painel do pregão: itens, lances do item (com nome), log, server_now
POST   /auctioneer/auctions/{a}/start | pause | resume
POST   /auctioneer/items/{item}/close | skip
POST   /auctioneer/items/{item}/void-last-bid   # { reason }: anula o último lance válido (regra 10)
GET    /auctioneer/bidders  POST /auctioneer/bidders/{b}/ban  DELETE /auctioneer/bidders/{b}/ban
GET|POST /auctioneer/auctions/{a}/chat         # histórico com o nome completo + muted/banned; o leiloeiro escreve como a loja
DELETE /auctioneer/auctions/{a}/chat/{msg}
PUT|DELETE /auctioneer/auctions/{a}/mutes/{bidder}   # { minutes: 5|15|60|null } (null = até o fim do leilão)
PUT|DELETE /auctioneer/bidders/{bidder}/ban          # { reason } — a lista de participantes chega na Fase 6
POST   /auctioneer/broadcasting/auth                  # canal privado do chat do próprio lote
GET    /auctioneer/awards?auction=&status=open|{status}&q=   PATCH /auctioneer/awards/{award}   # { status?, notes? }
GET    /auctioneer/awards/export?auction=     # CSV (UTF-8 com BOM, separador ";")
GET    /auctioneer/awards/{award}/message     # texto pronto: o modelo da loja preenchido
POST   /auctioneer/awards/{award}/message     # { body, channels: ["email"|"whatsapp"] } → { message, whatsapp_url }
GET|PUT /auctioneer/winner-message-template   # { template | null } (null volta ao padrão); campos {nome} {apelido} {item} {valor} {leilao} {data} {loja} {whatsapp}
GET    /auctioneer/analytics/live          # snapshot (o resto chega via WebSocket)
GET    /auctioneer/analytics/summary?from=&to=
GET    /auctioneer/consignment-requests   PATCH /auctioneer/consignment-requests/{id}   # { status: new|read|archived }
GET    /auctioneer/billing                # { card | null, fees_start_at, fees_started, fee_tiers: [{min_cents, max_cents, rate_bps, valid_from}] } (D-47, D-54)
PUT    /auctioneer/billing/card           # { number, holder_name, expiry: "MM/AA", cvv } → mesmo corpo do GET; guarda só bandeira, últimos 4, nome e validade
```

### Site (BFF) ← API

```
POST   {FRONTEND_URL}/api/revalidate      # header X-Revalidate-Secret; { tags: ["storefront:{slug}"] }
```
A API chama quando algo visível na loja muda (fila, `RevalidateStorefrontCache`). O site descarta o cache da tag na hora.

## WebSocket (Reverb, protocolo Pusher)

### Canais

| Canal | Tipo | Quem entra | Uso |
|---|---|---|---|
| `auction.{auctionId}` | público | Qualquer pessoa no auditório | Pregão: itens, lances, cronômetro |
| `auction.{auctionId}.chat` / `private-auction.{auctionId}.chat` | público, ou privado se `chat_visibility = logged_in` | Todos, ou participantes da loja e o leiloeiro | Mensagens do chat (só o apelido; o nome completo só na API do painel) |
| `presence-auction.{auctionId}` | presença | Participantes logados | Contagem e lista de online (painel) |
| `private-bidder.{bidderId}` | privado | O próprio participante | "Você foi superado", "automático esgotado", "você venceu", banimento |
| `private-auctioneer.{auctioneerId}` | privado | Leiloeiro | Analytics ao vivo, novos cadastros |

### Eventos (payloads tipados em `packages/realtime-contract`)

| Evento | Canal | Payload principal |
|---|---|---|
| `item.opened` | auction | itemId, position, product (resumo), startingPrice, increment, endsAt, serverNow, sequence |
| `bid.accepted` | auction | itemId, amount, bidderNickname, type, nextMinimum, endsAt, serverNow, sequence |
| `item.extended` | auction | itemId, endsAt, serverNow, sequence |
| `item.paused` / `item.resumed` | auction | itemId, remainingMs / endsAt |
| `item.closed` | auction | itemId, result (sold/unsold/skipped), amount, winnerNickname |
| `bid.voided` | auction | itemId, bidId, newCurrentPrice, leaderNickname |
| `auction.finished` | auction | resumo |
| `chat.message` | chat | id, author (apelido, foto ou avatar ou iniciais, papel), body, replyTo {id, author, trecho}, createdAt |
| `chat.message_deleted` | chat | id |
| `bidder.outbid` | bidder | itemId, newPrice |
| `bidder.autobid_exhausted` | bidder | itemId, maxAmount |
| `bidder.won` | bidder | itemId, amount, awardId → **dispara os confetes + o "plim"** |
| `bidder.banned` | bidder | o cliente desconecta e mostra o aviso |
| `bidder.muted` / `bidder.unmuted` | bidder | auction_id, until (nulo = até o fim do leilão) |
| `analytics.online` | auctioneer | storefrontOnline, auctions[{id, online, loggedIn}] |

Regras: o cliente descarta eventos de item com `sequence` menor ou igual ao último aplicado. Se detectar um salto de `sequence`, refaz a busca do estado (`/storefronts/{slug}/auctions/{a}/state`). Os canais usam o id público: `auction.{auctionPublicId}` e `private-bidder.{bidderPublicId}`. Os nomes e campos estão em `packages/realtime-contract` (T-15). Nesta fase não há `presence-auction.*` nem `private-auctioneer.*` (entram com o chat e o analytics).
