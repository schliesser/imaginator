<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * One per-breakpoint sizing spec: EITHER an aspect ratio (height derived from width) OR a fixed
 * height in CSS px (width climbs the ladder, height stays pinned — the full-bleed hero case).
 * Exactly one of `ratio`/`fixedHeight` is set. A null media makes this the default `<img>`
 * (single-tier case or the `<picture>` fallback); a non-null media emits a `<source media>` tier.
 */
final readonly class BreakpointRatio
{
    public function __construct(
        public ?AspectRatio $ratio = null,
        public ?string $media = null,
        public ?int $fixedHeight = null,
    ) {
        if (($this->ratio === null) === ($this->fixedHeight === null)) {
            throw new \InvalidArgumentException(
                'BreakpointRatio needs exactly one of ratio or fixedHeight.',
                1719223200,
            );
        }
    }
}
