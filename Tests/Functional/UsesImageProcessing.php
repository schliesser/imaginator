<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional;

/**
 * Shared GFX configuration for functional tests that exercise real image processing.
 *
 * Local processing drives TYPO3's ImageService, which shells out to ImageMagick (the de-facto
 * standard in the TYPO3 world). The binary location differs per environment, so the path is taken
 * from the `typo3ProcessorPath` env var and defaults to `/usr/bin/` (where the CI `imagemagick`
 * apt package lands). Locally, point it at your install, e.g.:
 *
 *   typo3ProcessorPath=/opt/homebrew/bin/ .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
 *
 * Consumers must set `$this->configurationToUseInTestInstance['GFX']` in setUp() *before* calling
 * parent::setUp() (the testing framework reads the property while booting the instance).
 */
trait UsesImageProcessing
{
    /**
     * @return array<string, mixed>
     */
    protected function imageProcessingGfxConfiguration(): array
    {
        return [
            'processor_enabled' => true,
            'processor' => 'ImageMagick',
            'processor_path' => getenv('typo3ProcessorPath') ?: '/usr/bin/',
            'processor_effects' => false,
            'imagefile_ext' => 'gif,jpg,jpeg,png,webp,tif,bmp,svg',
        ];
    }
}
