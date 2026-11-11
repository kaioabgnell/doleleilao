<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionItemResource;
use DoleLeilao\Storefront\Domain\StoreTerms;
use DoleLeilao\Storefront\Infrastructure\StoreTermsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Termos de uso da loja no painel: o leiloeiro edita as regras que o participante aceita no cadastro. */
final class StoreTermsController
{
    use ResolvesAuctioneerUser;

    public function __construct(private readonly StoreTermsRepository $terms) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->body($this->currentUser($request)->auctioneer_id));
    }

    /** `{terms}` grava o texto; `{reset: true}` volta ao texto padrão. */
    public function update(Request $request): JsonResponse
    {
        $request->validate(
            ['reset' => ['sometimes', 'boolean'], 'terms' => ['exclude_if:reset,true', 'required', 'string']],
            ['terms.required' => 'Escreva os termos da loja.'],
        );
        $auctioneerId = $this->currentUser($request)->auctioneer_id;
        $this->terms->save($auctioneerId, $request->boolean('reset') ? null : $request->string('terms')->value());

        return response()->json($this->body($auctioneerId));
    }

    /** @return array<string, mixed> */
    private function body(int $auctioneerId): array
    {
        $current = $this->terms->forAuctioneer($auctioneerId);

        return [
            'terms' => $current['terms']->text,
            'default' => StoreTerms::default()->text,
            'is_default' => $current['is_default'],
            'updated_at' => AuctionItemResource::iso($current['updated_at']),
            'max' => StoreTerms::MAX,
            'fields' => collect(StoreTerms::FIELDS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
        ];
    }
}
