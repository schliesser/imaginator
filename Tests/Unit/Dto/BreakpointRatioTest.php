<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;

final class BreakpointRatioTest extends TestCase
{
    public function testAcceptsRatioOnly(): void
    {
        $bp = new BreakpointRatio(new AspectRatio(16, 9), '(min-width:768px)');

        self::assertEquals(new AspectRatio(16, 9), $bp->ratio);
        self::assertNull($bp->fixedHeight);
    }

    public function testAcceptsFixedHeightOnly(): void
    {
        $bp = new BreakpointRatio(media: '(min-width:768px)', fixedHeight: 600);

        self::assertNull($bp->ratio);
        self::assertSame(600, $bp->fixedHeight);
    }

    public function testThrowsWhenNeitherSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BreakpointRatio();
    }

    public function testThrowsWhenBothSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BreakpointRatio(new AspectRatio(16, 9), null, 600);
    }
}
