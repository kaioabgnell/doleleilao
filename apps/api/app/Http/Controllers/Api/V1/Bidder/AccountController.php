<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use App\Http\Requests\AddressPayload;
use App\Http\Resources\BidderResource;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Notification\Infrastructure\AuctionStartingAlerts;
use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Media\ImageProcessor;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** "Minha conta" do participante (RF-E07): dados, foto ou avatar, situação do cadastro. */
final class AccountController
{
    public function me(Request $request): BidderResource
    {
        return new BidderResource($this->bidder($request));
    }

    public function update(Request $request): BidderResource
    {
        $bidder = $this->bidder($request);
        $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'nickname' => ['sometimes', 'string', 'min:2', 'max:30'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'alert_minutes' => ['sometimes', 'nullable', 'integer', 'in:'.implode(',', AuctionStartingAlerts::OPTIONS)],
            ...AddressPayload::rules(),
        ], ['alert_minutes.in' => 'Escolha 15, 30, 60 ou 120 minutos.'], ['nickname' => 'o apelido']);

        if ($request->has('name')) {
            $bidder->name = trim((string) preg_replace('/\s+/u', ' ', $request->string('name')->value()));
        }
        if ($request->has('nickname')) {
            $bidder->nickname = trim($request->string('nickname')->value());
        }
        if ($request->has('alert_minutes')) {
            $bidder->alert_minutes = $request->input('alert_minutes') === null ? null : $request->integer('alert_minutes');
        }
        if ($request->has('phone')) {
            $bidder->phone = Phone::fromString($request->string('phone')->value())->value;
        }
        // Endereço de entrega dos arremates (opcional). CEP vazio apaga.
        if ($request->has('address')) {
            $data = AddressPayload::from($request);
            $bidder->changeShippingAddress($data === null ? null : Address::fromArray($data));
        }
        $bidder->save();

        return new BidderResource($bidder);
    }

    /** Foto (opcional, D-38): o site recorta em quadrado; aqui vira WebP 512 px sem EXIF. */
    public function photo(Request $request, ImageProcessor $images, MediaStorage $media): BidderResource
    {
        $bidder = $this->bidder($request);
        $request->validate(['photo' => ['required', 'file', 'max:5120']], [], ['photo' => 'a foto']);
        /** @var UploadedFile $file */
        $file = $request->file('photo');
        $image = $images->toWebp($file->getRealPath(), 512, 512, 'photo');

        $media->delete($bidder->photo_path);
        $bidder->photo_path = $media->put($bidder->auctioneer->public_id, 'participantes', 'webp', $image->contents);
        $bidder->save();

        return new BidderResource($bidder);
    }

    public function removePhoto(Request $request, MediaStorage $media): BidderResource
    {
        $bidder = $this->bidder($request);
        $media->delete($bidder->photo_path);
        $bidder->forceFill(['photo_path' => null])->save();

        return new BidderResource($bidder);
    }

    public function avatar(Request $request): BidderResource
    {
        $bidder = $this->bidder($request);
        $request->validate(['avatar_key' => ['present', 'nullable', Rule::in(BidderUser::AVATAR_KEYS)]], [], ['avatar_key' => 'o avatar']);
        $key = $request->input('avatar_key');
        $bidder->forceFill(['avatar_key' => is_string($key) ? $key : null])->save();

        return new BidderResource($bidder);
    }

    public function eligibility(Request $request): JsonResponse
    {
        $bidder = $this->bidder($request);

        return response()->json(['eligible' => $bidder->isEligible(), 'missing' => $bidder->missingForEligibility()]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $bidder = $this->bidder($request);
        if ($bidder->email_verified_at !== null) {
            return response()->json(['message' => 'Seu e-mail já está confirmado.']);
        }
        $bidder->sendEmailVerification($bidder->auctioneer->slug);

        return response()->json(['message' => "Enviamos um novo link para {$bidder->email}."], 202);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->bidder($request)->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    private function bidder(Request $request): BidderUser
    {
        $user = $request->user('sanctum');
        if (! $user instanceof BidderUser) {
            abort(401);
        }

        return $user;
    }
}
