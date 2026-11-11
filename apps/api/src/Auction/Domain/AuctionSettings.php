<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Shared\Domain\RuleViolations;

/**
 * Configurações do pregão (RF-D02), do chat (RF-D03) e de quem paga a taxa (D-54), com valores padrão sugeridos.
 * Prorrogação (regra 7): lance com menos de `extendWindowS` restantes leva o fim para agora + `extendByS`.
 */
final readonly class AuctionSettings
{
    /** @var array<string, array{int, int, int, string}> chave => [padrão, mínimo, máximo, nome] */
    private const array LIMITS = [
        'item_duration_s' => [60, 15, 900, 'A duração do item'],
        'extend_window_s' => [15, 0, 120, 'A janela de prorrogação'],
        'extend_by_s' => [15, 5, 300, 'O tempo de prorrogação'],
        'gap_s' => [10, 0, 600, 'O intervalo entre itens'],
    ];

    private function __construct(
        public int $itemDurationS,
        public int $extendWindowS,
        public int $extendByS,
        public int $gapS,
        public bool $chatEnabled,
        public ChatVisibility $chatVisibility,
        public ChatWrite $chatWrite,
        public FeePayer $feePayer,
    ) {}

    public static function defaults(): self
    {
        return self::fromArray([]);
    }

    /** @param array<string, mixed> $data campos ausentes recebem o padrão */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $seconds = static function (string $key) use ($data, &$errors): int {
            [$default, $min, $max, $name] = self::LIMITS[$key];
            $raw = $data[$key] ?? $default;
            $value = is_int($raw) || (is_string($raw) && ctype_digit($raw)) ? (int) $raw : null;
            if ($value === null || $value < $min || $value > $max) {
                $errors[] = new InvalidAuction("settings.{$key}", "{$name} precisa ficar entre {$min} e {$max} segundos.");

                return $default;
            }

            return $value;
        };
        $itemDuration = $seconds('item_duration_s');
        $extendWindow = $seconds('extend_window_s');
        $extendBy = $seconds('extend_by_s');
        $gap = $seconds('gap_s');

        $visibility = ChatVisibility::tryFrom(self::str($data['chat_visibility'] ?? ChatVisibility::Everyone->value));
        $write = ChatWrite::tryFrom(self::str($data['chat_write'] ?? ChatWrite::LoggedIn->value));
        if ($visibility === null) {
            $errors[] = new InvalidAuction('settings.chat_visibility', 'Escolha quem pode ver o chat.');
        }
        if ($write === null) {
            $errors[] = new InvalidAuction('settings.chat_write', 'Escolha quem pode escrever no chat.');
        }
        $feePayer = FeePayer::tryFrom(self::str($data['fee_payer'] ?? FeePayer::Auctioneer->value));
        if ($feePayer === null) {
            $errors[] = new InvalidAuction('settings.fee_payer', 'Escolha quem paga a taxa do leilão.');
        }

        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new RuleViolations($errors);
        }

        return new self(
            $itemDuration,
            $extendWindow,
            $extendBy,
            $gap,
            filter_var($data['chat_enabled'] ?? true, FILTER_VALIDATE_BOOL),
            $visibility ?? ChatVisibility::Everyone,
            $write ?? ChatWrite::LoggedIn,
            $feePayer ?? FeePayer::Auctioneer,
        );
    }

    /** @return array{item_duration_s: int, extend_window_s: int, extend_by_s: int, gap_s: int, chat_enabled: bool, chat_visibility: string, chat_write: string, fee_payer: string} */
    public function toArray(): array
    {
        return [
            'item_duration_s' => $this->itemDurationS,
            'extend_window_s' => $this->extendWindowS,
            'extend_by_s' => $this->extendByS,
            'gap_s' => $this->gapS,
            'chat_enabled' => $this->chatEnabled,
            'chat_visibility' => $this->chatVisibility->value,
            'chat_write' => $this->chatWrite->value,
            'fee_payer' => $this->feePayer->value,
        ];
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
