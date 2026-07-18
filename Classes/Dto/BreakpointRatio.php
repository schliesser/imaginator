<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * One per-breakpoint sizing spec: EITHER an aspect ratio (height derived from width) OR a fixed
 * height in CSS px (width climbs the ladder, height stays pinned — e.g. a full-bleed hero image).
 * Exactly one of `ratio`/`fixedHeight` is set. A null media makes this the default `<img>`
 * (single-tier case or the `<picture>` fallback); a non-null media emits a `<source media>` tier.
 *
 * `resolutionGated` marks a `>1x` fixed-height variant produced by the DPR expander (its media
 * carries a `min-resolution` clause). Such a tier must emit the default format as a `<source>` too,
 * because the 1x `<img>` cannot satisfy a high-DPR request in a non-avif browser.
 *
 * `minRenderWidth` is the smallest file width this tier can ever select (its floor:
 * `minViewport * minDPR`). For a resolution-gated tier both lower bounds are known at render time, so
 * rungs below the floor are provably unreachable and the ladder builder prunes them. 0 = no floor.
 */
final readonly class BreakpointRatio
{
    public function __construct(
        public ?AspectRatio $ratio = null,
        public ?string $media = null,
        public ?int $fixedHeight = null,
        public bool $resolutionGated = false,
        public int $minRenderWidth = 0,
    ) {
        if (($this->ratio === null) === ($this->fixedHeight === null)) {
            throw new \InvalidArgumentException(
                'BreakpointRatio needs exactly one of ratio or fixedHeight.',
                1719223200,
            );
        }
    }
}
