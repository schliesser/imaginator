<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * Everything the renderer needs, decoupled from FAL so the render layer stays pure and
 * golden-file testable. A single breakpoint renders an `<img>`; several render a `<picture>`.
 */
final readonly class ImageRenderRequest
{
    /**
     * @param BreakpointRatio[] $breakpoints ordered; entries with media => <source>, the one with null media => <img>
     */
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public int $sourceWidth,
        public string $cropVariant,
        public array $breakpoints,
        public string $format,
        public int $quality,
        public string $alt,
        public ?string $class = null,
        public bool $priority = false,
    ) {}
}
