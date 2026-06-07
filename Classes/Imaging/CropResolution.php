<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\Rectangle;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Resolved crop context for a variant. sourceWidth/Height are the croppable region's dimensions
 * (the crop area for a reference, else the full image) — used to bound the ladder so render and
 * verify paths agree.
 */
final readonly class CropResolution
{
    public function __construct(
        public Rectangle $cropArea,
        public Rectangle $focusArea,
        public int $sourceWidth,
        public int $sourceHeight,
        public FileInterface $original,
    ) {}
}
