<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Rendering;

use Schliesser\Imaginator\DataProcessing\AspectRatioProcessor;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class CeRatioIntegrationTest extends FunctionalTestCase
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

    public function testDataProcessorResolvesCeJsonToBreakpointRatios(): void
    {
        $processor = $this->get(AspectRatioProcessor::class);
        $result = $processor->process(
            GeneralUtility::makeInstance(ContentObjectRenderer::class),
            [],
            ['as' => 'imaginatorAspectRatios'],
            ['data' => ['tx_imaginator_aspect_ratios' => '{"xs":"1:1","lg":"16:9"}']],
        );

        self::assertEquals(
            [
                new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
                new BreakpointRatio(new AspectRatio(1, 1), null),
            ],
            $result['imaginatorAspectRatios'],
        );
    }

    public function testViewHelperBreakpointsArgumentDrivesPicture(): void
    {
        $fileUid = $this->importFixture();

        $templateFile = $this->instancePath . '/imaginator-ce-ratio.html';
        file_put_contents(
            $templateFile,
            '<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"'
            . ' data-namespace-typo3-fluid="true"><i:image src="' . $fileUid . '"'
            . ' breakpoints="{ratios}" alt="CE"/></html>',
        );

        $view = $this->get(ViewFactoryInterface::class)
            ->create(new ViewFactoryData(templatePathAndFilename: $templateFile));
        $view->assign('ratios', [
            new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
            new BreakpointRatio(new AspectRatio(1, 1), null),
        ]);
        $output = $view->render();

        self::assertStringContainsString('<picture>', $output);
        self::assertStringContainsString('media="(min-width:992px)"', $output);
        // lg tier crops to 16:9 at the largest rung (2000 wide -> 1125 tall).
        self::assertMatchesRegularExpression('/media="\(min-width:992px\)"[^>]*srcset="[^"]*2000x1125/', $output);
        // base <img> at 1:1 (2000 wide -> 2000 tall, clamped to source height 3000 so stays 2000).
        self::assertMatchesRegularExpression('/<img [^>]*width="2000" height="2000"/', $output);
    }
}
