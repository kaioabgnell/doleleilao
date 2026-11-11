<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Problem;
use App\Http\Resources\BannerResource;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Domain\BannerSchedule;
use DoleLeilao\Storefront\Domain\StorefrontChanged;
use DoleLeilao\Storefront\Infrastructure\Eloquent\BannerRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Banners da loja (RF-B06). Imagem do celular obrigatória (4:5, até 1080 px de largura);
 * do computador opcional (3:1, até 1920 px). Envio em multipart; a edição usa POST.
 */
final class BannerController
{
    use ResolvesAuctioneerUser;

    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(BannerRecord::query()->orderBy('position')->orderBy('id')->get());
    }

    public function store(Request $request, ImageProcessor $images, MediaStorage $media): JsonResponse
    {
        $request->validate($this->rules(creating: true));
        if (BannerRecord::query()->count() >= BannerRecord::MAX_PER_STORE) {
            return Problem::validation(['image_mobile' => ['Sua loja já tem o máximo de '.BannerRecord::MAX_PER_STORE.' banners.']]);
        }
        $schedule = new BannerSchedule($this->date($request, 'starts_at'), $this->date($request, 'ends_at'));
        $max = BannerRecord::query()->max('position');

        $banner = BannerRecord::query()->create([
            'title' => $request->string('title')->trim()->value(),
            'link_url' => $this->link($request),
            'starts_at' => $schedule->startsAt,
            'ends_at' => $schedule->endsAt,
            'active' => $request->boolean('active', true),
            'position' => (is_numeric($max) ? (int) $max : 0) + 1,
            'image_mobile' => $this->storeImage($request, 'image_mobile', 1080, 1350, $images, $media),
            'image_desktop' => $this->storeImage($request, 'image_desktop', 1920, 640, $images, $media),
        ]);
        $this->changed($request);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $banner, ImageProcessor $images, MediaStorage $media): BannerResource
    {
        $record = BannerRecord::query()->where('public_id', $banner)->firstOrFail();
        $request->validate($this->rules(creating: false));

        if ($request->has('title')) {
            $record->title = $request->string('title')->trim()->value();
        }
        if ($request->has('link_url')) {
            $record->link_url = $this->link($request);
        }
        if ($request->has('active')) {
            $record->active = $request->boolean('active');
        }
        if ($request->has('starts_at') || $request->has('ends_at')) {
            $schedule = new BannerSchedule(
                $request->has('starts_at') ? $this->date($request, 'starts_at') : $record->starts_at?->toDateTimeImmutable(),
                $request->has('ends_at') ? $this->date($request, 'ends_at') : $record->ends_at?->toDateTimeImmutable(),
            );
            $record->starts_at = $schedule->startsAt === null ? null : Carbon::instance($schedule->startsAt);
            $record->ends_at = $schedule->endsAt === null ? null : Carbon::instance($schedule->endsAt);
        }
        if ($request->hasFile('image_mobile')) {
            $media->delete($record->image_mobile);
            $record->image_mobile = $this->storeImage($request, 'image_mobile', 1080, 1350, $images, $media) ?? $record->image_mobile;
        }
        if ($request->hasFile('image_desktop')) {
            $media->delete($record->image_desktop);
            $record->image_desktop = $this->storeImage($request, 'image_desktop', 1920, 640, $images, $media);
        }
        if ($request->boolean('remove_image_desktop')) {
            $media->delete($record->image_desktop);
            $record->image_desktop = null;
        }
        $record->save();
        $this->changed($request);

        return new BannerResource($record);
    }

    public function destroy(Request $request, string $banner, MediaStorage $media): JsonResponse
    {
        $record = BannerRecord::query()->where('public_id', $banner)->firstOrFail();
        $media->delete($record->image_mobile);
        $media->delete($record->image_desktop);
        $record->delete();
        $this->changed($request);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): AnonymousResourceCollection
    {
        $request->validate(['ids' => ['required', 'array', 'max:'.BannerRecord::MAX_PER_STORE], 'ids.*' => ['string', 'size:26']]);
        $ids = array_values(array_filter($request->collect('ids')->all(), 'is_string'));

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $publicId) {
                BannerRecord::query()->where('public_id', $publicId)->update(['position' => $position + 1]);
            }
        });
        $this->changed($request);

        return $this->index();
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:2', 'max:120'],
            'image_mobile' => [$creating ? 'required' : 'nullable', 'file', 'max:10240'],
            'image_desktop' => ['nullable', 'file', 'max:10240'],
            'link_url' => ['nullable', 'string', 'max:255', 'regex:#^(https://|/)#'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'remove_image_desktop' => ['sometimes', 'boolean'],
        ];
    }

    private function date(Request $request, string $field): ?\DateTimeImmutable
    {
        $value = $request->string($field)->trim()->value();

        return $value === '' ? null : Carbon::parse($value)->utc()->toDateTimeImmutable();
    }

    private function link(Request $request): ?string
    {
        $link = $request->string('link_url')->trim()->value();

        return $link === '' ? null : $link;
    }

    private function storeImage(Request $request, string $field, int $maxWidth, int $maxHeight, ImageProcessor $images, MediaStorage $media): ?string
    {
        $file = $request->file($field);
        if (! $file instanceof UploadedFile) {
            return null;
        }
        $image = $images->toWebp($file->getRealPath(), $maxWidth, $maxHeight, $field);

        return $media->put($this->currentUser($request)->auctioneer->public_id, 'banners', 'webp', $image->contents);
    }

    private function changed(Request $request): void
    {
        event(new StorefrontChanged($this->currentUser($request)->auctioneer_id));
    }
}
