<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Shared\Infrastructure\Media\SvgSanitizer;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Logo da loja (RF-B02): SVG (sanitizado) ou imagem de até 2 MB. O corte e o enquadramento
 * acontecem no painel antes do envio; aqui a imagem é reprocessada em WebP.
 */
final class LogoController
{
    use ResolvesAuctioneerUser;

    public const int MAX_KB = 2048;

    public function store(Request $request, ImageProcessor $images, SvgSanitizer $svg, MediaStorage $media): JsonResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:'.self::MAX_KB]]);
        /** @var UploadedFile $file */
        $file = $request->file('logo');
        $auctioneer = $this->currentUser($request)->auctioneer->load('storefront');
        $settings = $auctioneer->storefront ?? abort(404);

        $isSvg = in_array((new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()), ['image/svg+xml', 'text/xml', 'text/plain'], true)
            && strtolower($file->getClientOriginalExtension()) === 'svg';

        if ($isSvg) {
            $path = $media->put($auctioneer->public_id, 'logo', 'svg', $svg->sanitize($file->getRealPath(), 'logo'));
            $kind = 'svg';
        } else {
            $image = $images->toWebp($file->getRealPath(), 800, 400, 'logo', 90);
            $path = $media->put($auctioneer->public_id, 'logo', 'webp', $image->contents);
            $kind = 'webp';
        }

        $media->delete($settings->logo_path);
        $settings->forceFill(['logo_path' => $path, 'logo_kind' => $kind])->save();
        event(new StorefrontChanged($auctioneer->id));

        return response()->json(['kind' => $kind, 'url' => $media->url($path)], 201);
    }

    public function destroy(Request $request, MediaStorage $media): JsonResponse
    {
        $auctioneer = $this->currentUser($request)->auctioneer->load('storefront');
        $settings = $auctioneer->storefront ?? abort(404);

        $media->delete($settings->logo_path);
        $settings->forceFill(['logo_path' => null, 'logo_kind' => 'none'])->save();
        event(new StorefrontChanged($auctioneer->id));

        return response()->json(null, 204);
    }
}
