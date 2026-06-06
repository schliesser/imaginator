<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

use Schliesser\Imaginator\Url\CanonicalParams;

final readonly class ImageVariant
{
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
        public int $quality,
    ) {}

    public function toCanonicalParams(): CanonicalParams
    {
        return new CanonicalParams(
            $this->storageUid,
            $this->fileUid,
            $this->cropVariant,
            $this->width,
            $this->height,
            $this->format,
        );
    }
}
