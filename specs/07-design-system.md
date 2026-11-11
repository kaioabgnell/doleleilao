# 07 — Design system

Decisões fechadas em 07/11/2026 (ver [10-decisoes.md](10-decisoes.md), D-01 a D-20). Este é o ponto de partida. A skill **ui-ux-pro-max** valida e completa este arquivo na Fase 0, sem trocar as cores-chave (D-12).

## Duas camadas de marca

| Camada | Onde aparece | Quem define |
|---|---|---|
| **Dole Leilão** | Site institucional, cadastro e login do leiloeiro, painel admin, e-mails da plataforma | Fixo (este documento) |
| **Loja do leiloeiro** | `/{slug}`, auditório, área do participante, e-mails da loja | O modelo escolhido + a personalização do leiloeiro |

A marca Dole Leilão aparece nas lojas **só** no rodapé: "desenvolvido por Doleleilao.com.br", com link para `https://doleleilao.com.br/?utm_source={slug}&utm_medium=footer` (D-06, D-20).
Nos textos corridos, o nome se escreve **Dole Leilão** (D-19). O endereço "Doleleilao.com.br" é usado quando a referência é o site.

## Logo Dole Leilão

| Arquivo | Status |
|---|---|
| [`refs/logo-doleleilao.png`](../refs/logo-doleleilao.png) | Mockup original (512 px, fundo cinza com linhas-guia). **Não usar em produção** |
| [`refs/logo-provisoria/logo-vertical.svg`](../refs/logo-provisoria/logo-vertical.svg) | Provisório: símbolo + "DOLE / LEILÃO" empilhados |
| [`refs/logo-provisoria/logo-horizontal.svg`](../refs/logo-provisoria/logo-horizontal.svg) | Provisório: para cabeçalhos |
| [`refs/logo-provisoria/logo-vertical-negativo.svg`](../refs/logo-provisoria/logo-vertical-negativo.svg) | Provisório: para fundo escuro |
| [`refs/logo-provisoria/simbolo.svg`](../refs/logo-provisoria/simbolo.svg) | Provisório: só o "D" com as setas (favicon, avatar) |

Os SVGs provisórios são um redesenho aproximado feito a partir do mockup, e os textos não estão convertidos em curvas (dependem da Montserrat). Eles valem até a chegada dos arquivos oficiais.

**Pendência para o designer** (entregáveis da marca, D-09/D-17):
- [ ] SVG horizontal e vertical, com textos em curvas
- [ ] Versão positiva (fundo claro) e negativa (fundo escuro), além de uma versão monocromática
- [ ] Símbolo isolado
- [ ] Favicon (SVG + ICO 32 px) e `apple-touch-icon` 180 px
- [ ] Ícone de app 1024 × 1024 px, sem transparência, com área de segurança para o recorte do Android
- [ ] Teste do til de "LEILÃO" a 16 px e 24 px de altura

## Paleta Dole Leilão (extraída da logo)

| Token | Hex | Uso | Contraste |
|---|---|---|---|
| `brand-copper-400` | `#B07A4F` | Só em degradês e decoração (topo do "D") | 3,7:1 com branco: **não usar em texto** |
| `brand-copper-500` | `#9C6642` | Ícones, textos ≥ 24 px (tom dominante da logo) | 4,8:1 |
| `brand-copper-600` | `#8A5A38` | **Botões primários, links** | 5,8:1 com branco |
| `brand-copper-800` | `#7A4A2B` | Hover/pressionado, base do degradê | 7,4:1 |
| `ink-900` | `#2F3B3E` | Texto principal, títulos (grafite do "DOLE") | 11,6:1 |
| `ink-*` | escala 50–800 | Bordas, texto secundário, desabilitado. Cinzas puxando levemente para o azul-esverdeado do grafite | gerada na Fase 0 |
| `ink-950` | `#1E2628` | Palco escuro da tela de entrada (D-53) | ink-50 14,2:1, ink-300 8,1:1 |
| `lime` | `#C5F36A` | Destaque **só sobre o grafite** (`ink-900`/`ink-950`): faixas escuras do site e palco da tela de entrada (D-49, D-53). Nunca no fundo claro | 12,0:1 sobre `ink-950` |
| `surface-page` | `#F6F5F2` | Fundo das páginas (off-white quente) | |
| `surface-card` | `#FFFFFF` | Cartões, modais, campos | |

Degradê da marca (para o símbolo e destaques decorativos): `linear-gradient(135deg, #B07A4F 0%, #9C6642 50%, #7A4A2B 100%)`.

**Modo:** só claro na v1 (D-08).

### Site institucional (D-49 a D-51)

O site em `/` é uma página estática feita com a taste-skill (`apps/web/public/index.html`) e tem o próprio sistema:
os tokens `--p-*` no `:root` da página (gerados por `start.mjs brand`) e o kit em `public/assets/`. Não usa as classes
`dl-*` nem o Tailwind.

| Token | Hex | Uso |
|---|---|---|
| `--p-bg` | `#FAF9F6` | Fundo da página (papel) |
| `--p-surface` | `#F1EEE8` | Cartões e painéis |
| `--p-ink` | `#2F3B3E` | Texto e a faixa escura (Modelos) |
| `--p-muted` | `#616A6B` | Texto secundário (4,5:1 ou mais) |
| `--p-action` / `--p-accent` | `#8A5A38` | O único destaque: botão principal, links e foco (5,8:1 com o texto claro) |

Tipografia: Host Grotesk nos títulos (500, tracking -3,5%) e no texto; JetBrains Mono só em números de interface.

## Cores de estado do leilão (fixas, iguais em todas as lojas)

O leiloeiro não personaliza estas cores (D-05).

| Token | Hex | Significado | Forma de uso |
|---|---|---|---|
| `state-winning` | `#1F7A4D` | Você está ganhando | Faixa ou selo preenchido com texto branco (5,3:1) |
| `state-outbid` | `#C0362C` | Você foi superado | Faixa preenchida + vibração curta no celular |
| `state-live` | `#C0362C` | AO VIVO | Selo com ponto pulsante (sem pulso com `prefers-reduced-motion`) |
| `state-closed` | `#6B7280` | Encerrado | Selo cinza |

O âmbar não é usado nos estados porque se confunde com o cobre da marca e com a cor Cobre da loja. Um estado nunca é comunicado só pela cor: sempre vai junto um ícone e um texto.

## Tipografia

| Contexto | Títulos | Texto, interface e números |
|---|---|---|
| Dole Leilão (institucional + painel) | **Montserrat** 600/700 (conversa com o "DOLE" da logo) | **Inter** 400/500/600 |
| Lojas | A fonte escolhida pelo leiloeiro (abaixo) | **Inter**, sempre |

- Números de lance, preço e cronômetro: Inter com `font-variant-numeric: tabular-nums` (algarismos de largura igual, para o valor não "pular").
- Escala mobile first (base 16 px): 12 · 14 · 16 · 18 · 20 · 24 · 30 · 36 · 48. O lance atual no auditório fica entre 36 e 48 px.
- Espaçamento em base 4 px: 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64. Raio: 6 (campos), 10 (cartões), 999 (selos). Margem lateral mobile: 16 px.

## Personalização do leiloeiro

### Paleta do leiloeiro (10 cores para primária e destaque)

Todas já passam no contraste com texto branco por cima e funcionam em fundo claro (D-04, D-13):

| Chave | Nome | Hex | Branco sobre a cor |
|---|---|---|---|
| `copper` | Cobre | `#8A5A38` | 5,8:1 |
| `graphite` | Grafite | `#2F3B3E` | 11,6:1 |
| `black` | Preto | `#111111` | 18,9:1 |
| `navy` | Azul-marinho | `#1E3A5F` | 11,5:1 |
| `blue` | Azul | `#1D5FBF` | 6,1:1 |
| `emerald` | Esmeralda | `#0F6B4F` | 6,5:1 |
| `wine` | Vinho | `#7A1F2B` | 10,2:1 |
| `purple` | Roxo | `#5B3A8C` | 8,7:1 |
| `petrol` | Petróleo | `#0E5E6F` | 7,4:1 |
| `dark-gold` | Dourado-escuro | `#8A6A12` | 5,1:1 |

O sistema gera os tons de hover, pressionado e fundo suave de cada cor. Para links em fundos creme, ele escurece o tom até passar 4,5:1 (o Dourado sobre Areia, por exemplo, fica em 4,4:1 sem ajuste).

### Fundos (3 por modelo, D-13/D-15)

| Modelo | Opção 1 | Opção 2 | Opção 3 |
|---|---|---|---|
| Relógios | Branco `#FFFFFF` | Marfim `#F7F4EE` | **Ébano `#14161A` (escuro)** |
| Roupa | Branco `#FFFFFF` | Areia `#F4EFE9` | Névoa `#F2F2F2` |
| Eletrônicos | Branco `#FFFFFF` | Gelo `#F3F5F8` | Grafite-claro `#EEF0F2` |

**Bloqueio de combinações:** com o fundo Ébano, só as cores com contraste ≥ 3:1 contra o fundo podem ser a primária, que é usada nos botões: **Cobre (3,1:1) e Dourado-escuro (3,6:1)**. As outras 8 aparecem desabilitadas, com a explicação "pouco contraste com fundo escuro". A cor de destaque segue a mesma regra. No fundo Ébano, o texto usa `#F2EFEA` (15,8:1) e as cores de estado aparecem como selos preenchidos.

### Fontes de título (D-14)

Inter · Roboto · Poppins · DM Sans · Playfair Display. A mesma lista vale para os 3 modelos, e cada modelo sugere uma como padrão. A fonte escolhida vale para títulos, nomes de item e a logo de texto.

### Logo do leiloeiro

- Envio de SVG ou PNG de até 2 MB, com corte na hora do envio. Área da logo no cabeçalho: até 160 × 48 px no mobile e 220 × 64 px no desktop.
- **Sem logo:** mostra o **Nome de exibição** (≤ 2 palavras, ≤ 24 caracteres, D-10) na fonte de título escolhida, na cor primária.

### Avatar do participante (D-38)

A foto do participante é opcional. Quem não quiser enviar foto escolhe um **avatar** de uma galeria fixa:
- **12 avatares** ilustrados em SVG, neutros (sem gênero ou idade marcados), num estilo único e simples, feitos na Fase 2 com a ui-ux-pro-max.
- O fundo do avatar usa a cor **primária da loja**, então a galeria combina com qualquer personalização.
- Sem foto e sem avatar, aparecem as **iniciais** do nome sobre o fundo `ink-100`.
- Onde aparece: chat do auditório, "quem está ganhando", "Minha conta" e lista de participantes no painel.

### E-mails

- Modelo único em `apps/api/resources/views/mail/branded.blade.php` (tabelas e estilos inline, fundo `#F6F5F2`, cartão branco, largura máx. 560 px), aplicado por `MailBrand` (T-26).
- E-mails da loja (para o participante): nome/logo e botão na cor primária do leiloeiro; rodapé "desenvolvido por Doleleilao.com.br". E-mails da plataforma (para o leiloeiro): marca Dole Leilão em cobre.
- O texto continua no `MailMessage` (saudação, linhas, botão); a versão em texto puro sai junto.

### Modais e confirmações

- Componente único: `apps/web/src/components/ui/dialog.tsx`. `Modal` usa o `<dialog>` nativo (foco preso, Esc fecha, fundo escurecido); no celular abre como folha de baixo, no computador fica centralizado; entrada de 160 ms, desligada com "reduzir movimento".
- Confirmações com `useConfirm()` (o `ConfirmProvider` está no layout do painel). **Nunca `window.confirm` ou `alert`.**
- Título em pergunta curta ("Pular este item?"), descrição com a consequência, botão de ação com o verbo ("Pular item"), nunca "OK".
- Ação destrutiva (`tone: "danger"`): ícone e botão na cor de estado `state-outbid`, e o foco começa em "Cancelar". Ação comum: botão cobre e foco no botão de ação.

## Os 3 modelos de loja (D-07)

Cada modelo tem visual base próprio (layout, ritmo, tratamento de imagem, microinterações). A personalização do leiloeiro muda os destaques, o fundo e a fonte dos títulos.

| | **Relógios** | **Roupa** | **Eletrônicos** |
|---|---|---|---|
| Clima | Luxo, joalheria, silêncio | Editorial, revista de moda | Técnico, confiável, objetivo |
| Fonte sugerida | Playfair Display | DM Sans | Inter |
| Fundo sugerido | Marfim (ou Ébano) | Branco | Gelo |
| Cor sugerida | Cobre / Dourado-escuro | Preto | Azul |
| Fotos | Produto isolado, muito respiro, zoom de detalhe | Fotos grandes e verticais (4:5), grade assimétrica | Grade densa, fundo neutro, galeria com ângulos |
| Apresentação da ficha técnica | Lista elegante, espaçada | Lista curta junto do título | Tabela compacta, em destaque |
| Card de item | Grande, 1 por linha no mobile | 2 por linha, foto dominante | 2 por linha, preço e specs em destaque |

### Como cada modelo organiza a loja (T-30)

| | **Relógios** (vitrine de joalheria) | **Roupa** (revista de moda) | **Eletrônicos** (loja técnica) |
|---|---|---|---|
| Cabeçalho | Marca centralizada, menu em caixa alta, poucos links | Marca à esquerda, categorias na linha (faixa rolável no celular) | Busca no topo, barra de categorias com "Leilões" em destaque e "Quero leiloar" |
| Início | Peça principal do leilão grande, nome em serifa, contagem discreta, miniaturas "Nesta sessão" | Mosaico de fotos com o cartão do leilão sobreposto; categorias em blocos com imagem; próximos leilões em faixa horizontal | Painel do leilão (data, contagem em bloco, itens, lance mínimo) + trilho dos itens; agenda em tabela |
| Grade | 1 por linha no celular, até 3 no computador, muito respiro | 2 a 4 por linha, foto 4:5; a primeira peça ocupa 2×2 no computador | 2 a 5 por linha, cards com borda, 2 specs e preço em negrito |
| Página do produto | Galeria + lista espaçada "Detalhes da peça" | Fotos empilhadas (deslizar no celular), informações fixas ao rolar | Galeria com miniaturas laterais, caixa de valor, ficha em tabela zebrada |

Regras comuns: nada de carrossel automático, sem legenda embaixo de foto, foto ampliada 1.03 e card subindo 2 px ao passar o mouse (desligado com "reduzir movimento"), cantos de 12 a 16 px. A contagem regressiva só aparece nas 48 h antes do leilão e roda só no navegador.

**A ficha técnica é livre** (D-27): o leiloeiro cria os pares nome/valor que quiser, porque as lojas vendem produtos de ramos diferentes. O modelo muda só a **apresentação** da ficha, nunca os campos. O vídeo do produto é sempre um link do YouTube, exibido com fachada leve.

## Uso das skills

| Skill | Quando | Para quê |
|---|---|---|
| `nextlevelbuilder/ui-ux-pro-max-skill@ui-ux-pro-max` | Fase 0 | Validar paleta, pares de fontes, escala de espaçamento e regras de UX. Gerar os tokens finais |
| `pbakaus/impeccable` + `emilkowalski/skills` | Fase 7 | Construir o site institucional e os 3 modelos (acabamento, animações, microinterações) |
| `leonxlnx/taste-skill` | Fase 7 | Referências de sites reais por modelo (relojoarias, e-commerce de moda e de eletrônicos) |
| `wondelai/skills@clean-architecture` + `@domain-driven-design` | Fases 0–6 | Estrutura do backend ([04](04-arquitetura.md), [03](03-dominio.md)) |

## Validação pela ui-ux-pro-max (07/11/2026)

Consulta: "online auction marketplace live bidding premium copper trust mobile-first".

**Adotado** (compatível com as decisões):
- Estados do lance sempre visíveis (ganhando, superado), em verde e vermelho. Já está em D-05.
- Só chamar algo de "ao vivo" quando houver conexão ativa. Mostrar a hora da última atualização e um estado "desatualizado/reconectando" no auditório. Isso entra no RF-F11.
- Com `prefers-reduced-motion`, mostrar um retrato estático no lugar das animações (cronômetro e confetes).
- Ícones em SVG (Lucide), nunca emoji como ícone. Emoji só dentro do chat.
- Foco visível em tudo, transições de hover entre 150 e 300 ms, e testes em 375, 768, 1024 e 1440 px.

**Descartado** (conflita com decisões já tomadas):
- Tema escuro "OLED" com primária azul-marinho e CTA verde. Descartado por D-08 (só claro), D-12 (paleta da logo) e D-05 (o verde é exclusivo do estado "ganhando").
- Par Inter (títulos) + Playfair (texto). Descartado por D-18 (Montserrat + Inter na marca). Nas lojas, a Playfair segue disponível só para títulos (D-14).

## Tokens: formato

`packages/design-tokens/tokens.json` (formato W3C Design Tokens) gera:
- `tokens.css`, com as variáveis `--dl-*` (marca Dole Leilão) e `--store-*` (loja, sobrescritas por tenant em tempo de execução);
- `tokens.ts`, para o futuro app React Native.

Os componentes **só** usam variáveis semânticas (`--store-primary`, `--surface`, `--text`, `--state-winning`), nunca um hex direto.
