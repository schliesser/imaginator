<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local;

use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Imaging\Local\Backend\LocalBackendInterface;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\ProcessedFile;
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
        private ImageService $imageService,
        private CropCalculator $cropCalculator,
        private CropResolver $cropResolver,
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
        $resolution = $this->cropResolver->resolve($variant->isReference, $variant->uid, $variant->cropVariant);

        if ($variant->isReference) {
            $rect = $this->cropCalculator->fit(
                $resolution->cropArea,
                $resolution->focusArea,
                new AspectRatio($variant->width, $variant->height),
            );
            $processed = $this->backend->process($resolution->original, [
                'width' => $variant->width,
                'height' => $variant->height,
                'fileExtension' => $variant->format,
                'crop' => new Area($rect->x, $rect->y, $rect->width, $rect->height),
            ]);
        } else {
            $processed = $this->backend->process($resolution->original, [
                'width' => $variant->width . 'c',
                'height' => $variant->height . 'c',
                'fileExtension' => $variant->format,
            ]);
        }

        return $this->toProcessedImage($processed, $variant);
    }

    private function toProcessedImage(ProcessedFile $processed, ImageVariant $variant): ProcessedImage
    {
        $localPath = $processed->getForLocalProcessing(false);
        // Some backends exit 0 but leave a 0-byte file (e.g. GraphicsMagick/libheif failing to encode
        // AVIF at large dimensions). Redirecting to that yields a broken image with a 200; fail loud
        // instead so the failure surfaces in logs rather than being silently served.
        if (!is_file($localPath) || filesize($localPath) === 0) {
            throw new \RuntimeException(
                sprintf(
                    'imaginator: empty processed file for %dx%d.%s — the image processor failed to encode it.',
                    $variant->width,
                    $variant->height,
                    $variant->format,
                ),
                1718100001,
            );
        }

        return new ProcessedImage(
            $this->toRootRelativeUrl($this->imageService->getImageUri($processed)),
            $localPath,
            $processed->getMimeType(),
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
