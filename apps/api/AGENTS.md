# API (Laravel 13, PHP 8.4)

Siga também o `CLAUDE.md` da raiz do repositório.

- **Não instale PHP nem altere o PHP global.** Este projeto usa o PHP 8.4 local de `.tools/` por meio dos scripts da raiz: `../../bin/php`, `../../bin/composer` e `../../bin/artisan`. O `php` do terminal é 8.1 e não serve.
- Sem Docker (D-41): MySQL 9.4 e Redis do Homebrew, locais. Bancos `doleleilao` e `doleleilao_test`.
- Arquitetura: `src/<Contexto>/{Domain,Application,Infrastructure}`. `Domain` é PHP puro (sem `Illuminate\*`), e o Deptrac verifica isso (`deptrac.yaml`).
- Testes com Pest (`../../bin/php vendor/bin/pest`). Regras de lance com TDD.
