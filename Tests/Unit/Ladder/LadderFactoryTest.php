<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Ladder;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Ladder\Rung;

final class LadderFactoryTest extends TestCase
{
    private function factory(): LadderFactory
    {
        return new LadderFactory([320, 640, 1280, 1920, 2560], 2000);
    }

    public function testBuildCapsAtMaxAndSourceWidthAndDedupes(): void
    {
        // max=2000, source=1500 -> each rung clamped to min(rung, max, source);
        // 1920->1500, 2560->1500 collapse to a single 1500.
        $rungs = $this->factory()->build(new AspectRatio(16, 9), 1500);
        $widths = array_map(static fn (Rung $r) => $r->width, $rungs);
        self::assertSame([320, 640, 1280, 1500], $widths);
    }

    public function testHeightFollowsRatio(): void
    {
        $rungs = $this->factory()->build(new AspectRatio(16, 9), 9999);
        self::assertSame(720, $rungs[2]->height); // 1280 * 9/16
    }

    public function testNearestRungRoundsUp(): void
    {
        self::assertSame(640, $this->factory()->nearestRung(500, 9999));
    }

    public function testNearestRungClampsToLargestAvailable(): void
    {
        self::assertSame(2000, $this->factory()->nearestRung(5000, 9999)); // capped by max 2000
    }

    public function testBuildClampsWidthBySourceHeightForPortraitTarget(): void
    {
        // 9:16 crop from a short 4000x800 source is height-bound: max width = floor(800 * 9/16) = 450.
        $rungs = $this->factory()->build(new AspectRatio(9, 16), 4000, 800);
        $widths = array_map(static fn (Rung $r) => $r->width, $rungs);
        self::assertSame([320, 450], $widths);
        // No rung exceeds the source height.
        foreach ($rungs as $rung) {
            self::assertLessThanOrEqual(800, $rung->height);
        }
    }

    public function testBuildDoesNotOverClampLandscapeTargetFromPortraitSource(): void
    {
        // 6:1 crop from a tall 3000x4500 portrait is width-bound, so the height clamp is inert.
        $rungs = $this->factory()->build(new AspectRatio(6, 1), 3000, 4500);
        $widths = array_map(static fn (Rung $r) => $r->width, $rungs);
        self::assertSame([320, 640, 1280, 1920, 2000], $widths);
    }

    public function testSourceHeightZeroDisablesHeightClamp(): void
    {
        $withClamp = $this->factory()->build(new AspectRatio(9, 16), 4000, 800);
        $withoutClamp = $this->factory()->build(new AspectRatio(9, 16), 4000);
        self::assertNotSame(
            array_map(static fn (Rung $r) => $r->width, $withClamp),
            array_map(static fn (Rung $r) => $r->width, $withoutClamp),
        );
    }
}
