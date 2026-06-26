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
     * Build the width ladder for one tier. With $fixedHeight === null the height follows $ratio
     * (the classic case). With $fixedHeight set the height is pinned (a full-bleed hero): width
     * climbs the ladder while height stays the fixed CSS px value, DPR-scaled and clamped to the
     * source — $ratio is then ignored and may be null.
     *
     * @param int      $sourceHeight when > 0, ratio mode clamps width so a cover crop never upscales
     *                               vertically; fixed-height mode clamps each rung height to it
     * @param int|null $fixedHeight  fixed CSS-px tier height; null = derive height from $ratio
     * @return non-empty-list<Rung> ascending by width, deduped (clampedWidths never yields an empty set)
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
     * Fixed-height tier: widths are clamped by box only (height is independent), then every rung gets
     * the same flat pinned height, clamped to the source height so nothing upscales vertically —
     * which keeps the middleware's reconstructed `maxByHeight >= width`, so the signed URL still
     * verifies. The per-DPR multiple (H, 2H, 3H) lives in the expanded tier's $fixedHeight, not here.
     *
     * @return non-empty-list<Rung>
     */
    private function buildFixedHeight(int $sourceWidth, int $sourceHeight, int $fixedHeight): array
    {
        $height = $sourceHeight > 0 ? min($fixedHeight, $sourceHeight) : $fixedHeight;
        $height = max(1, $height);

        $rungs = [];
        foreach ($this->clampedWidths($sourceWidth) as $w) {
            $rungs[] = new Rung($w, $height);
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

        // clampedWidths never yields an empty set; the largest rung is the ceiling for over-large requests.
        return (int) end($widths);
    }

    /** @return non-empty-list<int> sorted ascending, unique, >= 1 */
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
