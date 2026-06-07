<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Url\CanonicalParams;

final class ImageVariantTest extends TestCase
{
    public function testMapsToCanonicalParams(): void
    {
        $v = new ImageVariant(true, 4567, 'hero', 1280, 720, 'webp', 72);
        self::assertEquals(
            new CanonicalParams(true, 4567, 'hero', 1280, 720, 'webp'),
            $v->toCanonicalParams()
        );
    }
}
