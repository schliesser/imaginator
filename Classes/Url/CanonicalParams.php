<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Url;

final readonly class CanonicalParams
{
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
    ) {}

    public function canonicalString(): string
    {
        return implode('|', [
            $this->storageUid,
            $this->fileUid,
            $this->cropVariant,
            $this->width,
            $this->height,
            $this->format,
        ]);
    }
}
