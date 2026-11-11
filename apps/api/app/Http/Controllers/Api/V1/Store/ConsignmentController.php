<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Notification\Application\SendNotification;
use DoleLeilao\Notification\Domain\Notification;
use DoleLeilao\Notification\Domain\Recipient;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\ViolationCollector;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Infrastructure\ConsignmentRequested;
use DoleLeilao\Storefront\Infrastructure\Eloquent\ConsignmentRequestRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification as Mailer;

/** "Quer leiloar algum produto seu? Fale com o leiloeiro" (RF-E09). */
final class ConsignmentController
{
    use ResolvesStore;

    public function store(Request $request, string $slug, ImageProcessor $images, MediaStorage $media, SendNotification $send): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'photos' => ['sometimes', 'array', 'max:3'],
            'photos.*' => ['file', 'max:10240'],
        ], ['email.required_without' => 'Informe um e-mail ou um telefone para o leiloeiro responder.', 'phone.required_without' => 'Informe um e-mail ou um telefone para o leiloeiro responder.'], [
            'name' => 'o seu nome', 'description' => 'a descrição', 'photos' => 'as fotos',
        ]);

        $errors = new ViolationCollector;
        $phone = $request->filled('phone') ? $errors->attempt(fn () => Phone::fromString($request->string('phone')->value())) : null;
        $photos = [];
        foreach (array_filter((array) $request->file('photos', []), fn ($f) => $f instanceof UploadedFile) as $file) {
            $image = $errors->attempt(fn () => $images->toWebp($file->getRealPath(), 1600, 1600, 'photos'));
            if ($image !== null) {
                $photos[] = $media->put($store->public_id, 'pedidos', 'webp', $image->contents);
            }
        }
        $errors->throwIfAny();

        $user = $request->user('sanctum');
        $consignment = ConsignmentRequestRecord::query()->create([
            'bidder_id' => $user instanceof BidderUser && $user->auctioneer_id === $store->id ? $user->id : null,
            'name' => trim($request->string('name')->value()),
            'email' => $request->filled('email') ? mb_strtolower($request->string('email')->trim()->value()) : null,
            'phone' => $phone?->value,
            'description' => trim($request->string('description')->value()),
            'photos' => $photos,
        ]);

        foreach (AuctioneerUser::query()->where('auctioneer_id', $store->id)->get() as $owner) {
            $send->handle(Notification::consignmentRequest(Recipient::auctioneerUser($owner->id, $store->id), $consignment->name, $consignment->public_id));
            Mailer::send($owner, new ConsignmentRequested($consignment));
        }

        return response()->json(['message' => 'Pedido enviado! O leiloeiro vai entrar em contato.'], 201);
    }
}
