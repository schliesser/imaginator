<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\ViewHelpers;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Schliesser\Imaginator\Tests\Functional\UsesImageProcessing;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ImageViewHelperTest extends FunctionalTestCase
{
    use UsesImageProcessing;

    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance['GFX'] = $this->imageProcessingGfxConfiguration();
        parent::setUp();
    }

    private function importFixture(): int
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);
        self::assertInstanceOf(ResourceStorage::class, $storage);

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/source-4000.jpg', $targetDir . 'source-4000.jpg');

        $file = $storage->getFile('source-4000.jpg');
        self::assertInstanceOf(File::class, $file);

        return $file->getUid();
    }

    private function importSvgFixture(): int
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);
        self::assertInstanceOf(ResourceStorage::class, $storage);

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        file_put_contents(
            $targetDir . 'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"></svg>',
        );

        $file = $storage->getFile('logo.svg');
        self::assertInstanceOf(File::class, $file);

        return $file->getUid();
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $variables = []): string
    {
        $templateFile = $this->instancePath . '/imaginator-test-' . md5($template) . '.html';
        file_put_contents($templateFile, $template);

        $view = $this->get(ViewFactoryInterface::class)
            ->create(new ViewFactoryData(templatePathAndFilename: $templateFile));
        $view->assignMultiple($variables);

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
        self::assertStringContainsString('.avif', $output);
        self::assertStringContainsString('alt="A hero"', $output);
    }

    public function testImgIsAvifByDefaultRegardlessOfSourceFormat(): void
    {
        // Source is a JPEG, yet the <img> must be the configured output format (AVIF default).
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        self::assertMatchesRegularExpression('/<img [^>]*src="[^"]*\.avif"/', $output);
        self::assertDoesNotMatchRegularExpression('/<img [^>]*src="[^"]*\.(jpe?g)"/', $output);
    }

    public function testExcludedExtensionIsRenderedAsPassthroughImg(): void
    {
        // SVG is in the default excludeExtensions: served as-is, never run through the ladder.
        $fileUid = $this->importSvgFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' alt="Vector" class="logo" priority="1"/></html>'
        );

        self::assertMatchesRegularExpression('/<img [^>]*src="[^"]*logo\.svg"/', $output);
        self::assertStringContainsString('alt="Vector"', $output);
        self::assertStringContainsString('class="logo"', $output);
        self::assertStringContainsString('fetchpriority="high"', $output);
        // No processing machinery: no picture, no srcset, no signed endpoint, no width/height clamp.
        self::assertStringNotContainsString('<picture>', $output);
        self::assertStringNotContainsString('srcset', $output);
        self::assertStringNotContainsString('/_imaginator/', $output);
    }

    public function testProcessedImageRegistersSizesAutoPolyfill(): void
    {
        // A processed image emits sizes="auto"; Safari has no native support, so the autosizes
        // polyfill must be queued via the AssetCollector — head-priority + async (never blocking).
        $fileUid = $this->importFixture();

        $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        $scripts = GeneralUtility::makeInstance(AssetCollector::class)->getJavaScripts();
        self::assertArrayHasKey('imaginator-autosizes', $scripts);
        $entry = $scripts['imaginator-autosizes'];
        self::assertStringContainsString('Resources/Public/JavaScript/frontend/autosizes.js', $entry['source']);
        self::assertSame('async', $entry['attributes']['async'] ?? null);
        self::assertTrue($entry['options']['priority'] ?? false);
        // Nonce-tagged for a strict CSP: v14 uses `csp`, v13 the (deprecated) `useNonce`.
        $cspKey = (new Typo3Version())->getMajorVersion() >= 14 ? 'csp' : 'useNonce';
        self::assertTrue($entry['options'][$cspKey] ?? false);
    }

    public function testPassthroughImageDoesNotRegisterPolyfill(): void
    {
        // A passthrough (SVG) image has no sizes="auto" srcset, so the polyfill is pointless here.
        $fileUid = $this->importSvgFixture();

        $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '" alt="Vector"/></html>'
        );

        $scripts = GeneralUtility::makeInstance(AssetCollector::class)->getJavaScripts();
        self::assertArrayNotHasKey('imaginator-autosizes', $scripts);
    }

    public function testWithoutAspectRatioUsesTheOriginalImageRatio(): void
    {
        // Fixture is 4000x3000 (4:3); largest rung = 3840 wide -> 2880 tall.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '" alt="Native"/></html>'
        );

        self::assertMatchesRegularExpression('/<img [^>]*width="3840" height="2880"/', $output);
    }

    public function testAspectRatioMapWithBreakpointAliasesEmitsMinWidthSources(): void
    {
        // pictureino-compat: {alias: ratio} keys resolve to the configured breakpoint min-widths,
        // largest-first, with the min-0 (xs) tier as the base <img>.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="{xs: \'1:1\', md: \'4:3\', lg: \'16:9\'}" alt="A hero"/></html>'
        );

        self::assertStringContainsString('<picture>', $output);
        self::assertStringContainsString('media="(min-width:992px)"', $output);
        self::assertStringContainsString('media="(min-width:768px)"', $output);
        // The alias is never leaked verbatim as a media string.
        self::assertStringNotContainsString('media="lg"', $output);
        self::assertStringNotContainsString('media="md"', $output);
        // Each <source> carries its own width/height so the browser sizes the box to the selected
        // breakpoint's ratio (without it an art-directed <picture> never changes shape).
        self::assertMatchesRegularExpression('/<source [^>]*media="\(min-width:992px\)"[^>]*width="\d+" height="\d+">/', $output);
    }

    public function testAspectRatioAcceptsRawJsonMapFromDatabase(): void
    {
        // The `aspect_ratio` column stores the breakpoint map as a JSON string. Templates that bind
        // it directly (no AspectRatioProcessor step) pass that raw JSON; the ViewHelper must decode
        // it like a map instead of choking on it as a single ratio.
        $fileUid = $this->importFixture();

        // Bound from a variable so Fluid passes the raw JSON STRING (binding the `{...}` inline would
        // make Fluid build an array instead, which is the already-covered map case).
        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="{ratio}" alt="A hero"/></html>',
            ['ratio' => '{"xs":"1:1","md":"4:3","lg":"16:9"}'],
        );

        self::assertStringContainsString('<picture>', $output);
        self::assertStringContainsString('media="(min-width:992px)"', $output);
        self::assertStringContainsString('media="(min-width:768px)"', $output);
        self::assertStringNotContainsString('Invalid ratio', $output);
    }

    public function testAspectRatioMapWithIntegerKeysEmitsMinWidthSources(): void
    {
        // Integer keys are literal min-widths in px; key 0 is the base <img>.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="{0: \'1:1\', 1400: \'21:9\'}" alt="A hero"/></html>'
        );

        self::assertStringContainsString('media="(min-width:1400px)"', $output);
        self::assertStringNotContainsString('media="1400"', $output);
    }

    public function testAspectRatioMapResolvingToNothingFallsBackToNativeRatio(): void
    {
        // All keys unknown / ratios auto -> the map resolves to []. The image must still render at
        // its native ratio rather than crash the renderer with an empty breakpoint set.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="{xxl: \'16:9\', md: \'auto\'}" alt="A hero"/></html>'
        );

        // Fixture is 4000x3000 (4:3); native largest rung = 3840 wide -> 2880 tall.
        self::assertMatchesRegularExpression('/<img [^>]*width="3840" height="2880"/', $output);
        self::assertStringNotContainsString('Oops', $output);
    }

    public function testScalarPxHeightEmitsPerDprResolutionSources(): void
    {
        // A full-bleed hero: aspectRatio="600px" pins the height by real DPR. The 1x <img> is 600px
        // tall; min-resolution <source>s serve 2x (1200) and 3x (1800), all in the single output format.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="600px" alt="A hero"/></html>'
        );

        // 1x base <img> keeps the flat 600px height.
        self::assertMatchesRegularExpression('/<img [^>]*width="3840" height="600"/', $output);
        // DPR-gated sources for 2x and 3x. Single format -> no <source type=…> stacking.
        self::assertStringContainsString('media="(min-resolution:1.5dppx)"', $output);
        self::assertStringContainsString('media="(min-resolution:2.5dppx)"', $output);
        self::assertStringNotContainsString('type="image/', $output);
        // Heights are flat per tier: 600 / 1200 / 1800, all AVIF.
        self::assertStringContainsString('x600.avif', $output);
        self::assertStringContainsString('x1200.avif', $output);
        self::assertStringContainsString('x1800.avif', $output);
    }

    public function testMixedRatioAndFixedHeightMapCombinesMinWidthAndResolution(): void
    {
        // {xs: "16:9", lg: "600px"}: base <img> keeps the 16:9 ladder (3840x2160); the lg tier pins a
        // flat height per DPR, its media combining the breakpoint min-width with min-resolution.
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="{xs: \'16:9\', lg: \'600px\'}" alt="A hero"/></html>'
        );

        self::assertStringContainsString('<picture>', $output);
        self::assertStringContainsString('media="(min-width:992px) and (min-resolution:1.5dppx)"', $output);
        self::assertMatchesRegularExpression('/<img [^>]*width="3840" height="2160"/', $output);
        // lg 1x source keeps the flat 600px height.
        self::assertStringContainsString('x600.avif', $output);
    }

    public function testSingleRatioRendersBareAvifImgWithCspFriendlyLqip(): void
    {
        $fileUid = $this->importFixture();

        $output = $this->render(
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' aspectRatio="16:9" alt="A hero"/></html>'
        );

        // A single ratio is a bare <img> in the single output format — no <picture> shell, no format
        // stacking (<picture> is only used for art-direction).
        self::assertStringNotContainsString('<picture>', $output);
        self::assertStringNotContainsString('type="image/', $output);
        self::assertMatchesRegularExpression('/<img [^>]*src="[^"]*\.avif"/', $output);

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
