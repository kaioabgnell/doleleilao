# 08 — Roadmap

**Time:** 1 pessoa de desenvolvimento + 1 PM/PO (D-34). Com uma só pessoa desenvolvendo, as fases são **sequenciais**. Em paralelo, o PM/PO cuida da marca (designer), dos textos, dos termos de uso e do recrutamento dos leiloeiros do beta.

**Estimativa** em semanas de 1 dev em tempo integral, usando Claude Code com as skills. É uma faixa de referência e deve ser revisada ao fim de cada fase.

Princípio: **backend, tempo real e painel não dependem da marca oficial.** Eles começam já com os tokens provisórios. Só a Fase 7 (site institucional + 3 modelos) depende da logo oficial e da paleta validada.

```
F0 ─▶ F1 ─▶ F2 ─▶ F3 ─▶ F4 ─▶ F5 ─▶ F6 ─▶ F7 ─▶ F8 ─▶ Lançamento v1
Fund.  Ident. Catál. Motor  Chat  Pós-  Analyt. Instit. Beta
       /Loja  /Loja  leilão       leilão       +3 mod.
                       │
                       └─ marco "MVP interno": um lote completo roda ponta a ponta
PM/PO em paralelo: logo oficial (até F7) · textos e termos (até F7) · 2 ou 3 leiloeiros beta (até F8)
```

| Fase | Semanas | Acumulado |
|---|---|---|
| F0 Fundação | 1–2 | 1–2 |
| F1 Identidade e loja | 2–3 | 3–5 |
| F2 Catálogo e loja pública | 2–3 | 5–8 |
| F3 Motor de leilão e auditório | 4–6 | 9–14 |
| F4 Chat | 1–2 | 10–16 |
| F5 Pós-leilão e área do participante | 1–2 | 11–18 |
| F6 Analytics e usuários | 1 | 12–19 |
| F7 Institucional + 3 modelos | 4–6 | 16–25 |
| F8 Endurecimento, beta e lançamento | 2–3 | **18–28 semanas (≈ 4,5 a 7 meses)** |

---

## Fase 0: Fundação · 1–2 semanas
**Objetivo:** o repositório pronto, o servidor contratado e o design system de partida.

- [x] Instalar as skills em `.claude/skills/` (22 skills, revisadas; versões em `skills-lock.json`):
  - [x] `wondelai/skills@clean-architecture`
  - [x] `wondelai/skills@domain-driven-design`
  - [x] `nextlevelbuilder/ui-ux-pro-max-skill@ui-ux-pro-max`
  - [x] `emilkowalski/skills` (https://github.com/emilkowalski/skills), todas as 17
  - [x] `pbakaus/impeccable` (https://github.com/pbakaus/impeccable). O hook de detecção dele fica **desligado** (é opcional)
  - [x] `leonxlnx/taste-skill` (https://github.com/leonxlnx/taste-skill) → `design-taste-frontend`
- [x] Monorepo (npm workspaces): `apps/web`, `packages/design-tokens`, `bin/` (PHP 8.4 do projeto)
- [x] `packages/realtime-contract` (entrou na Fase 3, junto com os eventos)
- [x] Ambiente local sem Docker (D-41): PHP 8.4.23 e Composer 2.11 em `.tools/`, bancos `doleleilao` e `doleleilao_test` no MySQL 9.4 local, Redis local (bancos 4 e 5, prefixo `doleleilao_`)
- [x] Laravel 13.40 em `apps/api`: `src/<Contexto>/{Domain,Application,Infrastructure}`, `Money` e `Clock` (TDD), Deptrac, Scramble (`/docs/api`), Sanctum, Reverb (porta 8085), Horizon, Pest, PHPStan no nível máximo, `GET /api/v1/health`
- [x] Next.js 16.4 + Tailwind 4 lendo os tokens, `next/font` com as 6 fontes, página interna `/design-system`
- [x] `packages/design-tokens`: paleta, 10 cores, fundos, fontes, `resolveStoreTheme()` + testes de contraste de todas as combinações. Validado pela ui-ux-pro-max ([07](07-design-system.md#validação-pela-ui-ux-pro-max-07112026))
- [x] Logo provisória em SVG ([`refs/logo-provisoria/`](../refs/logo-provisoria/))
- [ ] **PM/PO:** contratar o **VPS** na **HostGator** (D-36), apontar o domínio para a Cloudflare
- [ ] VPS sem Docker: nginx, PHP-FPM 8.4, MySQL 8.4 LTS, Redis, Node 24, Supervisor (`infra/production/`), staging em `staging.doleleilao.com.br`, deploy pelo GitHub Actions, backup diário
- [x] CI (`.github/workflows/ci.yml`): web + tokens + API (MySQL 8.4 e Redis instalados direto na máquina do GitHub, sem container)
- [x] `CONTEXT.md` (glossário) na raiz + `CLAUDE.md`

**Pronto quando:** `npm run dev` sobe a API, o Reverb, as filas e o site localmente, o CI está verde e um deploy de teste chega ao staging no VPS.

## Fase 1: Identidade e loja do leiloeiro · 2–3 semanas · ✅ concluída
RF-A01–A06, RF-B01–B09

- [x] Cadastro do leiloeiro (CPF/CNPJ, inclusive o CNPJ alfanumérico; slug com checagem de unicidade ao vivo, incluindo slugs em redirecionamento; nome de exibição), validação de e-mail, login e recuperação de senha (API + telas `/cadastro`, `/entrar`, `/esqueci-senha`, `/redefinir-senha`)
- [x] Troca de slug (1 vez a cada 30 dias, endereço antigo reservado por 90 dias), no painel em "Dados da empresa". O 301 em si entra com o layout `[slug]`
- [x] Multi-tenant: escopo global `BelongsToTenant` (falha fechada: sem loja definida, a consulta lança erro), loja do painel pelo token e da loja pública pelo slug, + testes de isolamento
- [x] Painel: estrutura base (Montserrat/Inter, cobre/grafite), menu mobile, sessão no BFF (cookie httpOnly)
- [x] Dashboard com a seção **"Comece seu primeiro leilão"** (3 passos, RF-A10 a RF-A16) e `GET /auctioneer/onboarding`. Nesta fase só o passo 1 funciona. Os passos 2 e 3 aparecem com o rótulo "em breve" até as fases 2 e 3 (`config/doleleilao.php` → `onboarding.available_steps`)
- [x] Tela "Dados da empresa" (passo 1): contato + modelo + personalização num só lugar
- [x] Personalização: modelo, 10 cores, 3 fundos, 5 fontes, pré-visualização ao vivo, bloqueio por contraste (a API valida com o catálogo gerado pelo `design-tokens`)
- [x] Logo: corte e enquadramento no painel (3:1), SVG sanitizado (sem scripts, eventos e referências externas), imagens regravadas em WebP sem EXIF
- [x] Banners (celular obrigatório, computador opcional, link, período, ativo, ordem; até 10) e categorias (nome, endereço único na loja, imagem opcional, ordem)
- [x] Layout `[slug]` injetando o tema + modelo "base" provisório (os 3 modelos definitivos ficam para a F7), com cache por tag `storefront:{slug}` descartado pela API ao salvar. Endereço antigo responde **301** e loja inexistente **404** (no `proxy.ts`, antes de renderizar)
- [x] Menu lateral do painel, recolhível (no celular, abre por cima) (D-45)
- [x] Notificações (D-43): contexto `Notification`, tabela, API do leiloeiro, **sino no topo do painel** e os primeiros avisos do leiloeiro ("Boas-vindas", "Sua loja está no ar")
- [x] Card "Sua loja" com indicador pulsante quando está no ar e botão para abrir a loja em nova aba
- [x] Ilustrações SVG de cada modelo na pré-visualização da loja

**Pronto quando:** um leiloeiro se cadastra, vê os 3 passos no dashboard, conclui o passo 1 e vê a loja dele em `/{slug}` com as cores, a fonte e a logo (ou a logo de texto) escolhidas. ✅ Concluída em 08/11/2026 (verificado no navegador, celular e computador).

## Fase 2: Catálogo e loja pública · 2–3 semanas · ✅ concluída (falta só a nota oficial do Lighthouse)
RF-C01–C06, RF-E01–E05, RF-E09

- [x] CRUD de produtos: fotos (até 10, processadas na fila em 320/640/1600 px, WebP + AVIF), link do YouTube (vários formatos), incremento em R$ livre (mínimo R$ 1,00), **ficha técnica livre** (nome/valor com autocompletar), rascunho ↔ disponível (disponível exige foto)
- [x] Ativar o **passo 2** dos primeiros passos (≥ 1 produto disponível)
- [x] Contagem de visualizações (1 por pessoa a cada 30 min, desduplicação no Redis, agregado diário em `product_view_daily`)
- [x] Páginas públicas: home da loja (com busca), categoria, produto, lista de leilões (vazia até a F3), com cache por tag `storefront:{slug}`. Vídeo com fachada leve (youtube-nocookie só depois do toque)
- [x] Cadastro e login do participante por loja (cookie com o caminho da loja), com **CPF obrigatório** (único por loja), validação de e-mail, nova senha, foto **ou avatar** opcionais (galeria de 12 símbolos neutros) e indicador de "cadastro completo" em "Minha conta"
- [x] Favoritos (pedido feito sem login é aplicado depois de entrar ou se cadastrar)
- [x] **Sino de notificações no topo da loja** para o participante logado (RF-N01, RF-N02) + `GET /bidders/me/notifications`
- [x] "Quer leiloar algo seu? Fale com o leiloeiro": formulário com até 3 fotos → e-mail + sino do leiloeiro + caixa "Pedidos para leiloar" no painel

**Pronto quando:** um participante se cadastra numa loja, completa o cadastro, navega, favorita e envia um pedido ao leiloeiro, e o Lighthouse mobile dá ≥ 90. ✅ Jornada verificada no navegador (celular) em 08/11/2026. Medição com o Chrome no perfil do Lighthouse mobile (4G lento, CPU 4x): LCP 2,0 s, CLS 0, 139 KB de JS. A nota oficial do Lighthouse fica pendente (a ferramenta não foi instalada neste computador).

## Fase 3: Motor de leilão e auditório · 4–6 semanas (caminho crítico) · ✅ concluída localmente (falta o teste no staging)
RF-D01–D06, RF-F01–F04, RF-F09–F11

- [x] Domínio `Auction` (lote) / `AuctionItem` / `Bid` / `AutoBid` com **TDD**, cobrindo todas as regras de [03-dominio.md](03-dominio.md#regras-de-lance)
- [x] Cadastro de lote de leilão no painel (data/hora, itens, ordem, configurações)
- [x] Ativar o **passo 3** dos primeiros passos (≥ 1 lote agendado) e a tela de conclusão com o link da loja
- [x] Caso de uso `PlaceBid` com trava pessimista + idempotência + lances automáticos encadeados
- [x] Processo `auction:clock` (encerramento, prorrogação, abertura do próximo item) com `SKIP LOCKED`, supervisionado
- [x] Reverb: canais `auction.*` e `private-bidder.*`, autorização de canal com checagem de banimento e cadastro completo
- [x] Habilitação automática por cadastro completo, com pedido para completar o cadastro dentro do auditório
- [x] Auditório (mobile first): fotos e vídeo do item, lance atual, cronômetro sincronizado com o servidor, botão de lance em 1 toque, lance automático, fila de itens, estados fixos
- [x] Controles do pregão no painel (iniciar, pausar, encerrar item, pular, anular lance) + log de auditoria
- [x] Tela do ganhador: confetes + "Você é o ganhador!" na tela + **"plim"**, botão de silenciar, `prefers-reduced-motion`
- [x] Reconexão e reconstrução do estado
- [x] **"Quero participar"** (RF-E10, D-44) e as notificações ligadas ao leilão (RF-N03 a RF-N05): habilitado para o leilão, véspera e dia (processo às 8h), vencedor; do lado do leiloeiro: leilão amanhã/hoje, item arrematado, novo participante
- [x] Teste de concorrência: N processos disputando o mesmo item, com exatamente 1 vencedor e nenhum lance perdido

**Pronto quando (marco MVP interno):** um lote de 5 itens roda de ponta a ponta no staging, com 3 participantes em celulares diferentes, lances automáticos e prorrogação, sem nenhuma divergência entre as telas.

Em 08/11/2026, localmente: lote de 3 itens com o painel e 2 participantes (navegadores separados, perfil de celular) — o auditório abriu sozinho pelo WebSocket, lance manual, automático cobrindo e esgotando (com aviso), cronômetros iguais nos dois aparelhos, tela do ganhador só no vencedor, item pulado e encerrado pelo painel, aviso no sino. Teste de concorrência: 10 processos PHP no mesmo item, nenhum lance perdido, 1 líder, 1 arrematação. Falta repetir no staging (depende do VPS, Fase 0).

## Fase 4: Chat do auditório · 1–2 semanas · ✅ concluída localmente (falta o teste de carga no staging)
RF-D03, RF-F05–F08

- [x] Envio e histórico, visibilidade e permissão de escrita conforme a configuração do lote
- [x] Responder citando (estilo WhatsApp), rolar até a original
- [x] Seletor de emojis (lazy)
- [x] Moderação: apagar, silenciar, banir. Limite de mensagens
- [x] Layout mobile: chat recolhível sem cobrir o botão de lance

**Pronto quando:** 500 clientes simulados num auditório conversam durante um pregão sem afetar a latência dos lances (RNF-03).

Em 08/11/2026, localmente (Reverb e API dedicados, 500 conexões WebSocket assinando o pregão e o chat, 2 participantes dando lances a cada 0,5 s, 20 conversando, ~10 mensagens/s, 30 s por fase): lance p95 de 127 ms sem chat e 99 ms com chat; lance chegando aos outros aparelhos p95 75 ms; mensagem do chat chegando a todos p95 63 ms (meta ≤ 500 ms); 197.500 mensagens entregues. No navegador: chat antes e durante o pregão, emoji, resposta citando, mensagem do leiloeiro, silenciar/liberar, apagar, banir com efeito imediato, e o chat aberto no celular termina acima do botão de lance. Falta repetir no staging.

## Fase 5: Pós-leilão, notificações e área do participante · 1–2 semanas · ✅ concluída
RF-E06–E08, RF-G05–G06

- [x] Arrematações (status, observações, painel do leiloeiro, exportação CSV para planilha)
- [x] Mensagem ao vencedor (modelo editável) por e-mail + in-app + botão **"Abrir no WhatsApp"** (`wa.me` com o texto pronto)
- [x] Alertas de "leilão vai começar" por e-mail (15, 30, 60 ou 120 min; quem marcou "Quero participar" ou favoritou um item)
- [x] "Minha conta" completa: situação do cadastro, favoritos, próximos leilões, alertas, arrematações, mensagens
- [x] Modelos de e-mail (marca do leiloeiro + rodapé Dole Leilão)
- [x] Confirmações e modais no padrão do design system (`useConfirm`, sem `window.confirm`)

Em 08/11/2026, no navegador: modelo da mensagem editado, mensagem por e-mail e pelo WhatsApp (janela abre com o número e o texto), status "Pago", observações, planilha CSV (UTF-8, `;`), "Minha conta" com a arrematação e as mensagens, alerta salvo e aviso no sino. E-mails renderizados com a marca da loja e com a do Dole Leilão.

## Fase 6: Analytics e gestão de usuários · 1 semana
RF-G01–G04, RF-G07

- [ ] Lista de participantes, filtros, banir e desbanir (com efeito imediato, inclusive no WebSocket)
- [ ] Online agora (loja + cada auditório) via presença + batimentos
- [ ] Resumo histórico (visitas, visualizações, lances, taxa de arremate)
- Painel da equipe Dole Leilão (super admin): **fica para a v2** (D-28). Na v1, a suspensão de um leiloeiro é feita por comando no servidor (`php artisan auctioneer:suspend`)

## Fase 7: Site institucional e os 3 modelos de loja · 4–6 semanas
RF-H01–H03, D-07

**Depende de:** logo oficial do designer (ou aceite explícito do SVG provisório) + paleta validada na F0 + textos e termos entregues pelo PM/PO.

- [x] Pesquisa de referências com a `taste-skill` (feita dentro do site: D-51)
- [x] Site institucional, refeito com a `taste-skill` (08/11/2026, D-48 a D-51): página estática em `/` (`apps/web/public/index.html`), direção de arte "o produto em ação" (o pregão no painel, o auditório com lista e chat, o registro de lances), 7 fotos licenciadas do Wikimedia Commons com créditos, capturas reais dos 3 modelos, simulador da taxa, FAQ e SEO (JSON-LD com `FAQPage`, Open Graph, `sitemap.xml`, `robots.txt`)
- [x] Modelo **Relógios**, modelo **Roupa** e modelo **Eletrônicos**, cada um implementando os pontos de troca (Header, Hero, ProductCard, ItemCard, AuctionHero, Footer) e a apresentação da ficha técnica livre (08/11/2026, antecipado a pedido: `components/store/templates/`, com a logo e a paleta provisórias; ver T-30)
- [x] Pré-visualização no painel com os componentes reais do modelo e alternância **Celular | Computador**
- [ ] Testes visuais automáticos: 3 modelos × 10 cores × 3 fundos (combinações permitidas), com checagem de contraste
- [x] Rodapé "desenvolvido por Doleleilao.com.br" com UTM (já estava em `components/store/store-footer.tsx`, D-20)
- [ ] Termos de uso e política de privacidade (com revisão jurídica). Os textos padrão já existem (T-32): termos da loja editáveis no painel e política de privacidade em `/privacidade`; falta a revisão jurídica e os termos de uso do leiloeiro com a plataforma

## Cobrança · antes de 01/12/2026 (D-47)
Antecipada da v2 a pedido do cliente. O site já anuncia as faixas.

- [x] Definir com o cliente como a taxa é cobrada: no cartão de crédito do leiloeiro (D-54, 10/11/2026)
- [ ] Trocar o "Em breve, mais informações" do site pelo texto final (P-10)
- [x] Contexto `Billing` com TDD: faixas em `fee_tiers` (com vigência, sem tela de gestão), alíquota pela faixa vigente na hora do arremate, em centavos, e um débito por lote encerrado (`fee_charges` + `award_fees`), gerado na fila e sem duplicar
- [x] Menu **Configurações → Pagamento** no painel (submenu flutuante; no celular, grupo que abre por toque) com o cartão do leiloeiro, sem gateway: guarda só bandeira, 4 últimos dígitos, nome e validade
- [x] Agendar lote exige o cartão cadastrado (aviso no cadastro do lote com o link para Pagamento)
- [x] "Configure o pagamento" como passo 3 dos primeiros passos no dashboard (D-55); o passo do leilão diz o que falta
- [x] Configuração do lote: quem paga a taxa (**Assumir a taxa** ou **Repassar ao vencedor**, `settings.fee_payer`)
- [ ] Como a taxa repassada aparece no auditório, na tela do vencedor e na mensagem ao vencedor (P-10)
- [ ] Integração com o gateway: débito de verdade no cartão, débito recusado, novo cadastro do cartão pelo gateway (P-10)
- [ ] Extrato de taxas no painel do leiloeiro

## Fase 8: Endurecimento, beta e lançamento · 2–3 semanas
- [ ] Teste de carga k6 no VPS: **5 lotes simultâneos × 500 pessoas** (RNF-03 a RNF-05). Ajuste do tamanho do VPS se necessário
- [ ] Revisão de segurança (OWASP ASVS L2), limites de requisição, CAPTCHA invisível (Turnstile) no cadastro do leiloeiro e do participante
- [ ] LGPD: exportação e exclusão de dados, registro de consentimento, CPF criptografado e mascarado
- [ ] Acessibilidade (axe + teste manual com leitor de tela no auditório)
- [ ] Monitoramento: Sentry, verificação de disponibilidade, alertas de disco e memória, teste de restauração do backup
- [ ] Beta fechado com 2 ou 3 leiloeiros reais (um por modelo), coleta de feedback
- [ ] Lançamento v1

---

## v2 (depois do lançamento)
- Painel da equipe Dole Leilão (super admin) (D-28)
- App mobile (Expo/React Native) reaproveitando a API, `design-tokens` e `realtime-contract` + push notifications
- Pagamento do arremate na plataforma
- Equipe do leiloeiro (vários usuários por loja, papéis)
- Domínio próprio do leiloeiro
