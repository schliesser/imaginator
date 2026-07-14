<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\UrlBuilder\LocalAsyncUrlBuilder;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * Default processor: the actual pixels are produced on demand through TYPO3's {@see ImageService}
 * (which drives the configured image processor — no direct GraphicsMagick/ImageMagick/GD calls).
 *
 * `buildUrl()` short-circuits when the derivative already exists: a warm variant gets its **static
 * `_processed_/…` file URL** straight into `srcset` (the browser then skips the `/_imaginator/`
 * middleware and its 302 entirely), while a cold variant gets the signed endpoint URL so processing
 * stays deferred to the first request. The existence probe is read-only — it never triggers
 * processing — and reuses the exact same processing instructions as {@see materialize()}, so the
 * lookup checksum matches byte-for-byte.
 *
 * For reference variants the editor's crop variant (cropArea + focusArea) is resolved here and the
 * target ratio is fitted inside it ({@see CropCalculator}); plain file variants are centre-cropped.
 */
#[AsImaginatorProcessor('local:async')]
final readonly class LocalImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private LocalAsyncUrlBuilder $localAsyncUrlBuilder,
        private ImageService $imageService,
        private CropCalculator $cropCalculator,
        private CropResolver $cropResolver,
        private ProcessedFileRepository $processedFileRepository,
    ) {}

    public function buildUrl(ImageVariant $variant): string
    {
        return $this->existingPublicUrl($variant)
            ?? $this->localAsyncUrlBuilder->build($variant->toCanonicalParams());
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        [$original, $instructions] = $this->processingPlan($variant);
        $processed = $this->imageService->applyProcessingInstructions($original, $instructions);

        return $this->toProcessedImage($processed, $variant);
    }

    /**
     * Read-only: returns the static processed-file URL when the derivative already exists, else null.
     * Never triggers processing — {@see ProcessedFileRepository::findOneByOriginalFileAndTaskTypeAndConfiguration()}
     * only queries `sys_file_processedfile` (by the configuration checksum), so a cold variant falls
     * back to the signed endpoint.
     */
    private function existingPublicUrl(ImageVariant $variant): ?string
    {
        [$original, $instructions] = $this->processingPlan($variant);
        if (!$original instanceof File) {
            return null;
        }

        $processed = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
            $original,
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            $instructions,
        );
        if (!$processed->isProcessed() || !$processed->exists()) {
            return null;
        }

        return $this->toRootRelativeUrl($this->imageService->getImageUri($processed));
    }

    /**
     * The original file + TYPO3 processing instructions for a variant. Single source of truth shared
     * by {@see materialize()} (which processes) and {@see existingPublicUrl()} (which only probes), so
     * the two can never disagree on the derivative's identity.
     *
     * @return array{0: FileInterface, 1: array<string, mixed>}
     */
    private function processingPlan(ImageVariant $variant): array
    {
        $resolution = $this->cropResolver->resolve($variant->isReference, $variant->uid, $variant->cropVariant);

        if ($variant->isReference) {
            $rect = $this->cropCalculator->fit(
                $resolution->cropArea,
                $resolution->focusArea,
                new AspectRatio($variant->width, $variant->height),
            );

            return [$resolution->original, [
                'width' => $variant->width,
                'height' => $variant->height,
                'fileExtension' => $variant->format,
                'crop' => new Area($rect->x, $rect->y, $rect->width, $rect->height),
            ]];
        }

        return [$resolution->original, [
            'width' => $variant->width . 'c',
            'height' => $variant->height . 'c',
            'fileExtension' => $variant->format,
        ]];
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
