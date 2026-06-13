<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Lqip;

use Schliesser\Imaginator\Lqip\DominantColorGenerator;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class DominantColorGeneratorTest extends FunctionalTestCase
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

    public function testReturnsApproxDominantHex(): void
    {
        $hex = GeneralUtility::makeInstance(DominantColorGenerator::class)
            ->generate($this->fixtureFile('warm.jpg'));

        self::assertNotNull($hex);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);

        $rgb = sscanf($hex, '#%02x%02x%02x');
        self::assertIsArray($rgb);
        [$r, $g, $b] = $rgb;
        self::assertEqualsWithDelta(0x8a, $r, 24);
        self::assertEqualsWithDelta(0x7f, $g, 24);
        self::assertEqualsWithDelta(0x6e, $b, 24);
    }

    public function testReturnsNullForImageWithTransparency(): void
    {
        // A solid colour painted behind the sharp image would shine through the image's transparent
        // pixels, so transparent sources must get no placeholder.
        $hex = GeneralUtility::makeInstance(DominantColorGenerator::class)
            ->generate($this->fixtureFile('transparent.png'));

        self::assertNull($hex);
    }
}
