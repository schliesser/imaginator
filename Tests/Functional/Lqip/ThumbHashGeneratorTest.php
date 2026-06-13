<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Lqip;

use Schliesser\Imaginator\Lqip\ThumbHashGenerator;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ThumbHashGeneratorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    private function fixtureFile(string $name): FileInterface
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);
        self::assertInstanceOf(ResourceStorage::class, $storage);

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/' . $name, $targetDir . $name);

        $file = $storage->getFile($name);
        self::assertInstanceOf(File::class, $file);

        return $file;
    }

    public function testReturnsBase64DataUri(): void
    {
        $uri = GeneralUtility::makeInstance(ThumbHashGenerator::class)
            ->generate($this->fixtureFile('source-4000.jpg'));

        self::assertNotNull($uri);
        self::assertStringStartsWith('data:image/', $uri);
        self::assertStringContainsString(';base64,', $uri);
        // Decodes to a non-trivial raster.
        $base64 = substr($uri, (int) strpos($uri, ';base64,') + 8);
        $binary = base64_decode($base64, true);
        self::assertNotFalse($binary);
        self::assertGreaterThan(64, strlen($binary));
    }
}
