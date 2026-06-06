<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * One per-breakpoint aspect ratio. A null media makes this the default `<img>` (single-ratio case
 * or the `<picture>` fallback); a non-null media emits a `<source media>` art-direction tier.
 */
final readonly class BreakpointRatio
{
    public function __construct(
        public AspectRatio $ratio,
        public ?string $media = null,
    ) {}
}
