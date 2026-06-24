<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Ladder;

use Schliesser\Imaginator\Dto\AspectRatio;

final class LadderFactory
{
    /**
     * Largest device-pixel-ratio a fixed-height tier sizes for: the smallest rung serves the height
     * at 1x, larger rungs scale up to this so a full-bleed hero stays crisp on retina; the surplus
     * vertical pixels are re-cropped by `object-fit:cover`.
     */
    private const DPR_CAP = 2;

    /** @param int[] $rungWidths configured ladder rung widths */
    public function __construct(
        private readonly array $rungWidths,
        private readonly int $maxDimension,
    ) {}

    /**
     * Build the width ladder for one tier. With $fixedHeight === null the height follows $ratio
     * (the classic case). With $fixedHeight set the height is pinned (a full-bleed hero): width
     * climbs the ladder while height stays the fixed CSS px value, DPR-scaled and clamped to the
     * source — $ratio is then ignored and may be null.
     *
     * @param int      $sourceHeight when > 0, ratio mode clamps width so a cover crop never upscales
     *                               vertically; fixed-height mode clamps each rung height to it
     * @param int|null $fixedHeight  fixed CSS-px tier height; null = derive height from $ratio
     * @return Rung[] ascending by width, deduped
     */
    public function build(?AspectRatio $ratio, int $sourceWidth, int $sourceHeight = 0, ?int $fixedHeight = null): array
    {
        if ($fixedHeight !== null) {
            return $this->buildFixedHeight($sourceWidth, $sourceHeight, $fixedHeight);
        }
        if ($ratio === null) {
            throw new \InvalidArgumentException('LadderFactory::build needs a ratio or a fixedHeight.', 1719223300);
        }

        $rungs = [];
        foreach ($this->clampedWidths($sourceWidth, $ratio, $sourceHeight) as $w) {
            $rungs[] = new Rung($w, $ratio->heightFor($w));
        }

        return $rungs;
    }

    /**
     * Fixed-height tier: widths are clamped by box only (height is independent), then each rung gets
     * the pinned height scaled by its position on the ladder up to {@see self::DPR_CAP}, clamped to
     * the source height so nothing upscales vertically — which keeps the middleware's reconstructed
     * `maxByHeight >= width`, so the signed URL still verifies.
     *
     * @return Rung[]
     */
    private function buildFixedHeight(int $sourceWidth, int $sourceHeight, int $fixedHeight): array
    {
        $widths = $this->clampedWidths($sourceWidth);
        $smallest = $widths[0];

        $rungs = [];
        foreach ($widths as $w) {
            $rungs[] = new Rung($w, $this->fixedHeightFor($w, $smallest, $fixedHeight, $sourceHeight));
        }

        return $rungs;
    }

    private function fixedHeightFor(int $width, int $smallestWidth, int $fixedHeight, int $sourceHeight): int
    {
        $scale = min((float) self::DPR_CAP, max(1.0, $width / $smallestWidth));
        $height = (int) round($fixedHeight * $scale);
        if ($sourceHeight > 0) {
            $height = min($height, $sourceHeight);
        }

        return max(1, $height);
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
