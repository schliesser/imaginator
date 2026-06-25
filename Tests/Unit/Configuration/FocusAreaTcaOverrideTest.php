<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;

/**
 * The focus-area TCA override touches only $GLOBALS, so it can be exercised as pure PHP without
 * booting TYPO3 — which lets us cover the branches a functional test cannot reach (the feature
 * toggle is read once at boot, so the off-path and the no-clobber guards are unit-only).
 */
final class FocusAreaTcaOverrideTest extends TestCase
{
    private const OVERRIDE = __DIR__ . '/../../../Configuration/TCA/Overrides/sys_file_reference.php';

    protected function setUp(): void
    {
        if (!defined('TYPO3')) {
            define('TYPO3', 'unit');
        }
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'] = true;
        $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config'] = ['type' => 'imageManipulation'];
    }

    private function load(): void
    {
        include self::OVERRIDE;
    }

    /**
     * @return array<string, mixed>
     */
    private function default(): array
    {
        return $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config']['cropVariants']['default'];
    }

    public function testDisabledToggleLeavesTcaUntouched(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'] = false;
        $this->load();

        self::assertArrayNotHasKey(
            'cropVariants',
            $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config'],
        );
    }

    public function testEnabledRegistersDefaultVariantWithCoreRatiosAndFocusArea(): void
    {
        $this->load();

        $default = $this->default();
        self::assertSame(['16:9', '3:2', '4:3', '1:1', 'NaN'], array_keys($default['allowedAspectRatios']));
        self::assertSame(['x' => 1 / 3, 'y' => 1 / 3, 'width' => 1 / 3, 'height' => 1 / 3], $default['focusArea']);
    }

    public function testKeepsAnExistingDefaultVariantAndOnlyAddsFocusArea(): void
    {
        $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config']['cropVariants']['default'] = [
            'title' => 'Custom',
            'allowedAspectRatios' => ['1:1' => ['title' => 'Square', 'value' => 1.0]],
        ];

        $this->load();

        $default = $this->default();
        self::assertSame('Custom', $default['title']);
        self::assertSame(['1:1'], array_keys($default['allowedAspectRatios']));
        self::assertArrayHasKey('focusArea', $default);
    }

    public function testDoesNotClobberAnExistingFocusArea(): void
    {
        $GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config']['cropVariants']['default'] = [
            'allowedAspectRatios' => ['NaN' => ['title' => 'Free', 'value' => 0.0]],
            'focusArea' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2],
        ];

        $this->load();

        self::assertSame(['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2], $this->default()['focusArea']);
    }
}
