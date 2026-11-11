# Dole Leilão

SaaS para leiloeiros criarem a própria loja de leilões online, com auditório ao vivo, lances em tempo real e chat.

- Planejamento completo: [specs/](specs/README.md)
- Glossário do domínio: [CONTEXT.md](CONTEXT.md)
- Briefing original: [funcionalidades do Leilaov2.md](funcionalidades%20do%20Leilaov2.md)

## Estrutura

```
apps/web/                 Next.js 16 (site institucional, lojas, auditório, painel)
apps/api/                 Laravel 13 + PHP 8.4 (API REST, WebSocket via Reverb, filas via Horizon)
packages/design-tokens/   Tokens de design + regras de tema das lojas (fonte única web/app)
bin/                      php, composer e artisan usando o PHP 8.4 do projeto
infra/local/php.ini       Configuração do PHP local
.tools/                   PHP 8.4 estático e Composer (não versionado)
refs/                     Logo (mockup) e logo provisória em SVG
specs/                    Planejamento
.claude/skills/           Skills do projeto (ver skills-lock.json)
```

## Ambiente local (sem Docker)

Tudo roda direto neste Mac (decisão D-41):

| Peça | Versão | Origem |
|---|---|---|
| PHP | 8.4.23 | `.tools/php/php` (static-php-cli). O `php` do terminal (8.1) não é usado |
| Composer | 2.11 | `.tools/composer.phar` |
| MySQL | 9.4 | Homebrew (`brew services start mysql`). Bancos `doleleilao` e `doleleilao_test` |
| Redis | 8.2 | Homebrew (já roda como serviço). Bancos 4 e 5, prefixo `doleleilao_` |
| Node | 24 | `.nvmrc` |

### Comandos

```bash
npm install                    # dependências do site e dos tokens
bin/composer install -d apps/api

npm run dev                    # sobe tudo: API :8000, Reverb :8085, Horizon, relógio dos leilões, agendador, site :3000
npm test                       # testes dos tokens + testes da API (Pest)
npm run lint                   # ESLint + Pint + PHPStan + Deptrac

bin/artisan migrate            # qualquer comando artisan com o PHP 8.4
bin/artisan migrate:fresh --seed   # banco do zero + leiloeiro demo
```

Configuração do site: copie `apps/web/.env.example` para `apps/web/.env.local` e use em `REVALIDATE_SECRET` o mesmo valor de `apps/api/.env` (é o que faz a loja pública mudar na hora quando o leiloeiro salva).

Leiloeiro de demonstração (seed): `demo@doleleilao.com.br` / `demo12345`, loja `relojoaria-demo`. Os e-mails (confirmação, nova senha) saem na fila: com `MAIL_MAILER=log`, aparecem em `apps/api/storage/logs/laravel.log` depois que o Horizon processa.

| Endereço | O quê |
|---|---|
| http://localhost:3000 | Site (Next.js). Painel do leiloeiro: `/cadastro`, `/entrar`, `/painel`. Página interna: `/design-system` |
| http://localhost:8000/api/v1/health | Saúde da API (banco e Redis) |
| http://localhost:8000/docs/api | Documentação da API (gerada pelo Scramble) |
| http://localhost:8000/horizon | Painel das filas (só no ambiente local) |
| ws://localhost:8085 | WebSocket (Reverb) |

### Reinstalar o PHP do projeto (se `.tools/` for apagado)

```bash
mkdir -p .tools/php && cd .tools/php
curl -fsSLO https://dl.static-php.dev/static-php-cli/bulk/php-8.4.23-cli-macos-x86_64.tar.gz
tar -xzf php-8.4.23-cli-macos-x86_64.tar.gz && rm php-8.4.23-cli-macos-x86_64.tar.gz
cd .. && curl -fsSLo composer.phar https://getcomposer.org/download/latest-stable/composer.phar
```
