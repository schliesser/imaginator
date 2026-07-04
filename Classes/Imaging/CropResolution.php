<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\Rectangle;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Resolved crop context for a variant. sourceWidth/Height are the croppable region's dimensions
 * (the crop area for a reference, else the full image) — used to bound the ladder so render and
 * verify paths agree. `hasEditorCrop` is true only when the editor actually stored a crop or focus
 * area — a whole-file fallback cropArea does not count, so consumers can distinguish "crop to
 * replay" from "no editor intent" (e.g. external providers fall back to smart gravity).
 */
final readonly class CropResolution
{
    public function __construct(
        public Rectangle $cropArea,
        public Rectangle $focusArea,
        public int $sourceWidth,
        public int $sourceHeight,
        public FileInterface $original,
        public bool $hasEditorCrop = false,
    ) {}
}
