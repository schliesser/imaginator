<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

use Schliesser\Imaginator\Dto\CanonicalParams;

final readonly class ImageVariant
{
    public function __construct(
        public bool $isReference,
        public int $uid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
        public int $quality,
    ) {}

    public function toCanonicalParams(): CanonicalParams
    {
        return new CanonicalParams(
            $this->isReference,
            $this->uid,
            $this->cropVariant,
            $this->width,
            $this->height,
            $this->format,
        );
    }
}
