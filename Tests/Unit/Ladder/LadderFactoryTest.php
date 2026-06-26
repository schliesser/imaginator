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
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);
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
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);
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
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);
        self::assertSame([320, 640, 1280, 1920, 2000], $widths);
    }

    public function testSourceHeightZeroDisablesHeightClamp(): void
    {
        $withClamp = $this->factory()->build(new AspectRatio(9, 16), 4000, 800);
        $withoutClamp = $this->factory()->build(new AspectRatio(9, 16), 4000);
        self::assertNotSame(
            array_map(static fn(Rung $r) => $r->width, $withClamp),
            array_map(static fn(Rung $r) => $r->width, $withoutClamp),
        );
    }

    public function testBuildNeverReturnsEmptyForUnreadableSourceWidth(): void
    {
        // A source whose width FAL could not determine (0) must not yield an empty ladder — the
        // renderer reads the largest rung and would fatal on an empty set (broken image).
        $rungs = $this->factory()->build(new AspectRatio(16, 9), 0, 0);
        self::assertGreaterThanOrEqual(1, count($rungs));
        self::assertGreaterThanOrEqual(1, $rungs[array_key_last($rungs)]->width);
    }

    public function testBuildNeverReturnsEmptyWhenRatioCannotFitShortSource(): void
    {
        // Source far too short for the target ratio: the height-bound width floors below 1. Must
        // still yield at least one rung instead of an empty ladder.
        $rungs = $this->factory()->build(new AspectRatio(1, 20), 1000, 4);
        self::assertGreaterThanOrEqual(1, count($rungs));
        self::assertGreaterThanOrEqual(1, $rungs[array_key_last($rungs)]->width);
    }

    public function testFixedHeightPinsFlatHeightAcrossRungs(): void
    {
        // Fixed-height hero: width climbs the (width-only clamped) ladder; height is the flat pinned
        // value on every rung. The DPR multiple lives in the expanded tier's fixedHeight, not here.
        $rungs = $this->factory()->build(null, 9999, 9999, 600);
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);
        $heights = array_map(static fn(Rung $r) => $r->height, $rungs);

        self::assertSame([320, 640, 1280, 1920, 2000], $widths);
        self::assertSame([600, 600, 600, 600, 600], $heights);
    }

    public function testFixedHeightNeverUpscalesPastSourceHeight(): void
    {
        // A 700px-tall source caps every rung at 700 — no vertical upscale (keeps the verify path's
        // reconstructed maxByHeight >= width, so signed URLs still validate).
        $rungs = $this->factory()->build(null, 9999, 700, 1800);
        $heights = array_map(static fn(Rung $r) => $r->height, $rungs);

        self::assertSame([700, 700, 700, 700, 700], $heights);
    }

    public function testFixedHeightClampsWidthBySourceWidth(): void
    {
        $rungs = $this->factory()->build(null, 1500, 9999, 600);
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);

        self::assertSame([320, 640, 1280, 1500], $widths);
    }

    public function testFixedHeightSingleRungSourceKeepsBaseHeight(): void
    {
        // A source narrower than the smallest rung collapses to one rung; its height stays the
        // unscaled fixed height.
        $rungs = $this->factory()->build(null, 300, 9999, 600);

        self::assertEquals([new Rung(300, 600)], $rungs);
    }

    public function testMinRenderWidthPrunesUnreachableLowRungs(): void
    {
        // Resolution-gated tier floor: a min-width 960 / 2.5dppx source can never select below
        // 960*2.5=2400, so every rung under the floor is dead weight. Keep rungs >= floor.
        $rungs = $this->factory()->build(null, 9999, 9999, 1600, 1500);
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);

        self::assertSame([1920, 2000], $widths);
    }

    public function testMinRenderWidthKeepsLargestWhenFloorExceedsAllRungs(): void
    {
        // Floor above the largest available rung (source-limited): never empty — keep the largest.
        $rungs = $this->factory()->build(null, 9999, 9999, 1600, 5000);
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);

        self::assertSame([2000], $widths);
    }

    public function testMinRenderWidthZeroDoesNotPrune(): void
    {
        $rungs = $this->factory()->build(null, 9999, 9999, 600, 0);
        $widths = array_map(static fn(Rung $r) => $r->width, $rungs);

        self::assertSame([320, 640, 1280, 1920, 2000], $widths);
    }
}
