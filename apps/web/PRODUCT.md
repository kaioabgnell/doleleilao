# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Participante (comprador):** chega à loja de um leiloeiro quase sempre pelo celular, por um link compartilhado no WhatsApp ou numa rede social, para ver e disputar o leilão do dia. Precisa entender rápido o que vai a leilão, quando começa e quanto custa entrar; depois favorita peças, marca "Quero participar" e volta na hora marcada para dar lances no auditório.
- **Visitante:** vê a loja, os leilões e os itens sem login; não dá lance.
- **Leiloeiro:** pequeno ou médio, hoje leiloa por grupos de WhatsApp e lives. Monta a loja no painel (modelo, logo, cores, fonte, banners, categorias) e precisa que ela pareça dele, profissional, sem saber design.

## Product Purpose

Dar a cada leiloeiro uma loja própria em `doleleilao.com.br/{slug}` e um auditório em tempo real (lances, cronômetro do servidor, chat), substituindo o leilão informal por WhatsApp. Sucesso: o participante chega pelo link, entende o leilão em segundos, se cadastra e dá lances; o leiloeiro tem uma vitrine que passa confiança.

## Positioning

Loja com a marca do leiloeiro (D-01), não um marketplace: um leiloeiro por loja, com o pregão ao vivo e regras de lance auditáveis no centro.

## Operating Context

- Tráfego majoritariamente mobile, vindo de WhatsApp; desktop para quem acompanha o leilão com calma.
- A home da loja prioriza o leilão (próximo ou ao vivo) e só depois o catálogo (confirmado em 08/11/2026).
- Fotos dos produtos são mistas, muitas tiradas de celular, com fundos e proporções variados (confirmado em 08/11/2026): a apresentação precisa funcionar bem com foto amadora.
- Três modelos de loja (D-07): Relógios (luxo, joalheria), Roupa (editorial) e Eletrônicos (técnico). O leiloeiro escolhe o modelo no painel e personaliza cor primária, destaque, fundo e fonte de título a partir de listas fechadas (D-04, D-13, D-14).

## Capabilities and Constraints

- Next.js 16 (Cache Components) + Tailwind 4; cores e fontes só por tokens `--store-*`, `state-*`, `dl-*` (nunca hex em componentes).
- Estados do leilão com cores fixas em todas as lojas (D-05): ganhando, superado, ao vivo, encerrado.
- Só modo claro (D-08); o modelo Relógios oferece o fundo escuro "Ébano" (D-15).
- Ficha técnica livre (D-27): o modelo muda só a apresentação.
- Vídeo só por link do YouTube com fachada leve (D-26).
- Rodapé da loja: "desenvolvido por Doleleilao.com.br" (D-06).
- Meta de performance: Lighthouse mobile ≥ 90, LCP ≤ 2,5 s.

## Brand Commitments

- Duas camadas de marca (D-01): a loja é do leiloeiro; Dole Leilão aparece só no rodapé.
- Textos em pt-BR, diretos.

## Evidence on Hand

- Loja de demonstração `relojoaria-demo` (fotos ilustrativas de relógio).
- Não há depoimentos, números de vendas ou logos de clientes: não inventar.

## Product Principles

1. O leilão é o produto: quem chega pelo link vê primeiro o que vai a pregão e quando.
2. A loja é do leiloeiro: a marca dele lidera, a plataforma some.
3. Funciona com foto de celular: enquadramento contido, nunca dependente de foto profissional.
4. Mobile primeiro, toque grande, nada escondido atrás de hover.
5. Estados de leilão iguais em todas as lojas: o participante reconhece "ganhando" e "superado" em qualquer uma.

## Accessibility & Inclusion

WCAG 2.2 AA (RNF-10): contraste validado por teste em todas as combinações de tema, foco visível, `prefers-reduced-motion`, leitor de tela no lance e no cronômetro.
