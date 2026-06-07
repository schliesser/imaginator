<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local;

use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Imaging\Local\Backend\LocalBackendInterface;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Default processor: srcset URLs point at the signed local endpoint, and the actual pixels
 * are produced on demand by the configured local backend (GraphicsMagick in v1).
 *
 * For reference variants the editor's crop variant (cropArea + focusArea) is resolved here and the
 * target ratio is fitted inside it ({@see CropCalculator}); plain file variants are centre-cropped.
 */
final readonly class LocalImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private SignedUrlBuilder $signedUrlBuilder,
        private LocalBackendInterface $backend,
        private ResourceFactory $resourceFactory,
        private ImageService $imageService,
        private CropCalculator $cropCalculator,
    ) {}

    public function buildUrl(ImageVariant $variant): string
    {
        return $this->signedUrlBuilder->build($variant->toCanonicalParams());
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        $processed = $variant->isReference
            ? $this->materializeReference($variant)
            : $this->backend->process(
                $this->resourceFactory->getFileObject($variant->uid),
                [
                    'width' => $variant->width . 'c',
                    'height' => $variant->height . 'c',
                    'fileExtension' => $variant->format,
                ],
            );

        return new ProcessedImage(
            $this->toRootRelativeUrl($this->imageService->getImageUri($processed)),
            $processed->getForLocalProcessing(false),
            $processed->getMimeType(),
        );
    }

    private function materializeReference(ImageVariant $variant): ProcessedFile
    {
        $reference = $this->resourceFactory->getFileReferenceObject($variant->uid);
        $original = $reference->getOriginalFile();
        $collection = CropVariantCollection::create((string)$reference->getProperty('crop'));

        // An empty crop area covers the whole file; an empty focus area stays empty so the crop is
        // centred on the crop area instead.
        $cropArea = $collection->getCropArea($variant->cropVariant);
        $cropRect = $cropArea->isEmpty()
            ? new Rectangle(0, 0, (float)$original->getProperty('width'), (float)$original->getProperty('height'))
            : $this->toRectangle($cropArea->makeAbsoluteBasedOnFile($original));

        $focusArea = $collection->getFocusArea($variant->cropVariant);
        $focusRect = $focusArea->isEmpty()
            ? new Rectangle(0, 0, 0, 0)
            : $this->toRectangle($focusArea->makeAbsoluteBasedOnFile($original));

        $rect = $this->cropCalculator->fit($cropRect, $focusRect, new AspectRatio($variant->width, $variant->height));

        return $this->backend->process($original, [
            'width' => $variant->width,
            'height' => $variant->height,
            'fileExtension' => $variant->format,
            'crop' => new Area($rect->x, $rect->y, $rect->width, $rect->height),
        ]);
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

    /**
     * ImageService returns local URLs relative to the site root without a leading slash
     * (e.g. "fileadmin/_processed_/…"). As a redirect Location that would resolve against the
     * /_imaginator/ request path, so normalise anything that is not already absolute.
     */
    private function toRootRelativeUrl(string $url): string
    {
        if ($url === '' || $url[0] === '/' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            return $url;
        }

        return '/' . $url;
    }
}
