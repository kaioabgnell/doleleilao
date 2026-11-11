<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Domain;

/**
 * Texto de uma mensagem do chat: texto puro (o site escapa ao mostrar e não transforma links em
 * links clicáveis, specs/04 segurança). De 1 a 500 caracteres.
 */
final readonly class MessageBody
{
    public const int MAX = 500;

    private function __construct(public string $value) {}

    public static function from(string $text): self
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Sem caracteres de controle (exceto a quebra de linha).
        $text = (string) preg_replace('/[^\P{C}\n]/u', '', $text);
        $text = (string) preg_replace("/[ \t]+\n/u", "\n", $text);
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);
        $text = trim($text);

        if ($text === '') {
            throw new InvalidMessage('body', 'Escreva uma mensagem.');
        }
        if (mb_strlen($text) > self::MAX) {
            throw new InvalidMessage('body', 'A mensagem pode ter até '.self::MAX.' caracteres.');
        }

        return new self($text);
    }

    /** Trecho para a citação ("responder", RF-F06): uma linha, até 80 caracteres. */
    public function excerpt(int $max = 80): string
    {
        return self::cut($this->value, $max);
    }

    public static function cut(string $text, int $max = 80): string
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($line) <= $max ? $line : rtrim(mb_substr($line, 0, $max - 1)).'…';
    }
}
