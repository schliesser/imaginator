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
            $GLOBALS['TCA']['tt_content']['columns']['tx_imaginator_aspect_ratios']['config']['renderType'],
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

    public function testInterimSingleRatioColumnIsGone(): void
    {
        self::assertArrayNotHasKey('tx_imaginator_aspect_ratio', $GLOBALS['TCA']['tt_content']['columns']);
    }
}
