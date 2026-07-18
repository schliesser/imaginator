<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

/**
 * Detects whether a decoded image actually uses transparency. A placeholder (blurred preview or
 * solid color) is painted *behind* the sharp image, so for images with transparent pixels it would
 * shine through permanently — those must get no placeholder at all.
 */
final class ImageTransparency
{
    /** GD alpha runs 0 (opaque) … 127 (fully transparent); ignore near-opaque anti-aliasing noise. */
    private const ALPHA_THRESHOLD = 16;

    /** Cap the scan to a 64×64 grid so detection stays cheap on large sources. */
    private const SCAN_GRID = 64;

    public static function isPresent(\GdImage $image): bool
    {
        // Palette images carry alpha per index; promote to true color so imagecolorat() exposes it.
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, intdiv($width, self::SCAN_GRID));
        $stepY = max(1, intdiv($height, self::SCAN_GRID));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                if ($alpha > self::ALPHA_THRESHOLD) {
                    return true;
                }
            }
        }

        return false;
    }
}
