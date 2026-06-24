<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\Local\Backend\GraphicsMagickBackend;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Middleware\ProcessImageRequest;
use Schliesser\Imaginator\Url\CanonicalParams;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ProcessImageRequestTest extends FunctionalTestCase
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

    private SignedUrlBuilder $signedUrlBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signedUrlBuilder = new SignedUrlBuilder(['test-secret']);
    }

    private function middleware(): ProcessImageRequest
    {
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $cropResolver = new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class));

        return new ProcessImageRequest(
            $this->signedUrlBuilder,
            new LadderFactory([320, 640, 1280, 1920, 2560], 2000),
            $cropResolver,
            new LocalImageProcessor(
                $this->signedUrlBuilder,
                new GraphicsMagickBackend($imageService),
                $imageService,
                new CropCalculator(),
                $cropResolver,
            ),
            new ResponseFactory(),
        );
    }

    private function passthroughHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 204);
            }
        };
    }

    private function importFixture(): CanonicalParams
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

        return new CanonicalParams(false, $file->getUid(), 'default', 1280, 720, 'webp');
    }

    public function testNonImaginatorPathPassesThrough(): void
    {
        $request = new ServerRequest('https://example.com/some/page');
        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(204, $response->getStatusCode());
    }

    public function testForgedSignatureReturns403(): void
    {
        $request = new ServerRequest('https://example.com/_imaginator/0000000000000000/f1/default/1280x720.webp');
        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testValidSignedUrlRedirectsToProcessedFile(): void
    {
        $path = $this->signedUrlBuilder->build($this->importFixture());
        $request = new ServerRequest('https://example.com' . $path);

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('immutable', $response->getHeaderLine('Cache-Control'));
    }

    public function testFixedHeightRungRedirectsToProcessedFile(): void
    {
        // A full-bleed hero rung pins a height unrelated to the width (1280x600, not the ratio
        // height). The verify path reconstructs AspectRatio(1280, 600); because the rendered height
        // never exceeds the source height, maxByHeight >= 1280 and the rung still validates — the
        // signed/verify path needs no fixed-height awareness.
        $params = $this->importFixture();
        $fixedHeight = new CanonicalParams(
            $params->isReference,
            $params->uid,
            $params->cropVariant,
            1280,
            600,
            $params->format,
        );
        $request = new ServerRequest('https://example.com' . $this->signedUrlBuilder->build($fixedHeight));

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('immutable', $response->getHeaderLine('Cache-Control'));
    }

    public function testSignedButNonRungWidthReturns403(): void
    {
        // A valid signature for a width that is not a ladder rung must still be rejected (leaked-secret defense).
        $params = $this->importFixture();
        $offLadder = new CanonicalParams(
            $params->isReference,
            $params->uid,
            $params->cropVariant,
            1281, // between rungs 1280 and 1920
            721,
            $params->format,
        );
        $request = new ServerRequest('https://example.com' . $this->signedUrlBuilder->build($offLadder));

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(403, $response->getStatusCode());
    }
}
