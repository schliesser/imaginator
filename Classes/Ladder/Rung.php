<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Ladder;

final readonly class Rung
{
    public function __construct(public int $width, public int $height) {}
}
