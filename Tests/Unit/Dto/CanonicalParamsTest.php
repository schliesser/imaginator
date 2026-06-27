<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\CanonicalParams;

final class CanonicalParamsTest extends TestCase
{
    public function testCanonicalStringIsDeterministicAndOrdered(): void
    {
        $file = new CanonicalParams(false, 4567, 'hero', 1280, 720, 'webp');
        self::assertSame('f|4567|hero|1280|720|webp', $file->canonicalString());

        $reference = new CanonicalParams(true, 4567, 'hero', 1280, 720, 'webp');
        self::assertSame('r|4567|hero|1280|720|webp', $reference->canonicalString());
    }

    public function testReferenceFlagChangesTheString(): void
    {
        $file = new CanonicalParams(false, 4567, 'hero', 1280, 720, 'webp');
        $reference = new CanonicalParams(true, 4567, 'hero', 1280, 720, 'webp');
        self::assertNotSame($file->canonicalString(), $reference->canonicalString());
    }

    public function testDifferentParamsProduceDifferentStrings(): void
    {
        $a = new CanonicalParams(false, 4567, 'hero', 1280, 720, 'webp');
        $b = new CanonicalParams(false, 4567, 'hero', 1281, 720, 'webp');
        self::assertNotSame($a->canonicalString(), $b->canonicalString());
    }
}
