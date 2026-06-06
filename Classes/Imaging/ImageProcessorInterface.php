<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;

interface ImageProcessorInterface
{
    /** Render-time: URL that belongs in srcset for this variant. */
    public function buildUrl(ImageVariant $variant): string;

    /** True when pixels happen elsewhere (external CDN/service); local processing is skipped. */
    public function isOffloaded(): bool;

    /** Local processors only: produce/return the processed binary. */
    public function materialize(ImageVariant $variant): ProcessedImage;
}
