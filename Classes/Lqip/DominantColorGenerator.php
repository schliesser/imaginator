<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Near-zero-byte placeholder: averages the image down to a single pixel and returns it as a
 * `#rrggbb` color, rendered as a solid background behind the sharp image. No decode, no flash.
 */
final class DominantColorGenerator implements LqipGeneratorInterface
{
    public function generate(FileInterface $file): ?string
    {
        $contents = $file->getContents();
        if ($contents === '') {
            return null;
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }
        // A solid color behind a transparent image would shine through its transparent pixels.
        if (ImageTransparency::isPresent($image)) {
            return null;
        }

        $pixel = imagescale($image, 1, 1, IMG_BILINEAR_FIXED);
        $rgb = imagecolorat($pixel ?: $image, 0, 0);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
