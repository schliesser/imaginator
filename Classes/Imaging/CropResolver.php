<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\Rectangle;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Single source of truth for the croppable region of a variant, shared by the render path
 * (ViewHelper), the verify path (middleware) and the processing path (LocalImageProcessor) so they
 * always agree on the crop area / focus area / source bounds.
 */
final readonly class CropResolver
{
    public function __construct(
        private ResourceFactory $resourceFactory,
    ) {}

    public function resolve(bool $isReference, int $uid, string $cropVariant): CropResolution
    {
        if (!$isReference) {
            $file = $this->resourceFactory->getFileObject($uid);
            $width = (int) $file->getProperty('width');
            $height = (int) $file->getProperty('height');

            return new CropResolution(new Rectangle(0, 0, $width, $height), new Rectangle(0, 0, 0, 0), $width, $height, $file);
        }

        $reference = $this->resourceFactory->getFileReferenceObject($uid);
        $original = $reference->getOriginalFile();
        $fileWidth = (int) $original->getProperty('width');
        $fileHeight = (int) $original->getProperty('height');
        $collection = CropVariantCollection::create((string) $reference->getProperty('crop'));

        // Empty crop area = whole file; empty focus area stays empty (crop centres on the crop area).
        $cropAreaArea = $collection->getCropArea($cropVariant);
        $cropArea = $cropAreaArea->isEmpty()
            ? new Rectangle(0, 0, $fileWidth, $fileHeight)
            : $this->toRectangle($cropAreaArea->makeAbsoluteBasedOnFile($original));

        $focusAreaArea = $collection->getFocusArea($cropVariant);
        $focusArea = $focusAreaArea->isEmpty()
            ? new Rectangle(0, 0, 0, 0)
            : $this->toRectangle($focusAreaArea->makeAbsoluteBasedOnFile($original));

        return new CropResolution(
            $cropArea,
            $focusArea,
            (int) $cropArea->width,
            (int) $cropArea->height,
            $original,
            !$cropAreaArea->isEmpty() || !$focusAreaArea->isEmpty(),
        );
    }

    private function toRectangle(Area $absolute): Rectangle
    {
        return new Rectangle(
            $absolute->getOffsetLeft(),
            $absolute->getOffsetTop(),
            $absolute->getWidth(),
            $absolute->getHeight(),
        );
    }
}
