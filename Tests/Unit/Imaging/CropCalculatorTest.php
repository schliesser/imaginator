<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Imaging;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\Imaging\CropCalculator;

final class CropCalculatorTest extends TestCase
{
    private function empty(): Rectangle
    {
        return new Rectangle(0, 0, 0, 0);
    }

    public function testMatchingRatioReturnsTheCropAreaItself(): void
    {
        $cropArea = new Rectangle(100, 50, 800, 450); // 16:9
        $rect = (new CropCalculator())->fit($cropArea, $this->empty(), new AspectRatio(16, 9));

        self::assertEqualsWithDelta(100, $rect->x, 0.01);
        self::assertEqualsWithDelta(50, $rect->y, 0.01);
        self::assertEqualsWithDelta(800, $rect->width, 0.01);
        self::assertEqualsWithDelta(450, $rect->height, 0.01);
    }

    public function testWiderTargetReducesHeightAndCentersWhenNoFocus(): void
    {
        $cropArea = new Rectangle(0, 0, 1000, 1000);
        $rect = (new CropCalculator())->fit($cropArea, $this->empty(), new AspectRatio(16, 9));

        self::assertEqualsWithDelta(1000, $rect->width, 0.01);
        self::assertEqualsWithDelta(562.5, $rect->height, 0.01);
        self::assertEqualsWithDelta(0, $rect->x, 0.01);
        self::assertEqualsWithDelta(218.75, $rect->y, 0.01); // vertically centered in 1000
    }

    public function testFocusNearTopClampsRectToCropTop(): void
    {
        $cropArea = new Rectangle(0, 0, 1000, 1000);
        $focus = new Rectangle(400, 0, 200, 100); // center y = 50
        $rect = (new CropCalculator())->fit($cropArea, $focus, new AspectRatio(16, 9));

        self::assertEqualsWithDelta(0, $rect->y, 0.01); // clamped to top, not negative
        self::assertEqualsWithDelta(562.5, $rect->height, 0.01);
    }

    public function testPortraitTargetFromLandscapeCropIsHeightBound(): void
    {
        $cropArea = new Rectangle(0, 0, 1000, 500);
        $rect = (new CropCalculator())->fit($cropArea, $this->empty(), new AspectRatio(9, 16));

        self::assertEqualsWithDelta(500, $rect->height, 0.01);
        self::assertEqualsWithDelta(281.25, $rect->width, 0.01); // 500 * 9/16
        self::assertGreaterThanOrEqual(0, $rect->x);
        self::assertLessThanOrEqual(1000 - 281.25 + 0.01, $rect->x);
    }
}
