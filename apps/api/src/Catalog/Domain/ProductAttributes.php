<?php

declare(strict_types=1);

namespace DoleLeilao\Catalog\Domain;

/**
 * Ficha técnica livre (D-27, RF-C06): pares nome/valor definidos pelo leiloeiro, sem campos fixos
 * por modelo ou ramo. Ex.: "Calibre: automático", "Tamanho: M", "Memória: 256 GB".
 */
final readonly class ProductAttributes
{
    public const int MAX_ITEMS = 30;

    public const int MAX_NAME = 40;

    public const int MAX_VALUE = 120;

    /** @param list<array{name: string, value: string}> $items */
    private function __construct(private array $items) {}

    /** @param array<mixed> $input */
    public static function fromArray(array $input): self
    {
        $items = [];
        $seen = [];
        foreach ($input as $row) {
            $name = is_array($row) && is_string($row['name'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $row['name']) ?? '') : '';
            $value = is_array($row) && is_string($row['value'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $row['value']) ?? '') : '';

            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '') {
                throw new InvalidProduct('attributes', 'Preencha o nome do item da ficha técnica.');
            }
            if ($value === '') {
                throw new InvalidProduct('attributes', "Preencha o valor de “{$name}”.");
            }
            if (mb_strlen($name) > self::MAX_NAME) {
                throw new InvalidProduct('attributes', 'Use até 40 caracteres no nome de cada item da ficha técnica.');
            }
            if (mb_strlen($value) > self::MAX_VALUE) {
                throw new InvalidProduct('attributes', 'Use até 120 caracteres no valor de cada item da ficha técnica.');
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                throw new InvalidProduct('attributes', "“{$name}” aparece duas vezes na ficha técnica.");
            }
            $seen[$key] = true;
            $items[] = ['name' => $name, 'value' => $value];
        }

        if (count($items) > self::MAX_ITEMS) {
            throw new InvalidProduct('attributes', 'A ficha técnica pode ter até 30 itens.');
        }

        return new self($items);
    }

    /** @return list<array{name: string, value: string}> */
    public function toArray(): array
    {
        return $this->items;
    }
}
