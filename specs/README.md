# Dole Leilão — Planejamento

SaaS para leiloeiros (pessoa física ou jurídica) criarem a própria loja de leilões online, com auditório virtual ao vivo, lances em tempo real e chat.

Fonte original dos requisitos: [`../funcionalidades do Leilaov2.md`](../funcionalidades%20do%20Leilaov2.md).
As decisões de marca e design system e os pontos P-01 a P-15 foram fechados em sessões de perguntas e respostas em 07/11/2026, e estão registrados em [10-decisoes.md](10-decisoes.md).

## Como ler

| Arquivo | Conteúdo | Para quem |
|---|---|---|
| [01-visao-produto.md](01-visao-produto.md) | Contexto, perfis de usuário, escopo v1 × v2, fora de escopo | Todos |
| [02-requisitos.md](02-requisitos.md) | Requisitos funcionais (com IDs e critérios de aceite) e não funcionais | Produto, dev, QA |
| [03-dominio.md](03-dominio.md) | Linguagem ubíqua, contextos delimitados (DDD), agregados, regras de lance | Dev |
| [04-arquitetura.md](04-arquitetura.md) | Stack, monorepo, Clean Architecture, tempo real, multi-tenant, preparo para app | Dev |
| [05-modelo-de-dados.md](05-modelo-de-dados.md) | Tabelas MySQL e índices principais | Dev |
| [06-api-e-tempo-real.md](06-api-e-tempo-real.md) | Rotas de página, endpoints REST, canais e eventos WebSocket | Dev (front e back) |
| [07-design-system.md](07-design-system.md) | Marca Dole Leilão, tokens, personalização do leiloeiro, 3 modelos de loja | Design, front |
| [08-roadmap.md](08-roadmap.md) | Fases, entregáveis, dependências e critérios de pronto | Todos |
| [09-pontos-em-aberto.md](09-pontos-em-aberto.md) | Decisões ainda pendentes, com recomendação | Dono do produto |
| [10-decisoes.md](10-decisoes.md) | Registro das decisões tomadas e o porquê | Todos |

## Resumo em uma tela

- **Stack:** PHP 8.4 (Laravel + Laravel Reverb para WebSocket), Next.js (App Router, TypeScript), MySQL 8.4, Redis. **Sem Docker** (D-41): tudo roda direto no Mac (PHP 8.4 e MySQL/Redis do Homebrew) e direto no VPS.
- **Hospedagem:** um VPS na HostGator (precisa ser VPS, não hospedagem compartilhada), com os serviços instalados direto no servidor e Cloudflare na frente. Meta inicial: 5 lotes ao vivo × 500 pessoas por auditório.
- **Time:** 1 dev + 1 PM/PO. Roadmap sequencial, estimado em 18 a 28 semanas.
- **Arquitetura:** API-first (REST versionada + WebSocket). O site Next.js e o futuro app mobile consomem a mesma API.
- **Multi-tenant:** cada leiloeiro tem um `slug` (`doleleilao.com.br/{slug}`), e os participantes têm conta por leiloeiro ("logins únicos por leiloeiro").
- **Núcleo do domínio:** motor de lances. Um **lote de leilão** é o grupo de itens do dia, e cada **item** vai a pregão com lance manual, lance automático e cronômetro controlado pelo servidor. Habilitação automática com cadastro completo (inclui CPF).
- **Marca:** logo em [`../refs/logo-doleleilao.png`](../refs/logo-doleleilao.png) (mockup) e SVG provisório em [`../refs/logo-provisoria/`](../refs/logo-provisoria/). A paleta foi extraída da logo: cobre `#8A5A38` + grafite `#2F3B3E`.
- **Ordem:** Fase 0 (fundação + design system) → identidade/tenancy → catálogo/loja → motor de leilão e auditório → chat → pós-leilão → analytics → site institucional e 3 modelos de loja → endurecimento e lançamento.
