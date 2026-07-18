<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\UrlBuilder\ImgproxyUrlBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExternalImageProcessorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    private function fileUid(): int
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

    private function processor(): ExternalImageProcessor
    {
        return new ExternalImageProcessor(
            new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example:8081')),
            GeneralUtility::makeInstance(ResourceFactory::class),
            new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class)),
            new CropCalculator(),
        );
    }

    public function testIsOffloaded(): void
    {
        self::assertTrue($this->processor()->isOffloaded());
    }

    public function testBuildUrlPointsAtProviderWithSourceAndDimensions(): void
    {
        $variant = new ImageVariant(false, $this->fileUid(), 'default', 2000, 1125, 'webp', 72);
        $url = $this->processor()->buildUrl($variant);

        self::assertStringStartsWith('https://imgproxy.example:8081/insecure/rs:fill:2000:1125/g:sm/q:72/plain/', $url);
        self::assertStringContainsString('source-4000.jpg@webp', $url);
    }

    public function testReferenceVariantReplaysEditorCropInProviderUrl(): void
    {
        // Editor crop variant: the top-left 2000x1500 quadrant of the 4000x3000 source, no focus.
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
            'uid_local' => $this->fileUid(),
            'uid_foreign' => 1,
            'tablenames' => 'tt_content',
            'fieldname' => 'image',
            'crop' => $crop,
        ]);
        $referenceUid = (int) $connection->lastInsertId();

        $variant = new ImageVariant(true, $referenceUid, 'default', 1000, 563, 'webp', 72); // 16:9 within the crop
        $url = $this->processor()->buildUrl($variant);

        // CropCalculator fit: 1000:563 inside 2000x1500 → 2000x1126, centered vertically at y=187.
        self::assertStringStartsWith(
            'https://imgproxy.example:8081/insecure/c:2000:1126:nowe:0:187/rs:fill:1000:563/q:72/plain/',
            $url,
        );
        self::assertStringContainsString('source-4000.jpg@webp', $url);
    }

    public function testCroplessReferenceFallsBackToSmartGravity(): void
    {
        // No editor crop (empty crop field) → nothing to replay; the provider's smart
        // detection beats a synthetic center crop.
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
        $connection->insert('sys_file_reference', [
            'pid' => 0,
            'uid_local' => $this->fileUid(),
            'uid_foreign' => 1,
            'tablenames' => 'tt_content',
            'fieldname' => 'image',
            'crop' => '',
        ]);
        $referenceUid = (int) $connection->lastInsertId();

        $variant = new ImageVariant(true, $referenceUid, 'default', 1000, 563, 'webp', 72);

        self::assertStringStartsWith(
            'https://imgproxy.example:8081/insecure/rs:fill:1000:563/g:sm/q:72/plain/',
            $this->processor()->buildUrl($variant),
        );
    }

    public function testMaterializeThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1718200001);
        $this->processor()->materialize(
            new ImageVariant(false, $this->fileUid(), 'default', 2000, 1125, 'webp', 72),
        );
    }
}
