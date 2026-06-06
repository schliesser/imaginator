<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

use Thumbhash\Thumbhash;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Default placeholder: a ~25-byte ThumbHash decoded server-side to a small blurred preview and
 * inlined as a data-URI (no client JS). Rendered as a cover background behind the sharp image.
 */
final class ThumbHashGenerator implements LqipGeneratorInterface
{
    private const MAX_DIMENSION = 100;

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
        $image = $this->downscale($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $rgba = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $rgba[] = ($color >> 16) & 0xFF;
                $rgba[] = ($color >> 8) & 0xFF;
                $rgba[] = $color & 0xFF;
                // GD alpha is 0 (opaque) .. 127 (transparent); ThumbHash wants 0..255 opacity.
                $rgba[] = 255 - (int)round((($color >> 24) & 0x7F) * (255 / 127));
            }
        }

        return Thumbhash::toDataURL(Thumbhash::RGBAToHash($width, $height, $rgba));
    }

    /**
     * ThumbHash rejects inputs larger than 100×100; scale the longest edge down to fit.
     *
     * @param \GdImage $image
     * @return \GdImage
     */
    private function downscale(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($longest <= self::MAX_DIMENSION) {
            return $image;
        }
        $scale = self::MAX_DIMENSION / $longest;
        $scaled = imagescale($image, max(1, (int)round($width * $scale)), max(1, (int)round($height * $scale)));

        return $scaled ?: $image;
    }
}
