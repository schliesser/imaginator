<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * An axis-aligned rectangle in absolute source pixels. Framework-free so the crop geometry stays
 * unit-testable; converted to a TYPO3 ImageManipulation\Area only at the processing boundary.
 */
final readonly class Rectangle
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}
}
