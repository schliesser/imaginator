<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Url\CanonicalParams;

final class CanonicalParamsTest extends TestCase
{
    public function testCanonicalStringIsDeterministicAndOrdered(): void
    {
        $p = new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp');
        self::assertSame('1|4567|hero|1280|720|webp', $p->canonicalString());
    }

    public function testDifferentParamsProduceDifferentStrings(): void
    {
        $a = new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp');
        $b = new CanonicalParams(1, 4567, 'hero', 1281, 720, 'webp');
        self::assertNotSame($a->canonicalString(), $b->canonicalString());
    }
}
