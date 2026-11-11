# 04 — Arquitetura

## Premissas do briefing

PHP 8.4 · Next.js · MySQL · WebSocket para tempo real · mobile first · boa performance · app nativo numa segunda etapa (arquitetura pronta para ele) · Clean Architecture + DDD.

## Stack proposta

| Camada | Tecnologia | Por quê |
|---|---|---|
| Backend / API | **PHP 8.4 + Laravel** (versão estável atual) | Ecossistema maduro para fila, e-mail, auth por token (Sanctum) e broadcast. O Laravel fica **só nas camadas externas**: o domínio é PHP puro |
| WebSocket | **Laravel Reverb** | Servidor WebSocket oficial do Laravel, em PHP, compatível com o protocolo Pusher. Escala na horizontal via Redis. Os clientes web e mobile usam `pusher-js` / `laravel-echo` |
| Banco | **MySQL 8.4 LTS** | Requisito. InnoDB com trava de linha para os lances |
| Cache / fila / presença | **Redis** | Filas (Laravel Horizon), cache, limite de requisições, escala do Reverb |
| Frontend web | **Next.js** (versão estável atual, App Router, TypeScript, React Server Components) | Requisito. Gera as páginas públicas no servidor para performance e SEO |
| Estilo | Tailwind CSS sobre **variáveis CSS (tokens)** | A personalização do leiloeiro é só troca de variáveis, sem novo build |
| Mídia | **Só fotos** no disco do VPS (camada `Storage` do Laravel) + Cloudflare como CDN. Vídeos apenas por link do YouTube | Fotos processadas na fila (WebP/AVIF em vários tamanhos) |
| E-mail | **Sequenzy** por SMTP (`smtp.sequenzy.com:587`, mailer `sequenzy`, chave em `SEQUENZY_API_KEY`; T-31) | Validação de e-mail, alertas, mensagem ao vencedor |
| Observabilidade | Sentry (front + back, plano gratuito), logs JSON, painel do Horizon | |
| Hospedagem | **VPS na HostGator**, com tudo instalado direto no servidor, sem Docker (ver abaixo) | Requisito do projeto. Precisa ser VPS, não hospedagem compartilhada |
| Dev local | **Nativo no Mac, sem Docker** (D-41): PHP 8.4.23 estático (static-php-cli) em `.tools/php/`, e Composer 2.11 em `.tools/composer.phar`, chamados pelos scripts `bin/php`, `bin/composer`, `bin/artisan`. O Homebrew não tem PHP 8.4 pronto para Mac Intel + macOS 26 (teria que compilar por horas), MySQL e Redis do Homebrew, Node 24. A API roda com `php artisan serve` + Reverb + Horizon + scheduler | O projeto é desenvolvido só neste computador. O PHP padrão do sistema (8.1) continua intocado para não quebrar outros projetos |

> As versões exatas (Laravel, Next.js) são fixadas na Fase 0, com a versão estável vigente na data.

## Estrutura do repositório (monorepo)

```
doleleilao/
├── apps/
│   ├── api/                 # Laravel (PHP 8.4)
│   └── web/                 # Next.js
├── packages/
│   ├── api-contract/        # OpenAPI 3.1 gerado do código (Scramble) + cliente TS gerado
│   ├── design-tokens/       # tokens em JSON → CSS vars (web) e TS (futuro app)
│   └── realtime-contract/   # tipos dos eventos WebSocket (TS) + JSON Schema
├── bin/                     # php, composer, artisan com PHP 8.4
├── infra/production/        # nginx, Supervisor e systemd do VPS
├── refs/                    # logo e referências visuais
├── specs/                   # este planejamento
└── funcionalidades do Leilaov2.md
```

A pasta `apps/mobile` (Expo / React Native) entra na v2 e reaproveita `api-contract`, `design-tokens` e `realtime-contract`.

## Backend: Clean Architecture

```
apps/api/src/
├── <Contexto>/                     # Auction, Catalog, Storefront, Identity, Chat, Notification, Analytics
│   ├── Domain/                     # entidades, objetos de valor, eventos, interfaces de repositório
│   │                               #   → PHP puro, sem Laravel, sem Eloquent
│   ├── Application/                # casos de uso (PlaceBid, ConfigureAutoBid, CloseItem…), DTOs, portas
│   └── Infrastructure/             # repositórios Eloquent, broadcasters Reverb, mail, storage
└── Shared/                         # Money, Slug, TenantId, Clock (porta), EventBus (porta)

apps/api/app/Http/                  # controllers finos: validam a entrada, chamam o caso de uso, devolvem um Resource
```

Regras de dependência: `Domain` não importa nada de fora. `Application` depende só de `Domain`. `Infrastructure` e `Http` dependem das camadas de dentro. Uma regra de arquitetura no CI (Deptrac ou PHPat) quebra o build se alguém violar isso.

Exemplo do fluxo de um lance:
```
POST /api/v1/items/{id}/bids
 → BidController → PlaceBid (caso de uso)
   → AuctionItemRepository::lockForUpdate(itemId)       # transação + SELECT … FOR UPDATE
   → AuctionItem::placeBid(bidder, amount, clock)      # regras puras do domínio
   → AutoBidResolver::resolve(item)             # lances automáticos encadeados, na mesma transação
   → AuctionItemRepository::save(item)  (commit)
   → EventBus::publish(LanceAceito…)           # depois do commit
 → Reverb: broadcast em auction.{id}           # ~ms
 → 201 { bid, item }
```

## Multi-tenant

- **Banco único, coluna `auctioneer_id`** em todas as tabelas do tenant. Um escopo global no Eloquent e um middleware resolvem o tenant pelo slug da URL (público) ou pelo token (painel). Testes automáticos garantem que nenhuma consulta vaze dados entre tenants.
- **Contas de participante por leiloeiro** (D-11): `bidders.auctioneer_id`, com unicidade `(auctioneer_id, email)`. A mesma pessoa pode ter contas separadas em lojas diferentes.
- **Contas de leiloeiro** ficam numa tabela própria (`auctioneer_users`), separada dos participantes. Isso prepara a v2 para membros de equipe.

## Autenticação

| Cliente | Mecanismo |
|---|---|
| Web (Next.js) | O Next.js atua como **BFF**: guarda o token Sanctum num cookie `httpOnly; Secure; SameSite=Lax` e repassa para a API. O navegador nunca vê o token |
| App (v2) | Token Sanctum (Bearer) guardado no armazenamento seguro do aparelho |
| WebSocket | Canais privados e de presença autorizados por `POST /api/v1/broadcasting/auth`, que consulta banimento e cadastro completo |

Escopos de token separados: `auctioneer` (painel) e `bidder:{auctioneer_id}` (loja).

## Tempo real

- **O servidor é a única fonte da verdade.** O cliente nunca envia lance por WebSocket. Ele usa `POST` (com chave de idempotência) e recebe a confirmação por HTTP e por broadcast. O WebSocket fica só para distribuir eventos.
- **Relógio:** cada evento de item traz `endsAt` (UTC ms) e `serverNow`. O cliente calcula a diferença entre o relógio dele e o do servidor e desenha o cronômetro localmente. Um ping de sincronização roda a cada 30 s.
- **Encerramento de itens:** o processo `auction:clock` (supervisionado) roda em laço de 250 ms. Ele pega os itens vencidos com `SELECT … FOR UPDATE SKIP LOCKED`, encerra e publica os eventos. Na v1 roda 1 instância supervisionada (reiniciada automaticamente se cair). O `SKIP LOCKED` já permite rodar 2 instâncias no futuro sem fechar o mesmo item duas vezes.
- **Presença:** canais de presença do Reverb contam os logados. Os visitantes são contados por um canal público com batimento a cada 15 s registrado no Redis (chave com expiração), o que alimenta o RF-G03.
- **Reconexão:** ao reconectar, o cliente chama `GET /api/v1/auctions/{id}/state` e reconstrói o estado. Os eventos têm `sequence` por item para descartar mensagens fora de ordem.
- Detalhes de canais e eventos em [06-api-e-tempo-real.md](06-api-e-tempo-real.md).

## Frontend (Next.js)

```
apps/web/app/
├── (institucional)/          # doleleilao.com.br: home, preços, termos, cadastro do leiloeiro
├── painel/                   # admin do leiloeiro (client-heavy, sem SEO)
├── [slug]/                   # loja do leiloeiro (layout injeta tema + modelo)
│   ├── page.tsx              # home da loja
│   ├── categoria/[cat]/
│   ├── produto/[produto]/
│   ├── leilao/[leilao]/      # auditório (realtime)
│   ├── entrar/ cadastro/
│   └── minha-conta/          # área do participante
└── api/                      # rotas BFF (sessão, proxy, upload de fotos)
```

- **Tema por loja:** o `layout.tsx` de `[slug]` busca a configuração da loja (cacheada com tag `storefront:{slug}`) e injeta as variáveis CSS no `<html>`. Quando o leiloeiro salva, a API pede a revalidação da tag. Nenhuma recompilação é necessária.
- **Modelos de loja:** um registro `templates/{relogios|roupa|eletronicos}` com os mesmos "pontos de troca" (Header, Hero, ProductCard, ItemCard, AuctionHero, Footer). O núcleo (auditório, chat, formulários) é compartilhado, e o modelo muda só a apresentação.
- **Fontes:** as 5 fontes + Montserrat via `next/font` (self-hosted, sem chamadas ao Google em tempo de execução). Só a fonte escolhida pela loja é carregada de antemão.
- **Renderização:** páginas públicas com ISR/cache por tag. Auditório com a estrutura gerada no servidor + uma "ilha" no cliente para o tempo real. Painel no cliente.
- **Estado do auditório:** um store por leilão (Zustand ou `useSyncExternalStore`) alimentado pelos eventos. O cronômetro usa `requestAnimationFrame` e não renderiza a árvore inteira.
- **Confetes + "plim":** `canvas-confetti` carregado sob demanda + um aviso sonoro curto (`/sounds/plim.mp3`, < 30 KB), sem locução (D-33). O áudio é liberado pelo gesto "Entrar no auditório".
- **Vídeo do YouTube:** fachada leve (miniatura + botão play). O player do YouTube só carrega quando a pessoa toca, o que protege o LCP.

## Preparo para o app (v2)

- Toda regra mora na API. O Next.js não tem regra de negócio, só apresentação e BFF.
- Contrato OpenAPI versionado. O cliente TS gerado serve à web e ao app.
- `design-tokens` exporta para CSS e para TS (React Native).
- Notificações modeladas por **canal** (`mail`, `in_app`, `push`). O push entra no app sem mexer no domínio.
- Links profundos previstos desde já: `doleleilao.com.br/{slug}/leilao/{leilao}` abre o app se ele estiver instalado (Universal Links / App Links na v2).

## Hospedagem e infraestrutura (v1)

Hospedagem definida: **HostGator** (D-30, D-36). Requisito: **plano VPS** (servidor virtual com acesso root), e **não** hospedagem compartilhada. A hospedagem compartilhada não serve porque não permite:
- processos rodando o tempo todo: o servidor WebSocket (Reverb), os workers de fila (Horizon) e o relógio dos leilões (`auction:clock`);
- Node.js rodando o Next.js no servidor;
- Redis e controle da versão do PHP (8.4).

**Topologia: 1 VPS** (D-31), Ubuntu LTS, serviços instalados direto no sistema (sem Docker, D-41). Os processos que ficam sempre ligados (Reverb, Horizon, relógio dos leilões, Next.js) são vigiados pelo **Supervisor**, que os reinicia se caírem:

```
                 Cloudflare (DNS, CDN, HTTPS, proteção DDoS, proxy de WebSocket)
                                       │
┌───────────────────────────────── VPS (HostGator) ─────────────────────────────────┐
│  nginx ──▶ next (Node, SSR)        nginx ──▶ php-fpm 8.4 (API Laravel)            │
│  nginx ──▶ reverb (WebSocket)      horizon (filas)      auction:clock (relógio)   │
│  mysql 8.4 LTS                     redis                fotos em /storage (disco) │
│  Supervisor: reverb · horizon · auction:clock · next          cron: schedule:run  │
└───────────────────────────────────────────────────────────────────────────────────┘
        backup diário do MySQL + fotos ──▶ armazenamento externo (fora do VPS)
```

| Item | Proposta |
|---|---|
| Tamanho inicial | Cerca de 4 vCPU, 8–16 GB RAM, SSD ≥ 100 GB, com acesso root. Dá folga para a meta de 5 lotes × 500 pessoas (≈ 2.500 conexões WebSocket) e 20 lances/s por item. Escolher o plano VPS da HostGator mais próximo dessa configuração |
| Data center | No Brasil, se o plano oferecer (latência menor no auditório) |
| Fotos | Disco do VPS, servidas pelo nginx com cache na Cloudflare. O acesso passa pela camada `Storage` do Laravel, então trocar por um armazenamento S3 depois é só configuração |
| Vídeos | Não armazenados: só links do YouTube (D-26) |
| HTTPS | Cloudflare + Let's Encrypt no nginx |
| Backup | `mysqldump` diário + fotos, enviados para armazenamento externo. Retenção de 14 dias. Snapshot semanal do VPS. Teste de restauração todo mês |
| Staging | Segunda cópia da aplicação no mesmo VPS (`/var/www/staging`, `staging.doleleilao.com.br`), com banco, porta do Reverb e programas do Supervisor separados |
| Deploy | GitHub Actions → SSH → `git pull`, `composer install --no-dev`, `php artisan migrate --force`, `npm ci && npm run build`, `supervisorctl restart` e verificação de saúde. Na v1 o deploy tem uma pausa curta; deploy sem pausa (releases em pastas + symlink) pode vir depois |
| Monitoramento | Sentry (plano gratuito), verificação de disponibilidade externa (UptimeRobot ou similar), alerta de disco e memória |

**Caminho de crescimento** (só quando a demanda pedir, sem reescrever código):
1. Mover o MySQL para um servidor próprio ou gerenciado.
2. Mover o Reverb para um VPS próprio (escala via Redis, `REVERB_SCALING_ENABLED`).
3. Fotos para um armazenamento compatível com S3.
4. Mais instâncias da API e do Next.js atrás de um balanceador.

## Simplificações para o time (1 dev + 1 PM/PO)

O time é **1 pessoa de desenvolvimento + 1 PM/PO** (D-34). Para caber nessa capacidade:
- O **OpenAPI é gerado a partir do código Laravel** (Scramble), em vez de escrito à mão. O cliente TS é gerado a partir dele.
- Só 2 pacotes compartilhados de início: `design-tokens` e `realtime-contract`. O `api-contract` é só o arquivo gerado.
- Sem réplica de banco, sem balanceador, staging no mesmo VPS.
- Testes de ponta a ponta (E2E) só para as jornadas críticas (cadastro → lance → arremate). O TDD fica concentrado no núcleo (regras de lance).
- O PM/PO cuida de: aceite de cada fase, contato com o designer da logo, conteúdo (textos, termos), recrutamento dos leiloeiros do beta e testes manuais em celulares reais.
- Uso intenso do Claude Code com as skills instaladas na Fase 0.

## Segurança

- Limite de requisições: lances (5/s por participante), chat (1 mensagem/s, 20/min), login (5/min por IP+e-mail), cadastro (CAPTCHA invisível, como Turnstile).
- Banimento verificado em todo caso de uso sensível e na autorização de canal. Ao banir, a sessão é revogada e o servidor força a desconexão.
- Upload: validação de tipo real (magic bytes), SVG sanitizado, imagens reprocessadas (remove EXIF/GPS).
- Chat: texto puro com escape no render. Links não ficam clicáveis por padrão (anti-phishing). Filtro de palavras opcional por loja.
- Log de auditoria (quem, o quê, quando) para ações do painel e controles do pregão.
- CPF/CNPJ de leiloeiros e CPF de participantes criptografados (cast `encrypted`) + hash para unicidade e busca. O CPF nunca aparece completo na interface (ex.: `***.456.789-**`).

## Qualidade e testes

| Nível | Ferramenta | Foco |
|---|---|---|
| Domínio | Pest (PHP) com TDD | Regras de lance, automáticos, prorrogação, encerramento: 100% dos cenários de [03-dominio.md](03-dominio.md#regras-de-lance) |
| Concorrência | Teste de integração com processos paralelos no MySQL real | Nenhum item com dois vencedores. Nenhum lance perdido |
| API | Testes de feature (Pest) + validação do contrato OpenAPI | |
| Front | Vitest + Testing Library | Componentes do auditório e do chat |
| E2E | Playwright (viewport mobile por padrão) | Jornadas: cadastrar leiloeiro → criar lote → participante completa cadastro → dá lance → vence |
| Carga | k6 (HTTP + WebSocket) | RNF-03 a RNF-05: 5 lotes simultâneos × 500 conexões, rodando contra o VPS de staging |
| Visual / a11y | Playwright + axe | Contraste dos temas × 10 cores × 3 fundos |

CI (GitHub Actions): lint (Pint, PHPStan nível máximo, ESLint, tsc), regras de arquitetura, testes e build. Ambientes: `dev` (nativo no Mac), `staging` e `produção` (no VPS). No CI, o MySQL 8.4 é instalado direto na máquina do GitHub (action `setup-mysql`), sem container.
