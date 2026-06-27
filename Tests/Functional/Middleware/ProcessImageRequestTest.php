<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Middleware\ProcessImageRequest;
use Schliesser\Imaginator\Tests\Functional\UsesImageProcessing;
use Schliesser\Imaginator\Url\CanonicalParams;
use Schliesser\Imaginator\UrlBuilder\LocalAsyncUrlBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ProcessImageRequestTest extends FunctionalTestCase
{
    use UsesImageProcessing;

    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    private LocalAsyncUrlBuilder $localAsyncUrlBuilder;

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance['GFX'] = $this->imageProcessingGfxConfiguration();
        parent::setUp();
        $this->localAsyncUrlBuilder = new LocalAsyncUrlBuilder(['test-secret']);
    }

    private function middleware(): ProcessImageRequest
    {
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $cropResolver = new CropResolver(GeneralUtility::makeInstance(ResourceFactory::class));

        return new ProcessImageRequest(
            $this->localAsyncUrlBuilder,
            new LadderFactory([320, 640, 1280, 1920, 2560], 2000),
            $cropResolver,
            new LocalImageProcessor(
                $this->localAsyncUrlBuilder,
                $imageService,
                new CropCalculator(),
                $cropResolver,
                GeneralUtility::makeInstance(ProcessedFileRepository::class),
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
        $path = $this->localAsyncUrlBuilder->build($this->importFixture());
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
        $request = new ServerRequest('https://example.com' . $this->localAsyncUrlBuilder->build($fixedHeight));

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('immutable', $response->getHeaderLine('Cache-Control'));
    }

    public function testFixedHeightThreeXRungRedirectsToProcessedFile(): void
    {
        // A 3x DPR variant pins a tall height (2000x1800) unrelated to the width. On the 3000x2000
        // fixture 1800 <= source height, so the reconstructed maxByHeight >= 2000 and the rung still
        // verifies — confirming the resolution tiers need no change to the signed/verify path.
        $params = $this->importFixture();
        $threeX = new CanonicalParams(
            $params->isReference,
            $params->uid,
            $params->cropVariant,
            2000,
            1800,
            $params->format,
        );
        $request = new ServerRequest('https://example.com' . $this->localAsyncUrlBuilder->build($threeX));

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(302, $response->getStatusCode());
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
        $request = new ServerRequest('https://example.com' . $this->localAsyncUrlBuilder->build($offLadder));

        $response = $this->middleware()->process($request, $this->passthroughHandler());

        self::assertSame(403, $response->getStatusCode());
    }
}
