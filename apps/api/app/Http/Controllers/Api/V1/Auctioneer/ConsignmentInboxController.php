<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use DoleLeilao\Storefront\Infrastructure\Eloquent\ConsignmentRequestRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Caixa de entrada do "quero leiloar" no painel (RF-G07, versão simples já na Fase 2). */
final class ConsignmentInboxController
{
    public function index(Request $request, MediaStorage $media): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:new,read,archived'], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = ConsignmentRequestRecord::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()),
                fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => array_map(fn (ConsignmentRequestRecord $c): array => [
                'id' => $c->public_id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone === null ? null : Phone::fromString($c->phone)->formatted(),
                'whatsapp_url' => $c->phone === null ? null : 'https://wa.me/'.Phone::fromString($c->phone)->whatsappNumber(),
                'description' => $c->description,
                'photos' => array_map(fn (string $path) => $media->url($path), $c->photos ?? []),
                'status' => $c->status,
                'from_bidder' => $c->bidder_id !== null,
                'created_at' => $c->created_at->toIso8601ZuluString(),
            ], $page->items()),
            'new_count' => ConsignmentRequestRecord::query()->where('status', 'new')->count(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function update(Request $request, string $consignment): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:new,read,archived']]);
        ConsignmentRequestRecord::query()->where('public_id', $consignment)->firstOrFail()
            ->forceFill(['status' => $request->string('status')->value()])->save();

        return response()->json(null, 204);
    }
}
