<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

use DoleLeilao\Shared\Domain\Slugify;

/**
 * Endereço da loja: doleleilao.com.br/{slug} (RF-A02).
 * Aceita a-z, 0-9 e hífen (sem hífen nas pontas nem repetido), de 3 a 40 caracteres,
 * e não pode ser uma rota do próprio site.
 */
final readonly class StoreSlug
{
    public const int MIN = 3;

    public const int MAX = 40;

    /** Rotas e nomes do site que não podem virar loja. */
    public const array RESERVED = [
        'admin', 'ajuda', 'api', 'app', 'assets', 'auth', 'blog', 'cadastro', 'carreiras', 'conta', 'contato',
        'dashboard', 'design-system', 'docs', 'dole', 'dole-leilao', 'doleleilao', 'entrar', 'esqueci-senha',
        'favicon', 'horizon', 'imagens', 'institucional', 'leilao', 'leiloes', 'login', 'logout', 'loja', 'lojas',
        'mail', 'minha-conta', 'modelos', 'painel', 'planos', 'precos', 'privacidade', 'redefinir-senha',
        'robots', 'sair', 'sanctum', 'sitemap', 'sobre', 'staging', 'static', 'status', 'storage', 'suporte',
        'telescope', 'termos', 'up', 'verificar-email', 'www',
    ];

    private function __construct(public string $value) {}

    public static function fromString(string $input): self
    {
        $slug = strtolower(trim($input));

        if (strlen($slug) < self::MIN || strlen($slug) > self::MAX
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw InvalidSlug::format();
        }

        if (in_array($slug, self::RESERVED, true)) {
            throw InvalidSlug::reserved();
        }

        return new self($slug);
    }

    /** Sugestão a partir do nome da empresa (sem acentos, palavras unidas por hífen). */
    public static function suggestFrom(string $companyName): ?self
    {
        $slug = Slugify::from($companyName, self::MAX);
        if ($slug === '') {
            return null;
        }

        if (strlen($slug) < self::MIN || in_array($slug, self::RESERVED, true)) {
            $slug .= '-leiloes';
        }

        return self::fromString($slug);
    }

    /** "casa-silva" → "casa-silva-2", cortando a base se passar de 40 caracteres. */
    public function withSuffix(int $n): self
    {
        $suffix = '-'.$n;
        $base = rtrim(substr($this->value, 0, self::MAX - strlen($suffix)), '-');

        return self::fromString($base.$suffix);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
