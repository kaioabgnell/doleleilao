<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auctioneer;

use App\Http\Resources\AuctionItemResource;
use DoleLeilao\Billing\Application\Port\FeeTierRepository;
use DoleLeilao\Billing\Application\Port\PaymentCardRepository;
use DoleLeilao\Billing\Application\SavePaymentCard;
use DoleLeilao\Billing\Domain\CreditCard;
use DoleLeilao\Billing\Domain\FeeTier;
use DoleLeilao\Shared\Domain\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurações → Pagamento (D-54): o cartão onde a taxa dos itens arrematados é debitada e as
 * faixas da taxa (D-47). Sem gateway: o número e o CVV chegam aqui, são conferidos e descartados.
 */
final class BillingController
{
    use ResolvesAuctioneerUser;

    public function __construct(
        private readonly PaymentCardRepository $cards,
        private readonly FeeTierRepository $tiers,
        private readonly Clock $clock,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->body($this->currentUser($request)->auctioneer_id));
    }

    public function saveCard(Request $request, SavePaymentCard $save): JsonResponse
    {
        $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'holder_name' => ['required', 'string', 'max:80'],
            'expiry' => ['required', 'string', 'max:9'],
            'cvv' => ['required', 'string', 'max:6'],
        ], [
            'number.required' => 'Digite o número do cartão.',
            'holder_name.required' => 'Digite o nome como está impresso no cartão.',
            'expiry.required' => 'Digite a validade no formato MM/AA.',
            'cvv.required' => 'Digite o código de segurança.',
        ]);
        $auctioneerId = $this->currentUser($request)->auctioneer_id;
        $save->handle(
            $auctioneerId,
            $request->string('number')->value(),
            $request->string('holder_name')->value(),
            $request->string('expiry')->value(),
            $request->string('cvv')->value(),
        );

        return response()->json($this->body($auctioneerId));
    }

    /** @return array<string, mixed> */
    private function body(int $auctioneerId): array
    {
        $schedule = $this->tiers->schedule();
        $card = $this->cards->findFor($auctioneerId);
        $now = $this->clock->now();
        $start = $schedule->startsAt();

        return [
            'card' => $card === null ? null : self::card($card),
            'fees_start_at' => AuctionItemResource::iso($start),
            // Antes do início (D-47: até 30/11/2026), a plataforma é gratuita.
            'fees_started' => $start !== null && $start <= $now,
            'fee_tiers' => array_map(static fn (FeeTier $t): array => [
                'min_cents' => $t->from->cents,
                'max_cents' => $t->upTo?->cents,
                'rate_bps' => $t->rateBps,
                'valid_from' => AuctionItemResource::iso($t->validFrom),
            ], $schedule->inEffect($now)),
        ];
    }

    /** @return array{brand: string, brand_label: string, last4: string, holder_name: string, exp_month: int, exp_year: int} */
    private static function card(CreditCard $card): array
    {
        return [
            'brand' => $card->brand->value,
            'brand_label' => $card->brand->label(),
            'last4' => $card->last4,
            'holder_name' => $card->holderName,
            'exp_month' => $card->expMonth,
            'exp_year' => $card->expYear,
        ];
    }
}
