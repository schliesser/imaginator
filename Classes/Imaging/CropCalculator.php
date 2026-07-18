<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\Rectangle;

/**
 * Pure geometry: the largest rectangle of a target ratio that fits inside an editor-defined crop
 * area, positioned to keep the focus area centered, clamped to the crop-area bounds. Used by the
 * renderer (to bound the ladder by the croppable region) and the processor (to apply the crop).
 */
final class CropCalculator
{
    public function fit(Rectangle $cropArea, Rectangle $focusArea, AspectRatio $ratio): Rectangle
    {
        // Largest rect of $ratio inside the crop area: width-bound unless that would overflow height.
        $heightAtFullWidth = $cropArea->width / $ratio->width * $ratio->height;
        if ($heightAtFullWidth <= $cropArea->height) {
            $width = $cropArea->width;
            $height = $heightAtFullWidth;
        } else {
            $height = $cropArea->height;
            $width = $cropArea->height / $ratio->height * $ratio->width;
        }

        // Center on the focus-area center, falling back to the crop-area center when no focus is set.
        $focusCenterX = $focusArea->width > 0.0
            ? $focusArea->x + $focusArea->width / 2
            : $cropArea->x + $cropArea->width / 2;
        $focusCenterY = $focusArea->height > 0.0
            ? $focusArea->y + $focusArea->height / 2
            : $cropArea->y + $cropArea->height / 2;

        $x = $this->clamp($focusCenterX - $width / 2, $cropArea->x, $cropArea->x + $cropArea->width - $width);
        $y = $this->clamp($focusCenterY - $height / 2, $cropArea->y, $cropArea->y + $cropArea->height - $height);

        return new Rectangle($x, $y, $width, $height);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($value, $max));
    }
}
