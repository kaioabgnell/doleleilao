---
version: 1
slug: "apps-web-src-app-slug-page-tsx"
primary_target: "apps/web/src/app/[slug]/page.tsx"
related_targets: ["apps/web/src/app/[slug]/produto/[produto]/page.tsx","apps/web/src/app/painel/dados-da-empresa/store-preview.tsx"]
---

## Scope and mode
Loja pública do leiloeiro (`apps/web/src/app/[slug]`): home, categoria e página do produto, nos 3 modelos (D-07), + pré-visualização no painel. Mode: Persuade (home) / Operate (produto).

## Audience and job
Participante no celular, vindo de link do WhatsApp: entender em segundos o leilão do dia e entrar. Fotos de celular, mistas.

## Direction contract
- WORLD: os 3 mundos de D-07 (Relógios = joalheria silenciosa; Roupa = revista de moda; Eletrônicos = loja técnica). Cores e fonte de título continuam do leiloeiro (tokens --store-*).
- FIRST VIEWPORT: o leilão em destaque (ao vivo ou o próximo) com a peça real, data/contagem e a ação; sem leilão, o banner ou o nome da loja.
- STRUCTURE: Relógios = vitrine de uma peça, 1 card por linha no celular, foto contida com respiro, ficha em lista espaçada. Roupa = editorial full-bleed com bloco sobreposto, grade 4:5 com peça em destaque, ficha curta junto do título. Eletrônicos = painel do leilão com contagem em blocos e fila de itens rolável, grade densa com specs no card, ficha em tabela.
- SIGNATURE: a foto do item do leilão em destaque no primeiro viewport, emoldurada para funcionar com foto amadora.
- MOTION: entrada única do hero; hover: card sobe 2px e a foto aproxima 1.02-1.03; reduced-motion para tudo.
- RISK: fotos amadoras no recorte 4:5 do modelo Roupa; mitigado com object-position central e fundo neutro.
- seed: none (direção fixada por D-07)

## Constraints
Container queries (preview celular/computador no painel); tokens apenas; acessível (AA); sem carrossel automático.
