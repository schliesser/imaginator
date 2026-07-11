<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Configuration\Settings;
use Schliesser\Imaginator\Configuration\SettingsFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class SettingsFactoryTest extends TestCase
{
    public function testReadsExtensionConfigurationAndMapsQualityToQualities(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('imaginator')->willReturn([
            'ladder' => '200,400,800',
            'maxDimension' => '1600',
            'format' => 'webp',
            'quality' => ['avif' => 40, 'webp' => 70],
            'processor' => 'local:async',
            'lqip' => 'dominant-color',
        ]);

        $settings = (new SettingsFactory($extensionConfiguration))->create();

        self::assertSame([200, 400, 800], $settings->ladder);
        self::assertSame(1600, $settings->maxDimension);
        self::assertSame('webp', $settings->format);
        self::assertSame(['avif' => 40, 'webp' => 70], $settings->qualities);
        self::assertSame('dominant-color', $settings->lqip);
        self::assertNotSame('', $settings->secret);
    }

    public function testFallsBackToDefaultsWhenNotConfigured(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new \RuntimeException('not configured'));

        $settings = (new SettingsFactory($extensionConfiguration))->create();

        self::assertSame(Settings::DEFAULT_LADDER, $settings->ladder);
        self::assertSame(Settings::DEFAULT_LQIP, $settings->lqip);
    }
}
