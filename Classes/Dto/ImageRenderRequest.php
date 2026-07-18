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
     * @param string $format the single output format (avif|webp)
     */
    public function __construct(
        public bool $isReference,
        public int $uid,
        public int $sourceWidth,
        public int $sourceHeight,
        public string $cropVariant,
        public array $breakpoints,
        public string $format,
        public int $quality,
        public string $alt,
        public ?string $class = null,
        public bool $priority = false,
        public ?string $lqipClass = null,
    ) {}
}
