<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Lqip;

use Schliesser\Imaginator\Lqip\DominantColorGenerator;
use TYPO3\CMS\Core\Resource\FileInterface;
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

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/' . $name, $targetDir . $name);

        return $storage->getFile($name);
    }

    public function testReturnsApproxDominantHex(): void
    {
        $hex = GeneralUtility::makeInstance(DominantColorGenerator::class)
            ->generate($this->fixtureFile('warm.jpg'));

        self::assertNotNull($hex);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        self::assertEqualsWithDelta(0x8a, $r, 24);
        self::assertEqualsWithDelta(0x7f, $g, 24);
        self::assertEqualsWithDelta(0x6e, $b, 24);
    }
}
