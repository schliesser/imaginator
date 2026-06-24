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
                new BreakpointRatio(media: '(min-width:992px) and (min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true),
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
            ],
            $result,
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
                new BreakpointRatio(media: '(min-width:992px) and (min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true),
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
                new BreakpointRatio(new AspectRatio(16, 9)),
            ],
            $result,
        );
    }
}
