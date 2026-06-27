<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use Schliesser\Imaginator\Tests\Functional\UsesImageProcessing;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LocalImageProcessorTest extends FunctionalTestCase
{
    use UsesImageProcessing;

    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance['GFX'] = $this->imageProcessingGfxConfiguration();
        parent::setUp();
    }

    private function processor(): LocalImageProcessor
    {
        return new LocalImageProcessor(
            new SignedUrlBuilder(['test-secret']),
            GeneralUtility::makeInstance(ImageService::class),
            new CropCalculator(),
            new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class)),
            GeneralUtility::makeInstance(ProcessedFileRepository::class),
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

    public function testBuildUrlReturnsSignedEndpoint(): void
    {
        $url = $this->processor()->buildUrl($this->variant());
        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/f\d+/default/1280x720\.webp$#',
            $url
        );
    }

    public function testBuildUrlShortCircuitsToStaticUrlOnceTheDerivativeExists(): void
    {
        $variant = $this->variant();

        // Cold cache: no derivative yet, so srcset points at the signed middleware endpoint and
        // processing is deferred to the first request.
        self::assertStringContainsString('/_imaginator/', $this->processor()->buildUrl($variant));

        $this->processor()->materialize($variant);

        // Warm cache: the derivative now exists, so buildUrl returns the static _processed_ file URL
        // directly — the browser skips the /_imaginator/ middleware (and its 302) entirely.
        $warm = $this->processor()->buildUrl($variant);
        self::assertStringNotContainsString('/_imaginator/', $warm);
        self::assertStringContainsString('_processed_', $warm);
        self::assertStringStartsWith('/', $warm);
    }

    public function testMaterializeProducesProcessedImageAt1280Webp(): void
    {
        $processed = $this->processor()->materialize($this->variant());

        self::assertSame('image/webp', $processed->mimeType);
        $size = getimagesize($processed->absolutePath);
        self::assertNotFalse($size);
        self::assertSame(1280, $size[0]);
    }

    public function testMaterializeThrowsOnEmptyProcessedOutput(): void
    {
        // Some processors (e.g. GraphicsMagick/libheif failing to encode AVIF at large sizes) exit 0
        // but leave a 0-byte file. Serving a 302 to that yields a broken image; fail loudly instead.
        $emptyFile = $this->instancePath . '/empty.avif';
        touch($emptyFile);

        $processedFile = $this->createMock(ProcessedFile::class);
        $processedFile->method('getForLocalProcessing')->willReturn($emptyFile);
        $processedFile->method('getMimeType')->willReturn('image/avif');

        // Fake the ImageService so it returns the 0-byte derivative without invoking a real processor.
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('applyProcessingInstructions')->willReturn($processedFile);

        $processor = new LocalImageProcessor(
            new SignedUrlBuilder(['test-secret']),
            $imageService,
            new CropCalculator(),
            new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class)),
            GeneralUtility::makeInstance(ProcessedFileRepository::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1718100001);
        $processor->materialize($this->variant());
    }

    public function testMaterializePublicUrlIsRootRelative(): void
    {
        // The middleware emits publicUrl as a Location header; a path without a leading slash
        // would be resolved against the /_imaginator/... request path and 404.
        $processed = $this->processor()->materialize($this->variant());

        self::assertStringStartsWith('/', $processed->publicUrl);
    }

    public function testReferenceVariantAppliesEditorCropAndIsSignedAsReference(): void
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);
        self::assertInstanceOf(ResourceStorage::class, $storage);
        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/source-4000.jpg', $targetDir . 'source-4000.jpg');
        $file = $storage->getFile('source-4000.jpg'); // 4000x3000
        self::assertInstanceOf(File::class, $file);

        // Editor crop variant: the top-left 2000x1500 quadrant, no focus.
        $crop = json_encode([
            'default' => [
                'cropArea' => ['x' => 0.0, 'y' => 0.0, 'width' => 0.5, 'height' => 0.5],
                'focusArea' => ['x' => 0.0, 'y' => 0.0, 'width' => 0.0, 'height' => 0.0],
                'selectedRatio' => 'NaN',
            ],
        ], JSON_THROW_ON_ERROR);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        $connection->insert('sys_file_reference', [
            'pid' => 0,
            'uid_local' => $file->getUid(),
            'uid_foreign' => 1,
            'tablenames' => 'tt_content',
            'fieldname' => 'image',
            'crop' => $crop,
        ]);
        $referenceUid = (int) $connection->lastInsertId();

        $variant = new ImageVariant(true, $referenceUid, 'default', 1000, 563, 'webp', 72); // 16:9 within the crop

        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/r' . $referenceUid . '/default/1000x563\.webp$#',
            $this->processor()->buildUrl($variant),
        );

        $processed = $this->processor()->materialize($variant);
        self::assertSame('image/webp', $processed->mimeType);
        $size = getimagesize($processed->absolutePath);
        self::assertNotFalse($size);
        self::assertSame(1000, $size[0]); // cropped + scaled to the requested width
    }
}
