<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;

/**
 * Synchronous local processor: instead of pointing `srcset` at the signed `/_imaginator/` endpoint
 * (the {@see LocalImageProcessor} async mode + middleware 302), it materializes each variant at
 * render time through TYPO3's {@see \TYPO3\CMS\Extbase\Service\ImageService} and writes the
 * resulting static `_processed_/…` file URL straight into `srcset`. The signed endpoint and its
 * middleware are never involved, so subsequent requests are plain static-file serves with no PHP hop.
 *
 * Trade-off: render time rises on a cold cache (one processing op per rung per breakpoint); once
 * the derivatives exist, ImageService returns them without reprocessing and the webserver serves
 * the bytes directly.
 */
final readonly class LocalSyncImageProcessor implements ImageProcessorInterface
{
    public function __construct(private LocalImageProcessor $processor) {}

    public function buildUrl(ImageVariant $variant): string
    {
        return $this->processor->materialize($variant)->publicUrl;
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        return $this->processor->materialize($variant);
    }
}
