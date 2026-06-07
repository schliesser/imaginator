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
     * @param string[]          $formats     negotiated formats (most-preferred first) emitted as stacked
     *                                        `<source type>` tiers; empty => bare <img>/<picture>. The
     *                                        `format` field is the fallback/original format for the `<img>`.
     */
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public int $sourceWidth,
        public int $sourceHeight,
        public string $cropVariant,
        public array $breakpoints,
        public string $format,
        public int $quality,
        public string $alt,
        public ?string $class = null,
        public bool $priority = false,
        public array $formats = [],
        public ?string $lqipClass = null,
    ) {}
}
