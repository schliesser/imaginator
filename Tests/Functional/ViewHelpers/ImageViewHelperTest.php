<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\ViewHelpers;

use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ImageViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    protected array $configurationToUseInTestInstance = [
        'GFX' => [
            'processor_enabled' => true,
            'processor' => 'GraphicsMagick',
            'processor_path' => '/usr/bin/',
            'processor_effects' => false,
            'imagefile_ext' => 'gif,jpg,jpeg,png,webp,tif,bmp,svg',
        ],
    ];

    private function importFixture(): int
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/source-4000.jpg', $targetDir . 'source-4000.jpg');

        return $storage->getFile('source-4000.jpg')->getUid();
    }

    private function render(string $template): string
    {
        $templateFile = $this->instancePath . '/imaginator-test-template.html';
        file_put_contents($templateFile, $template);

        $view = $this->get(ViewFactoryInterface::class)
            ->create(new ViewFactoryData(templatePathAndFilename: $templateFile));

        return $view->render();
    }

    public function testRendersImgWithLadderForSingleRatio(): void
    {
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        self::assertStringContainsString('<img ', $output);
        self::assertStringContainsString('srcset="', $output);
        self::assertStringContainsString('sizes="auto"', $output);
        self::assertStringContainsString('/_imaginator/', $output);
        self::assertStringContainsString('.webp', $output);
        self::assertStringContainsString('alt="A hero"', $output);
    }

    public function testWithoutAspectRatioUsesTheOriginalImageRatio(): void
    {
        // Fixture is 4000x3000 (4:3); largest rung = 2000 wide -> 1500 tall.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '" alt="Native"/></html>'
        );

        self::assertMatchesRegularExpression('/<img [^>]*width="2000" height="1500"/', $output);
    }

    public function testEmitsFormatTiersAndCspFriendlyLqip(): void
    {
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        // Stacked AVIF + WebP <source> tiers from the default formats setting.
        self::assertStringContainsString('<picture>', $output);
        self::assertStringContainsString('type="image/avif"', $output);
        self::assertStringContainsString('type="image/webp"', $output);

        // The <img> carries an LQIP class, never an inline style attribute (CSP-friendly).
        self::assertMatchesRegularExpression('/<img [^>]*class="imaginator-lqip-[0-9a-f]{12}"/', $output);
        self::assertStringNotContainsString('style=', $output);

        // The actual rule is registered as a (nonce-able) inline stylesheet via the AssetCollector.
        $styles = GeneralUtility::makeInstance(AssetCollector::class)->getInlineStyleSheets();
        $rules = implode("\n", array_column($styles, 'source'));
        self::assertMatchesRegularExpression('/\.imaginator-lqip-[0-9a-f]{12}\{background-image:url\(data:image\//', $rules);
    }

    public function testLqipSettingIsAppliedFromExtensionConfiguration(): void
    {
        // Instance-wide Extension Configuration must actually change behaviour.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = ['lqip' => 'dominant-color'];
        $fileUid = $this->importFixture();

        $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        $styles = GeneralUtility::makeInstance(AssetCollector::class)->getInlineStyleSheets();
        $rules = implode("\n", array_column($styles, 'source'));
        // dominant-color -> a solid hex background, not a ThumbHash data-URI.
        self::assertMatchesRegularExpression('/\.imaginator-lqip-[0-9a-f]{12}\{background:#[0-9a-f]{6}\}/', $rules);
        self::assertStringNotContainsString('background-image:url(data:', $rules);
    }
}
