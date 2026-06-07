<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Url;

final readonly class CanonicalParams
{
    public function __construct(
        public bool $isReference,
        public int $uid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
    ) {}

    public function canonicalString(): string
    {
        return implode('|', [
            $this->isReference ? 'r' : 'f',
            $this->uid,
            $this->cropVariant,
            $this->width,
            $this->height,
            $this->format,
        ]);
    }
}
