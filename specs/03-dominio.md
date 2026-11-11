# 03 — Domínio (DDD)

Base para as skills `domain-driven-design` e `clean-architecture` (instalação na Fase 0). Este arquivo é o embrião do `CONTEXT.md` do backend.

## Linguagem ubíqua

Os termos abaixo valem no código, na interface, nas issues e nas conversas.

| Termo | Significado | No código |
|---|---|---|
| **Leiloeiro** | A conta da empresa ou pessoa dona da loja (o tenant) | `Auctioneer` |
| **Loja** | A vitrine pública do leiloeiro em `/{slug}`, com modelo e personalização | `Storefront` |
| **Participante** | Pessoa cadastrada na loja de um leiloeiro para dar lances | `Bidder` |
| **Produto** | Item do catálogo do leiloeiro. Existe antes e fora de um lote | `Product` |
| **Lote de leilão** (ou **Leilão**) | **Grupo de itens que serão leiloados naquele dia**, com data e hora de início. No painel ele se chama "lote". Na loja pública aparece como "leilão" (`/{slug}/leilao/{nome}`). É a mesma coisa (decisão D-22) | `Auction` |
| **Item** | Um produto colocado dentro de um lote, com posição na fila e pregão próprio | `AuctionItem` |
| **Pregão** | A disputa ao vivo de um item: abre, recebe lances, encerra. Os itens de um lote vão a pregão um de cada vez | estado do `AuctionItem` |
| **Lance** | Oferta de valor de um participante num item. Não muda depois de aceito | `Bid` |
| **Lance automático** | Valor máximo que o participante autoriza o sistema a ofertar por ele | `AutoBid` (proxy bid) |
| **Incremento** | Acréscimo mínimo sobre o lance atual, em reais, definido pelo leiloeiro no cadastro do produto (ex.: R$ 100, R$ 200, R$ 500) | `Increment` (Money) |
| **Ficha técnica** | Lista livre de pares nome/valor do produto (ex.: "Calibre: automático", "Tamanho: M"). Não há campos fixos por ramo | `ProductAttribute` |
| **Arrematação** | Resultado de um item encerrado com vencedor | `Award` |
| **Arrematante** | O participante vencedor de um item | |
| **Cadastro completo** | Participante com nome, **CPF**, telefone e e-mail validado. Foto ou avatar são opcionais (D-38). Quem tem cadastro completo está **habilitado** automaticamente em todos os lotes da loja | `Bidder::isEligible()` |
| **Auditório** | A tela pública do lote ao vivo, com pregão e chat | (interface, não entidade) |
| **Banimento** | Bloqueio de um participante numa loja | `Ban` |

### Primeiros passos (onboarding do leiloeiro)

Não é um agregado com estado próprio: é uma **leitura calculada** (`OnboardingProgress`, contexto Loja) a partir dos dados reais, conforme D-42 e RF-A10 a RF-A16:

| Passo | Concluído quando |
|---|---|
| 1. Dados da empresa | e-mail validado **e** contato (telefone/WhatsApp) preenchido **e** modelo da loja escolhido |
| 2. Produtos | ≥ 1 produto `disponível` (≥ 1 foto, valor inicial, incremento) |
| 3. Leilão | ≥ 1 lote `agendado` com ≥ 1 item |

Só é guardado no banco: quando a seção foi concluída (`onboarding_completed_at`, gravado uma vez e que não volta atrás) e quando foi ocultada (`onboarding_dismissed_at`).

## Contextos delimitados

```
┌─────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐
│ Identidade & Acesso │   │ Loja (Tenancy)       │   │ Catálogo             │
│ contas, login, CPF, │──▶│ leiloeiro, slug,     │──▶│ produtos, fotos,     │
│ banimento, cadastro │   │ personalização,      │   │ vídeo (YouTube),     │
│ completo            │   │ banners, categorias  │   │ ficha, favoritos     │
└─────────────────────┘   └──────────────────────┘   └──────────┬───────────┘
                                                                │ ProdutoColocadoNoLote
┌─────────────────────┐   ┌──────────────────────┐   ┌──────────▼───────────┐
│ Notificações        │◀──│ Chat                 │   │ LEILÃO (núcleo)      │
│ e-mail, in-app,     │   │ mensagens, respostas,│◀──│ lotes, itens,        │
│ alertas, (push v2)  │◀──│ moderação            │   │ lances, lance autom.,│
└─────────────────────┘   └──────────────────────┘   │ pregão, arrematações │
          ▲                                          └──────────┬───────────┘
          │                 ┌──────────────────────┐            │
          └─────────────────│ Analytics            │◀───────────┤ eventos de domínio
                            │ presença, visitas    │            ▼
                            └──────────────────────┘   ┌──────────────────────┐
                                                       │ Cobrança             │
                                                       └──────────────────────┘
```

| Contexto | Tipo | Observação |
|---|---|---|
| **Leilão** | Núcleo | O diferencial do produto. Domínio puro, com TDD e sem dependência de framework |
| Loja, Catálogo, Chat | Suporte | |
| Identidade & Acesso, Notificações, Analytics | Genérico | Usa o que o framework oferece (auth, mail, broadcast) |
| Cobrança | Suporte | `src/Billing` (D-47, D-54): faixas da taxa, cartão do leiloeiro e débito da taxa por lote encerrado. Depende do Leilão (consome `LoteEncerrado` e lê as arrematações por uma camada anticorrupção, `AuctionSoldItems`). Sem gateway: o débito fica "a cobrar" |

Os contextos se comunicam por **eventos de domínio** (fila Redis), nunca acessando as tabelas uns dos outros.

## Agregados principais (contexto Leilão)

### `Auction` (Lote de leilão)
- Atributos: `id`, `auctioneerId`, `slug`, `startsAt`, `status` (draft → scheduled → live → finished | cancelled), `settings` (duração do item, janela e tempo de prorrogação, intervalo entre itens, regras de chat), lista ordenada de `AuctionItemId`.
- Invariantes: precisa ter ≥ 1 item e o leiloeiro precisa ter cartão de crédito cadastrado (D-54) para ser agendado. Só um item fica em pregão por vez. Não volta para `draft` depois de `live`.

### `AuctionItem` (Item): agregado próprio, porque é a unidade de concorrência
- Atributos: `id`, `auctionId`, `productId`, `position`, `startingPrice`, `increment`, `currentPrice`, `leadingBidderId`, `endsAt`, `status` (queued → open → (paused) → closed_sold | closed_unsold | skipped), `sequence`, `version`.
- `startingPrice` e `increment` são **copiados do produto** quando ele entra no lote. O leiloeiro pode ajustá-los até o item abrir.
- Os lances são aplicados **dentro** do agregado com trava pessimista (`SELECT … FOR UPDATE`): um único escritor por item.

### `Bid` (Lance): entidade que não muda depois de criada
- `id`, `auctionItemId`, `bidderId`, `amount` (centavos), `type` (manual | auto), `placedAt` (horário do servidor, microssegundos), `idempotencyKey`, `voidedAt` / `voidReason` (anulação pelo leiloeiro; nunca apagado).

### `AutoBid` (Lance automático)
- `auctionItemId`, `bidderId`, `maxAmount`, `createdAt`, `active`. Um por participante por item, atualizável para um valor maior.

### `Award` (Arrematação)
- `auctionItemId`, `bidderId`, `amount`, `awardedAt`, `status` (aguardando contato → em negociação → pago → entregue | desistência).

## Regras de lance

1. **Quem pode dar lance:** participante logado, com **cadastro completo** (nome, CPF, telefone, e-mail validado), sem banimento e sem silenciamento. Não existe aprovação manual (D-23). O leiloeiro e a equipe dele **não** dão lance na própria loja.
2. **Incremento:** é um valor em reais livre, informado no cadastro do produto (mínimo R$ 1,00). Ele é copiado para o item.
3. **Valor mínimo:** em item sem lances, `valor ≥ startingPrice`. Em item com lances, `valor ≥ currentPrice + increment`. O valor **não** precisa ser múltiplo do incremento (D-39). A interface sugere botões com atual + 1×, 2× e 5× o incremento.
4. **Ordem:** os lances de um item são gravados em sequência. Em empate de valor, ganha o primeiro gravado. O segundo recebe a resposta "superado, o lance mínimo agora é X".
5. **Já ganhando:** o líder não pode cobrir o próprio lance manualmente, mas pode aumentar o lance automático.
6. **Lance automático (proxy):**
   - Quando B dá um lance e A tem um automático com máximo `M`: se `M ≥ lance_B + incremento`, o sistema lança `lance_B + incremento` em nome de A (tipo `auto`).
   - Com dois automáticos concorrentes, vence o de maior máximo, pelo valor `menor_máximo + incremento` (limitado ao próprio máximo). Se os máximos empatam, vence quem cadastrou primeiro.
   - Cadastrar um automático num item aberto já dispara o lance inicial, se a pessoa não estiver ganhando.
   - Quando o automático é superado, o dono recebe o aviso "seu lance automático foi superado".
7. **Prorrogação:** se um lance é aceito com `endsAt - agora < janela` (padrão 15 s), então `endsAt = agora + tempo de prorrogação` (padrão 15 s).
8. **Encerramento:** o processo "relógio" do servidor fecha os itens com `endsAt ≤ agora` (verificação a cada 250 ms, com `SKIP LOCKED`). Item com lances → `closed_sold` + `Award`. Item sem lances → `closed_unsold`. Depois do intervalo configurado, o próximo item abre. Quando o último item fecha, o lote fica `finished`.
9. **Pausa:** pausar congela o tempo restante. Ao retomar, `endsAt = agora + restante`. Se o servidor de WebSocket cair, o pregão pausa sozinho.
10. **Anulação:** o leiloeiro pode anular o último lance, informando o motivo. O preço volta ao lance válido anterior, e os lances automáticos são recalculados.

## Notificações (sino)

Contexto **Notificações** (genérico): cada notificação tem destinatário (leiloeiro ou participante), tipo (`NotificationKind`), título, texto, link e data de leitura. Os textos saem de um único lugar no domínio, a partir do tipo e dos dados (nome do leilão, data, valor), em pt-BR. Os outros contextos não criam notificações direto: publicam eventos, e o contexto de Notificações decide quem avisar (D-43).

## Eventos de domínio

| Evento | Origem | Consumidores |
|---|---|---|
| `LeiloeiroCadastrado` | Identidade | Loja (cria a loja padrão), Notificações |
| `PrimeirosPassosConcluidos` | Loja | Analytics (tempo do cadastro até o primeiro lote agendado) |
| `ParticipanteCadastrado`, `EmailValidado`, `CadastroCompletado` | Identidade | Notificações, Analytics |
| `ParticipanteBanido` | Identidade | Leilão (bloqueia lances), Chat, Realtime (derruba a conexão) |
| `LoteAgendado` | Leilão | Notificações (programa os alertas) |
| `LojaPublicada` | Loja | Notificações ("Sua loja está no ar") |
| `ParticipacaoConfirmada` ("Quero participar") | Leilão | Notificações (participante habilitado; leiloeiro: novo participante) |
| `ItemAberto`, `LanceAceito`, `LanceAutomaticoSuperado`, `ItemProrrogado`, `ItemPausado`, `ItemEncerrado` | Leilão | Realtime, Notificações, Analytics |
| `ItemArrematado` | Leilão | Notificações (ganhador e leiloeiro), Catálogo (status do produto) |
| `LoteEncerrado` (`AuctionFinished`) | Leilão | Realtime, Cobrança (débito da taxa dos itens arrematados no cartão do leiloeiro, D-54) |
| `MensagemEnviada`, `MensagemRemovida` | Chat | Realtime |
| `ProdutoVisualizado` | Catálogo | Analytics |
