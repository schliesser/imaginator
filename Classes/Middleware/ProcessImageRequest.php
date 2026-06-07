<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Url\CanonicalParams;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Serves the signed image endpoint. The candidate URL *is* the image: verify the HMAC
 * signature, re-check the width against the ladder (so a leaked secret still cannot trigger
 * arbitrary-size processing), materialize the derivative and 302-redirect to the processed
 * file with immutable cache headers. No JSON, ever. Forged/invalid signatures get a 403.
 */
final readonly class ProcessImageRequest implements MiddlewareInterface
{
    private const PREFIX = '/_imaginator/';

    public function __construct(
        private SignedUrlBuilder $signedUrlBuilder,
        private LadderFactory $ladderFactory,
        private ResourceFactory $resourceFactory,
        private ImageProcessorInterface $processor,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, self::PREFIX)) {
            return $handler->handle($request);
        }

        $params = $this->signedUrlBuilder->verify($path);
        if ($params === null) {
            return $this->responseFactory->createResponse(403);
        }

        if (!$this->isRungWidth($params)) {
            return $this->responseFactory->createResponse(403);
        }

        $processed = $this->processor->materialize($this->toVariant($params));

        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $processed->publicUrl)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }

    /**
     * Defense in depth: even with a valid signature, only rung-quantized widths may be served.
     */
    private function isRungWidth(CanonicalParams $params): bool
    {
        $file = $params->isReference
            ? $this->resourceFactory->getFileReferenceObject($params->uid)->getOriginalFile()
            : $this->resourceFactory->getFileObject($params->uid);
        $sourceWidth = (int)$file->getProperty('width');
        $sourceHeight = (int)$file->getProperty('height');
        // The variant's own w:h is its target ratio, so the verify path applies the same
        // source-height clamp as the render path.
        $ratio = new AspectRatio(max(1, $params->width), max(1, $params->height));

        return $this->ladderFactory->nearestRung($params->width, $sourceWidth, $ratio, $sourceHeight) === $params->width;
    }

    private function toVariant(CanonicalParams $params): ImageVariant
    {
        return new ImageVariant(
            $params->isReference,
            $params->uid,
            $params->cropVariant,
            $params->width,
            $params->height,
            $params->format,
            0,
        );
    }
}
