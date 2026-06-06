<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Lqip;

use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * No placeholder — the reserved aspect-ratio box is the only thing behind the sharp image.
 */
final class NullLqipGenerator implements LqipGeneratorInterface
{
    public function generate(FileInterface $file): ?string
    {
        return null;
    }
}
