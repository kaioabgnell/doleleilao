<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Domain;

/**
 * Vídeo do produto: só por link do YouTube (D-26, RF-C03). Guarda o ID de 11 caracteres;
 * o site monta o player (com fachada leve) a partir dele.
 */
final readonly class YouTubeVideoId
{
    private const array HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtu.be'];

    private function __construct(public string $value) {}

    public static function fromUrl(string $input): self
    {
        $url = trim($input);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $id = null;
        if (in_array($host, self::HOSTS, true)) {
            if (str_ends_with($host, 'youtu.be')) {
                $id = trim($path, '/');
            } elseif ($path === '/watch') {
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#^/(shorts|embed|live|v)/([^/]+)#', $path, $m) === 1) {
                $id = $m[2];
            }
        }

        if ($id === null || preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
            throw new InvalidProduct('youtube_url', 'Cole o link de um vídeo do YouTube.');
        }

        return new self($id);
    }

    public static function fromId(string $id): self
    {
        return self::fromUrl("https://youtu.be/{$id}");
    }

    public function watchUrl(): string
    {
        return "https://www.youtube.com/watch?v={$this->value}";
    }
}
