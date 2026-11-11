# Dole Leilão

Leia antes de mudar algo: `specs/README.md` (índice do planejamento), `CONTEXT.md` (glossário) e `specs/10-decisoes.md` (decisões já tomadas: não reabra sem o usuário pedir).

- Idioma: interface, specs e conversas em pt-BR. Identificadores de código em inglês, usando os nomes do `CONTEXT.md` (`Auction` = lote do dia, `AuctionItem` = item).
- Monorepo npm workspaces: `apps/web` (Next.js 16), `packages/design-tokens`. `apps/api` (Laravel 13). **Sem Docker** (D-41). O `php` do terminal é 8.1 e o do XAMPP 8.0: para o projeto use sempre `bin/php`, `bin/composer` e `bin/artisan` (PHP 8.4 estático em `.tools/`). Não instale nem troque o PHP global.
- Next.js 16 tem APIs diferentes do que você conhece: siga `apps/web/AGENTS.md` e consulte `node_modules/next/dist/docs/`.
- Confirmações e modais: `useConfirm()` / `Modal` de `apps/web/src/components/ui/dialog.tsx`. Nunca `window.confirm` ou `alert`.
- Cores e fontes: nunca use hex direto em componentes. Use as classes semânticas (`dl-*`, `store-*`, `state-*`) de `apps/web/src/app/globals.css`, que vêm de `@doleleilao/design-tokens`. Mudou tokens? Rode `npm test` (valida o contraste de todas as combinações de tema).
- Backend: Clean Architecture. `Domain` é PHP puro, sem Laravel. Regras de lance: `specs/03-dominio.md#regras-de-lance`, implementadas com TDD.
- Valores em centavos (inteiro, `DoleLeilao\Shared\Domain\Money`). Datas em UTC (porta `Clock`).
- Comandos: `npm run dev` (API, Reverb, Horizon, agendador e site), `npm test` (tokens + API), `npm run lint` (ESLint, Pint, PHPStan, Deptrac).
- Ao fechar uma fase, marque os itens em `specs/08-roadmap.md`.
