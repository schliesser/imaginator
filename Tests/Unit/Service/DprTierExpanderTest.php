<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Service\DprTierExpander;

final class DprTierExpanderTest extends TestCase
{
    public function testRatioTiersPassThroughUnchanged(): void
    {
        $tiers = [
            new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
            new BreakpointRatio(new AspectRatio(4, 3)),
        ];

        self::assertEquals($tiers, (new DprTierExpander())->expand($tiers, 3));
    }

    public function testFixedHeightTierExpandsToResolutionVariantsMostRestrictiveFirst(): void
    {
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: null, fixedHeight: 600)],
            3,
        );

        self::assertEquals(
            [
                new BreakpointRatio(media: '(min-resolution:2.5dppx)', fixedHeight: 1800, resolutionGated: true),
                new BreakpointRatio(media: '(min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true),
                new BreakpointRatio(media: null, fixedHeight: 600),
            ],
            $result,
        );
    }

    public function testCombinesResolutionWithExistingMinWidthMedia(): void
    {
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600)],
            2,
        );

        self::assertEquals(
            [
                // floor = 992 * 1.5 = 1488: rungs below this can never be selected at min-width 992 / 1.5dppx.
                new BreakpointRatio(media: '(min-width:992px) and (min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true, minRenderWidth: 1488),
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
            ],
            $result,
        );
    }

    public function testGatedTiersCarryMinRenderWidthFloorPerDprStep(): void
    {
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: '(min-width:960px)', fixedHeight: 640)],
            3,
        );

        self::assertSame(
            [
                ['(min-width:960px) and (min-resolution:2.5dppx)', 2400], // 960 * 2.5
                ['(min-width:960px) and (min-resolution:1.5dppx)', 1440], // 960 * 1.5
                ['(min-width:960px)', 0],                                 // base tier: not pruned
            ],
            array_map(static fn(BreakpointRatio $b): array => [$b->media, $b->minRenderWidth], $result),
        );
    }

    public function testMediaNullHeroGetsNoFloor(): void
    {
        // No min-width gate: the hero can be selected at any viewport, so even a high-DPR tier can
        // reach a small width — no rung is unreachable, floor stays 0.
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: null, fixedHeight: 600)],
            3,
        );

        foreach ($result as $tier) {
            self::assertSame(0, $tier->minRenderWidth);
        }
    }

    public function testPriorityAppliesAssumedViewportFloorToUngatedHero(): void
    {
        // A priority hero emits sizes="100vw" on every tier, so layout width = viewport. Assuming a
        // 320px smallest viewport, the gated tiers floor at 320 * minDPR; the 1x base stays unfloored.
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: null, fixedHeight: 600)],
            3,
            true,
        );

        self::assertSame(
            [
                ['(min-resolution:2.5dppx)', 800], // ceil(320 * 2.5)
                ['(min-resolution:1.5dppx)', 480], // ceil(320 * 1.5)
                [null, 0],                          // base 1x tier: not floored
            ],
            array_map(static fn(BreakpointRatio $b): array => [$b->media, $b->minRenderWidth], $result),
        );
    }

    public function testPriorityDoesNotLowerExistingMinWidthGate(): void
    {
        // A real min-width gate (992) is larger than the assumed 320 viewport, so it wins — the
        // priority floor never weakens an explicit breakpoint gate.
        $result = (new DprTierExpander())->expand(
            [new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600)],
            3,
            true,
        );

        self::assertSame(
            [2480, 1488, 0], // ceil(992*2.5), ceil(992*1.5), base
            array_map(static fn(BreakpointRatio $b): int => $b->minRenderWidth, $result),
        );
    }

    public function testCapOfOneDoesNotExpand(): void
    {
        $tiers = [new BreakpointRatio(media: null, fixedHeight: 600)];

        self::assertEquals($tiers, (new DprTierExpander())->expand($tiers, 1));
    }

    public function testExpandsEachFixedHeightTierInPlacePreservingOrder(): void
    {
        // {xs: "16:9", lg: "600px"} after resolver -> [lg fixed, xs ratio]; only the fixed tier expands.
        $result = (new DprTierExpander())->expand(
            [
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
                new BreakpointRatio(new AspectRatio(16, 9)),
            ],
            2,
        );

        self::assertEquals(
            [
                new BreakpointRatio(media: '(min-width:992px) and (min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true, minRenderWidth: 1488),
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
                new BreakpointRatio(new AspectRatio(16, 9)),
            ],
            $result,
        );
    }
}
