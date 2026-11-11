<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionResource;
use DoleLeilao\Auction\Infrastructure\AuctionReadModel;
use DoleLeilao\Auction\Infrastructure\Eloquent\AuctionRecord;
use DoleLeilao\Shared\Application\EventBus;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Capa do lote: aparece na home, nos cartões e nas listas de leilão. Sem capa enviada, a loja usa a
 * 1ª foto do 1º item. É só apresentação, então pode mudar em qualquer status do lote.
 */
final class AuctionCoverController
{
    use ResolvesAuctioneerUser;

    public function __construct(private readonly MediaStorage $media, private readonly EventBus $events) {}

    /** POST multipart { cover }. */
    public function update(Request $request, string $auction, ImageProcessor $images): AuctionResource
    {
        $record = $this->find($auction);
        $request->validate(['cover' => ['required', 'file', 'max:10240']], [], ['cover' => 'a capa']);
        $file = $request->file('cover');
        assert($file instanceof UploadedFile);

        $cover = $images->toWebp($file->getRealPath(), 1600, 1600, 'cover');
        $thumb = $images->toWebp($file->getRealPath(), 320, 320, 'cover');
        $owner = $this->currentUser($request)->auctioneer->public_id;

        $this->deleteFiles($record);
        $record->cover_path = $this->media->put($owner, 'leiloes', 'webp', $cover->contents);
        $record->cover_thumb_path = $this->media->put($owner, 'leiloes', 'webp', $thumb->contents);
        $record->save();

        return $this->changed($record);
    }

    public function destroy(string $auction): AuctionResource
    {
        $record = $this->find($auction);
        $this->deleteFiles($record);
        $record->cover_path = null;
        $record->cover_thumb_path = null;
        $record->save();

        return $this->changed($record);
    }

    private function deleteFiles(AuctionRecord $record): void
    {
        $this->media->delete($record->cover_path);
        $this->media->delete($record->cover_thumb_path);
    }

    private function changed(AuctionRecord $record): AuctionResource
    {
        $this->events->publish(new StorefrontChanged($record->auctioneer_id));
        $record->load(['items.product.photos', 'items.leader'])->loadCount('participations');
        AuctionReadModel::attachCovers([$record]);

        return new AuctionResource($record);
    }

    private function find(string $publicId): AuctionRecord
    {
        return AuctionRecord::query()->where('public_id', $publicId)->firstOrFail();
    }
}
