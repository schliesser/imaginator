<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use Schliesser\Imaginator\Imaging\Local\LocalSyncImageProcessor;
use Schliesser\Imaginator\Tests\Functional\UsesImageProcessing;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LocalSyncImageProcessorTest extends FunctionalTestCase
{
    use UsesImageProcessing;

    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance['GFX'] = $this->imageProcessingGfxConfiguration();
        parent::setUp();
    }

    private function processor(): LocalSyncImageProcessor
    {
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $cropResolver = new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class));

        return new LocalSyncImageProcessor(
            new LocalImageProcessor(
                new SignedUrlBuilder(['test-secret']),
                $imageService,
                new CropCalculator(),
                $cropResolver,
                GeneralUtility::makeInstance(ProcessedFileRepository::class),
            ),
        );
    }

    private function variant(): ImageVariant
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

        return new ImageVariant(false, $file->getUid(), 'default', 1280, 720, 'webp', 72);
    }

    public function testIsNotOffloaded(): void
    {
        self::assertFalse($this->processor()->isOffloaded());
    }

    public function testBuildUrlProcessesSynchronouslyAndReturnsStaticProcessedFileUrl(): void
    {
        // Sync mode writes the processed-file URL straight into srcset: a static /…/_processed_/…
        // path the webserver serves directly, never the signed /_imaginator/ middleware endpoint.
        $url = $this->processor()->buildUrl($this->variant());

        self::assertStringNotContainsString('/_imaginator/', $url);
        self::assertStringContainsString('_processed_', $url);
        self::assertStringStartsWith('/', $url);

        // The URL must point at a real, non-empty file on disk (cold-render materialized it).
        self::assertFileExists($this->instancePath . '/' . ltrim($url, '/'));
    }

    public function testMaterializeProducesProcessedImageAt1280Webp(): void
    {
        $processed = $this->processor()->materialize($this->variant());

        self::assertSame('image/webp', $processed->mimeType);
        $size = getimagesize($processed->absolutePath);
        self::assertNotFalse($size);
        self::assertSame(1280, $size[0]);
    }
}
