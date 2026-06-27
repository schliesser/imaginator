<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\UrlBuilder\ImgproxyUrlBuilder;
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

    public function testMaterializeThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(1718200001);
        $this->processor()->materialize(
            new ImageVariant(false, $this->fileUid(), 'default', 2000, 1125, 'webp', 72),
        );
    }
}
