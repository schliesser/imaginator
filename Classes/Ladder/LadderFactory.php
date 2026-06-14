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

    /**
     * @param int $sourceHeight when > 0, also clamp so a cover crop to $ratio never upscales
     *                          vertically (a portrait target from a short source is height-bound)
     * @return Rung[] ascending, capped to min(rung, maxDimension, sourceWidth, height-bound), deduped
     */
    public function build(AspectRatio $ratio, int $sourceWidth, int $sourceHeight = 0): array
    {
        $rungs = [];
        foreach ($this->clampedWidths($sourceWidth, $ratio, $sourceHeight) as $w) {
            $rungs[] = new Rung($w, $ratio->heightFor($w));
        }

        return $rungs;
    }

    /**
     * Quantize an arbitrary requested width UP to the nearest available rung width. Pass $ratio and
     * $sourceHeight to apply the same height-bound clamp as {@see build()}.
     */
    public function nearestRung(int $requestedWidth, int $sourceWidth, ?AspectRatio $ratio = null, int $sourceHeight = 0): int
    {
        $widths = $this->clampedWidths($sourceWidth, $ratio, $sourceHeight);
        foreach ($widths as $w) {
            if ($w >= $requestedWidth) {
                return $w;
            }
        }

        return $widths === [] ? 0 : (int) end($widths);
    }

    /** @return int[] sorted ascending, unique, >= 1 */
    private function clampedWidths(int $sourceWidth, ?AspectRatio $ratio = null, int $sourceHeight = 0): array
    {
        // Widest crop of $ratio that fits the source height without upscaling: floor(sh * w/h).
        $maxByHeight = ($sourceHeight > 0 && $ratio !== null)
            ? (int) floor($sourceHeight * $ratio->width / $ratio->height)
            : PHP_INT_MAX;

        $widths = [];
        foreach ($this->rungWidths as $w) {
            $clamped = (int) min($w, $this->maxDimension, $sourceWidth, $maxByHeight);
            if ($clamped >= 1) {
                $widths[$clamped] = true;
            }
        }

        // Degenerate source (unreadable 0 dimension, or too short for the target ratio so the
        // height-bound width floors below 1): never hand back an empty ladder — the renderer reads
        // the largest rung and would fatal on an empty set. Fall back to the largest width the box
        // allows, floored to 1px. The verify path runs the same clamp, so both still agree.
        if ($widths === []) {
            $widths[max(1, (int) min($sourceWidth, $maxByHeight, $this->maxDimension))] = true;
        }
        ksort($widths);

        return array_keys($widths);
    }
}
