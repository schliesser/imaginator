<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\Local\Backend\GraphicsMagickBackend;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LocalImageProcessorTest extends FunctionalTestCase
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

    private function processor(): LocalImageProcessor
    {
        return new LocalImageProcessor(
            new SignedUrlBuilder(['test-secret']),
            new GraphicsMagickBackend(GeneralUtility::makeInstance(ImageService::class)),
            GeneralUtility::makeInstance(ResourceFactory::class),
            GeneralUtility::makeInstance(ImageService::class),
        );
    }

    private function variant(): ImageVariant
    {
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storageUid = $storageRepository->createLocalStorage('Fixtures', 'fileadmin/', 'relative', '', true);
        $storage = $storageRepository->findByUid($storageUid);

        $targetDir = $this->instancePath . '/fileadmin/';
        GeneralUtility::mkdir_deep($targetDir);
        copy(__DIR__ . '/../Fixtures/Images/source-4000.jpg', $targetDir . 'source-4000.jpg');
        $file = $storage->getFile('source-4000.jpg');

        return new ImageVariant($storageUid, $file->getUid(), 'default', 1280, 720, 'webp', 72);
    }

    public function testIsNotOffloaded(): void
    {
        self::assertFalse($this->processor()->isOffloaded());
    }

    public function testBuildUrlReturnsSignedEndpoint(): void
    {
        $url = $this->processor()->buildUrl($this->variant());
        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/\d+-\d+/default/1280x720\.webp$#',
            $url
        );
    }

    public function testMaterializeProducesProcessedImageAt1280Webp(): void
    {
        $processed = $this->processor()->materialize($this->variant());

        self::assertSame('image/webp', $processed->mimeType);
        $size = getimagesize($processed->absolutePath);
        self::assertNotFalse($size);
        self::assertSame(1280, $size[0]);
    }

    public function testMaterializePublicUrlIsRootRelative(): void
    {
        // The middleware emits publicUrl as a Location header; a path without a leading slash
        // would be resolved against the /_imaginator/... request path and 404.
        $processed = $this->processor()->materialize($this->variant());

        self::assertStringStartsWith('/', $processed->publicUrl);
    }
}
