<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local\Backend;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Extbase\Service\ImageService;

/**
 * v1 local processing backend. Delegates to TYPO3's ImageService, which drives the
 * configured GraphicsMagick processor. Sits behind LocalBackendInterface so a libvips
 * backend can replace it in v2 without touching callers.
 */
final readonly class GraphicsMagickBackend implements LocalBackendInterface
{
    public function __construct(private ImageService $imageService) {}

    public function process(FileInterface $file, array $instructions): ProcessedFile
    {
        return $this->imageService->applyProcessingInstructions($file, $instructions);
    }
}
