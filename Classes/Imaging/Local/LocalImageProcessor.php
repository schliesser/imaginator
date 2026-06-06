<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Imaging\Local\Backend\LocalBackendInterface;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Default processor: srcset URLs point at the signed local endpoint, and the actual pixels
 * are produced on demand by the configured local backend (GraphicsMagick in v1).
 */
final readonly class LocalImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private SignedUrlBuilder $signedUrlBuilder,
        private LocalBackendInterface $backend,
        private ResourceFactory $resourceFactory,
        private ImageService $imageService,
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
        $file = $this->resourceFactory->getFileObject($variant->fileUid);
        $processed = $this->backend->process($file, [
            'width' => $variant->width . 'c',
            'height' => $variant->height . 'c',
            'fileExtension' => $variant->format,
        ]);

        return new ProcessedImage(
            $this->imageService->getImageUri($processed),
            $processed->getForLocalProcessing(false),
            $processed->getMimeType(),
        );
    }
}
