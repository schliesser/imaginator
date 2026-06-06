<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

/**
 * Selects the LQIP generator for the configured `imaginator.lqip` value. Unknown values
 * (and `none`) fall back to the no-op generator.
 */
final class LqipGeneratorFactory
{
    public function __construct(
        private readonly ThumbHashGenerator $thumbHash,
        private readonly DominantColorGenerator $dominantColor,
        private readonly NullLqipGenerator $null,
    ) {}

    public function get(string $kind): LqipGeneratorInterface
    {
        return match ($kind) {
            'thumbhash' => $this->thumbHash,
            'dominant-color' => $this->dominantColor,
            default => $this->null,
        };
    }
}
