<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

/**
 * Modelo da mensagem ao vencedor (RF-G05): texto livre com campos entre chaves, trocados pelos dados
 * da arrematação. Campos desconhecidos ficam como estão (o leiloeiro vê na prévia).
 */
final readonly class WinnerMessageTemplate
{
    public const int MAX = 2000;

    /** @var array<string, string> campo => descrição para o painel */
    public const array FIELDS = [
        'nome' => 'Nome do arrematante',
        'apelido' => 'Apelido',
        'item' => 'Item arrematado',
        'valor' => 'Valor',
        'leilao' => 'Nome do leilão',
        'data' => 'Data do arremate',
        'loja' => 'Nome da loja',
        'whatsapp' => 'WhatsApp da loja',
    ];

    private const string DEFAULT = "Olá, {nome}! Parabéns: você arrematou {item} no leilão {leilao} por {valor}.\n\nVamos combinar o pagamento e a entrega? Responda esta mensagem ou chame a gente no WhatsApp {whatsapp}.\n\n{loja}";

    private function __construct(public string $text) {}

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public static function from(string $text): self
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            throw new InvalidAuction('template', 'Escreva a mensagem.');
        }
        if (mb_strlen($text) > self::MAX) {
            throw new InvalidAuction('template', 'A mensagem pode ter até '.self::MAX.' caracteres.');
        }

        return new self($text);
    }

    /** @param array<string, string> $values */
    public function render(array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z]+)\}/',
            static fn (array $m): string => array_key_exists($m[1], self::FIELDS) ? ($values[$m[1]] ?? '') : $m[0],
            $this->text,
        );
    }
}
