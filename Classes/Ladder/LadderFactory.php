<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Ladder;

use Schliesser\Imaginator\Dto\AspectRatio;

final class LadderFactory
{
    /** @param int[] $rungWidths configured ladder rung widths */
    public function __construct(
        private readonly array $rungWidths,
        private readonly int $maxDimension,
    ) {}

    /** @return Rung[] ascending, capped to min(rung, maxDimension, sourceWidth), deduped */
    public function build(AspectRatio $ratio, int $sourceWidth): array
    {
        $rungs = [];
        foreach ($this->clampedWidths($sourceWidth) as $w) {
            $rungs[] = new Rung($w, $ratio->heightFor($w));
        }

        return $rungs;
    }

    /** Quantize an arbitrary requested width UP to the nearest available rung width. */
    public function nearestRung(int $requestedWidth, int $sourceWidth): int
    {
        $widths = $this->clampedWidths($sourceWidth);
        foreach ($widths as $w) {
            if ($w >= $requestedWidth) {
                return $w;
            }
        }

        return $widths === [] ? 0 : (int)end($widths);
    }

    /** @return int[] sorted ascending, unique, >= 1 */
    private function clampedWidths(int $sourceWidth): array
    {
        $widths = [];
        foreach ($this->rungWidths as $w) {
            $clamped = (int)min($w, $this->maxDimension, $sourceWidth);
            if ($clamped >= 1) {
                $widths[$clamped] = true;
            }
        }
        ksort($widths);

        return array_keys($widths);
    }
}
