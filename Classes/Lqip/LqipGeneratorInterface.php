<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

use TYPO3\CMS\Core\Resource\FileInterface;

interface LqipGeneratorInterface
{
    /**
     * Produce a low-quality placeholder for $file:
     *  - a `#rrggbb` color (rendered as a solid background), or
     *  - a `data:image/...;base64,…` URI (rendered as a cover background-image), or
     *  - null when no placeholder should be emitted.
     */
    public function generate(FileInterface $file): ?string;
}
