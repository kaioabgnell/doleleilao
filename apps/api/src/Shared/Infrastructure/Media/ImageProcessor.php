<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Media;

use DoleLeilao\Shared\Domain\InvalidUpload;

/**
 * Reprocessa imagens enviadas (specs/04 segurança): confere o tipo real pelos bytes, reduz ao tamanho
 * máximo e regrava em WebP. A regravação descarta EXIF/GPS e qualquer conteúdo escondido no arquivo.
 */
final class ImageProcessor
{
    private const array ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

    private const int MAX_PIXELS = 40_000_000; // evita "bombas" de descompressão

    /**
     * Vários tamanhos de uma vez (fotos de produto, RF-C02). Cada tamanho sai em WebP e, se pedido, AVIF.
     *
     * @param  array<string, array{width: int, avif: bool}>  $sizes
     * @return array<string, array{webp: string, avif: string|null, width: int, height: int}>
     */
    public function variants(string $path, array $sizes, string $field): array
    {
        $source = $this->load($path, $field);
        $out = [];
        foreach ($sizes as $name => $spec) {
            $image = $this->resize($source, $spec['width'], $spec['width'] * 4);
            $out[$name] = [
                'webp' => $this->encode($image, 'webp', 80),
                'avif' => $spec['avif'] && function_exists('imageavif') ? $this->encode($image, 'avif', 55) : null,
                'width' => imagesx($image),
                'height' => imagesy($image),
            ];
        }

        return $out;
    }

    public function toWebp(string $path, int $maxWidth, int $maxHeight, string $field, int $quality = 82): ProcessedImage
    {
        $target = $this->resize($this->load($path, $field), $maxWidth, $maxHeight);

        return new ProcessedImage($this->encode($target, 'webp', $quality), imagesx($target), imagesy($target));
    }

    private function load(string $path, string $field): \GdImage
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, self::ACCEPTED, true)) {
            throw new InvalidUpload($field, 'Envie uma imagem JPG, PNG ou WebP.');
        }

        $size = @getimagesize($path);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new InvalidUpload($field, 'Não conseguimos ler esta imagem. Tente outro arquivo.');
        }

        $bytes = file_get_contents($path);
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new InvalidUpload($field, 'Não conseguimos ler esta imagem. Tente outro arquivo.');
        }

        return $source;
    }

    private function resize(\GdImage $source, int $maxWidth, int $maxHeight): \GdImage
    {
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            throw new \RuntimeException('GD: não foi possível criar a imagem.');
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function encode(\GdImage $image, string $format, int $quality): string
    {
        ob_start();
        $format === 'avif' ? imageavif($image, null, $quality, 6) : imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }
}
