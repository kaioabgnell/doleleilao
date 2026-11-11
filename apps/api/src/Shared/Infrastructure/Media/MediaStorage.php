<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Arquivos públicos das lojas (logo, banners, categorias). Disco do VPS + Cloudflare; trocar por um
 * armazenamento S3 depois é só configuração (T-08). Caminho: lojas/{public_id do leiloeiro}/{pasta}/{ulid}.{ext}
 */
final class MediaStorage
{
    public function put(string $auctioneerPublicId, string $folder, string $extension, string $contents): string
    {
        $path = sprintf('lojas/%s/%s/%s.%s', $auctioneerPublicId, $folder, strtolower((string) Str::ulid()), $extension);
        $this->disk()->put($path, $contents, ['visibility' => 'public']);

        return $path;
    }

    public function url(?string $path): ?string
    {
        return $path === null ? null : $this->disk()->url($path);
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('public');
    }
}
