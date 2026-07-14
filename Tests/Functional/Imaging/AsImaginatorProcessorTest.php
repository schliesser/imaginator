<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\Imaging\ImageProcessorFactory;
use Schliesser\ImaginatorFixture\AttributeProcessor\AttributeTaggedProcessor;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * End-to-end registration through a real container compile: the fixture extension carries no
 * `imaginator.image_processor` yaml tags — both processors below exist purely because of
 * #[AsImaginatorProcessor] and the ProcessorRegistrationPass.
 */
final class AsImaginatorProcessorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'schliesser/imaginator',
        __DIR__ . '/../Fixtures/Extensions/attribute_processor',
    ];

    /**
     * @param array<string, mixed> $config
     */
    private function createWith(array $config): object
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = $config;

        return $this->get(ImageProcessorFactory::class)->create();
    }

    public function testAttributeOnProcessorInterfaceClassRegistersItUnderItsKey(): void
    {
        self::assertInstanceOf(
            AttributeTaggedProcessor::class,
            $this->createWith(['processor' => 'fixture:attribute']),
        );
    }

    public function testAttributeOnUrlBuilderClassRegistersASynthesizedExternalProcessor(): void
    {
        $processor = $this->createWith([
            'processor' => 'dummy-cdn',
            'processorBaseUrl' => 'https://dummy-cdn.example',
        ]);

        self::assertInstanceOf(ExternalImageProcessor::class, $processor);
        self::assertTrue($processor->isOffloaded());
    }

    public function testSynthesizedProcessorBuildsUrlsWithTheDummyGrammar(): void
    {
        $processor = $this->createWith([
            'processor' => 'dummy-cdn',
            'processorBaseUrl' => 'https://dummy-cdn.example',
        ]);
        self::assertInstanceOf(ExternalImageProcessor::class, $processor);

        $url = $processor->buildUrl(new ImageVariant(false, $this->fileUid(), 'default', 2000, 1125, 'webp', 72));

        self::assertSame('https://dummy-cdn.example/dummy/2000x1125/q72/fileadmin/source-4000.jpg', $url);
    }

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
}
