<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Media;

final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public int $width,
        public int $height,
    ) {}
}
