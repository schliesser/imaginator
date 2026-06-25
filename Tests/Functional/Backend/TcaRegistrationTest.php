<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Backend;

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TcaRegistrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    public function testRenderTypeIsConfiguredOnTheColumn(): void
    {
        self::assertSame(
            'imaginatorAspectRatios',
            $GLOBALS['TCA']['tt_content']['columns']['aspect_ratio']['config']['renderType'],
        );
    }

    public function testNodeRegistryResolvesRenderTypeToElement(): void
    {
        $registry = $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'] ?? [];
        $classes = [];
        foreach ($registry as $entry) {
            if (($entry['nodeName'] ?? null) === 'imaginatorAspectRatios') {
                $classes[] = $entry['class'] ?? null;
            }
        }

        self::assertContains(AspectRatiosElement::class, $classes);
    }

    public function testLegacyPrefixedColumnsAreGone(): void
    {
        // The field is the unprefixed `aspect_ratio` (matching pictureino, so no DB migration is
        // needed). Guard against the earlier extension-prefixed names creeping back in.
        $columns = $GLOBALS['TCA']['tt_content']['columns'];
        self::assertArrayNotHasKey('tx_imaginator_aspect_ratio', $columns);
        self::assertArrayNotHasKey('tx_imaginator_aspect_ratios', $columns);
        self::assertArrayHasKey('aspect_ratio', $columns);
    }

    public function testFieldIsAddedToMediaAdjustmentsPalette(): void
    {
        // The field rides the media element's mediaAdjustments palette, so it shows only on CEs that
        // render images rather than on every content type.
        $showitem = $GLOBALS['TCA']['tt_content']['palettes']['mediaAdjustments']['showitem'] ?? '';
        self::assertIsString($showitem);
        self::assertStringContainsString('aspect_ratio', $showitem);
    }

    public function testDefaultCropVariantOffersAFocusAreaWithCoreAspectRatios(): void
    {
        // The feature toggle defaults on, so a real boot must register the focus area on the default
        // crop variant of file references — keeping core's stock ratio set intact (declaring
        // cropVariants at all drops core's implicit default, so we re-supply it verbatim).
        $default = $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config']['cropVariants']['default'] ?? null;
        self::assertIsArray($default);
        self::assertSame(['16:9', '3:2', '4:3', '1:1', 'NaN'], array_keys($default['allowedAspectRatios']));
        self::assertSame(
            ['x' => 1 / 3, 'y' => 1 / 3, 'width' => 1 / 3, 'height' => 1 / 3],
            $default['focusArea'],
        );
    }
}
