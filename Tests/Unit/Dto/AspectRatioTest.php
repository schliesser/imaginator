<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;

final class AspectRatioTest extends TestCase
{
    public function testHeightForSixteenNine(): void
    {
        self::assertSame(900, (new AspectRatio(16, 9))->heightFor(1600));
    }

    public function testHeightRoundsToNearestInt(): void
    {
        self::assertSame(563, (new AspectRatio(16, 9))->heightFor(1000));
    }

    public function testFromStringParses(): void
    {
        self::assertEquals(new AspectRatio(4, 3), AspectRatio::fromString('4:3'));
    }

    public function testFromStringRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AspectRatio::fromString('16x9');
    }

    public function testRejectsZeroSide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AspectRatio(16, 0);
    }
}
