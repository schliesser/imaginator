<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

final readonly class AspectRatio
{
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('AspectRatio sides must be >= 1', 1717600000);
        }
    }

    public static function fromString(string $ratio): self
    {
        if (!preg_match('/^(\d+):(\d+)$/', trim($ratio), $m)) {
            throw new \InvalidArgumentException(sprintf('Invalid ratio "%s"', $ratio), 1717600001);
        }

        return new self((int)$m[1], (int)$m[2]);
    }

    public function heightFor(int $width): int
    {
        return (int)round($width * $this->height / $this->width);
    }
}
