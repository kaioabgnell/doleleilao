# Dole Leilão — Contexto do domínio

Glossário que vale para código, interface, issues e conversas. Detalhes e regras: [specs/03-dominio.md](specs/03-dominio.md). Decisões: [specs/10-decisoes.md](specs/10-decisoes.md).

## Termos

| Termo (pt-BR) | No código | Definição |
|---|---|---|
| Leiloeiro | `Auctioneer` | Empresa ou pessoa (CPF/CNPJ) dona de uma loja. É o tenant. |
| Loja | `Storefront` | Vitrine pública do leiloeiro em `/{slug}`: modelo + personalização. |
| Modelo | `template` | Visual base da loja: `watches` (Relógios), `fashion` (Roupa), `electronics` (Eletrônicos). |
| Primeiros passos | `OnboardingProgress` | Seção do dashboard do leiloeiro novo com 4 passos: dados da empresa → produtos → pagamento → leilão. Calculada a partir dos dados reais (D-42, D-55). |
| Participante | `Bidder` | Pessoa cadastrada **numa loja** para dar lances. A conta vale só para aquele leiloeiro. |
| Cadastro completo | `Bidder::isEligible()` | Nome, CPF, telefone e e-mail validado. Habilita o participante automaticamente em todos os lotes da loja. Foto/avatar é opcional. |
| Produto | `Product` | Item do catálogo do leiloeiro. Tem fotos, link do YouTube, ficha técnica livre, valor inicial e incremento (R$). |
| Lote de leilão (ou Leilão) | `Auction` | **O grupo de itens leiloados naquele dia**, com data e hora. No painel: "lote". Na loja: "leilão". |
| Item | `AuctionItem` | Um produto dentro de um lote. Vai a pregão sozinho, um de cada vez. |
| Pregão | estado do `AuctionItem` | A disputa ao vivo de um item: abre, recebe lances, prorroga, encerra. |
| Lance | `Bid` | Oferta de um participante num item. Nunca é alterado nem apagado (só anulado com motivo). |
| Lance automático | `AutoBid` | Valor máximo que o participante autoriza o sistema a ofertar por ele. |
| Incremento | `Increment` | Acréscimo mínimo sobre o lance atual, em reais, livre por produto. O lance não precisa ser múltiplo dele. |
| Arrematação | `Award` | Resultado de um item encerrado com vencedor (o arrematante). |
| Auditório | (tela) | Página pública do lote ao vivo: pregão + chat. |
| Notificação | `Notification` | Aviso no sino do painel ou da loja (D-43). Só o contexto de Notificações cria; os outros publicam eventos. |
| Quero participar | `AuctionParticipation` | Interesse do participante num leilão. Não é aprovação (D-44): só define quem recebe os avisos do leilão. |
| Banimento | `Ban` | Bloqueio de um participante numa loja, com efeito imediato. |
| Taxa | `ItemFee` | O que o Dole Leilão cobra por item arrematado: alíquota única pela faixa do valor (D-47). Item sem venda não paga. |
| Faixa da taxa | `FeeTier` | Intervalo de valor com a alíquota e a vigência (tabela `fee_tiers`). |
| Quem paga a taxa | `FeePayer` | Escolha do lote: o leiloeiro **assume** (`auctioneer`) ou **repassa ao vencedor** (`bidder`). O débito é sempre no cartão do leiloeiro. |
| Cartão do leiloeiro | `CreditCard` | Cartão de crédito em Configurações → Pagamento, exigido para agendar lotes. Guarda só bandeira, 4 últimos dígitos, nome e validade (D-54). |
| Débito da taxa | `FeeCharge` | Cobrança no cartão do leiloeiro, uma por lote encerrado: a soma das taxas dos itens arrematados. |

## Evite

- "Lote" para um produto isolado. Use **item**.
- "Usuário" sem qualificar. Diga **leiloeiro** ou **participante**.
- "Leilão" no código para o item. `Auction` é sempre o lote do dia.
