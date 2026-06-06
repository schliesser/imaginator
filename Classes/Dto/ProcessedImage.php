<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

final readonly class ProcessedImage
{
    public function __construct(
        public string $publicUrl,    // for 302 redirect (preferred)
        public string $absolutePath, // for streaming fallback
        public string $mimeType,
    ) {}
}
