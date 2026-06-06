<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\Local\Backend;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;

interface LocalBackendInterface
{
    /**
     * Produce a processed (resized/cropped/reformatted) derivative of $file.
     *
     * @param array<string, mixed> $instructions TYPO3 processing instructions (width, height, crop, fileExtension, …)
     */
    public function process(FileInterface $file, array $instructions): ProcessedFile;
}
