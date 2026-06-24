<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Dto\ImageRenderRequest;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Rendering\PictureRenderer;

final class PictureRendererPreloadTest extends TestCase
{
    private function renderer(): PictureRenderer
    {
        return new PictureRenderer(new LadderFactory([320, 640], 2000));
    }

    private function fakeProcessor(): ImageProcessorInterface
    {
        return new class implements ImageProcessorInterface {
            public function buildUrl(ImageVariant $variant): string
            {
                return sprintf('/url/%s/%dx%d', $variant->format, $variant->width, $variant->height);
            }

            public function isOffloaded(): bool
            {
                return false;
            }

            public function materialize(ImageVariant $variant): ProcessedImage
            {
                throw new \LogicException('not used', 1717600600);
            }
        };
    }

    private function request(bool $priority): ImageRenderRequest
    {
        return new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [new BreakpointRatio(new AspectRatio(16, 9))],
            format: 'jpeg',
            quality: 72,
            alt: 'A hero',
            priority: $priority,
            formats: ['avif', 'webp'],
        );
    }

    public function testPriorityImageGetsOnePreloadLinkForTheFirstFormat(): void
    {
        $links = $this->renderer()->preloadLinks($this->request(true), $this->fakeProcessor());

        self::assertSame(
            [
                '<link rel="preload" as="image"'
                . ' imagesrcset="/url/avif/320x180 320w, /url/avif/640x360 640w"'
                . ' imagesizes="100vw" type="image/avif" fetchpriority="high">',
            ],
            $links
        );
    }

    public function testNonPriorityImageGetsNoPreloadLink(): void
    {
        self::assertSame([], $this->renderer()->preloadLinks($this->request(false), $this->fakeProcessor()));
    }

    public function testResolutionGatedHeroSkipsTheMediaNullBasePreload(): void
    {
        // The 1x base is the webp <img>, preload-scanned anyway; an avif media-null preload would
        // mismatch it and double-fetch. Only the gated (high-DPR) variants get a media-scoped preload.
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [
                new BreakpointRatio(media: '(min-resolution:1.5dppx)', fixedHeight: 1200, resolutionGated: true),
                new BreakpointRatio(media: null, fixedHeight: 600),
            ],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
            priority: true,
            formats: ['avif', 'webp'],
        );

        self::assertSame(
            [
                '<link rel="preload" as="image"'
                . ' imagesrcset="/url/avif/320x1200 320w, /url/avif/640x1200 640w"'
                . ' imagesizes="100vw" type="image/avif" fetchpriority="high" media="(min-resolution:1.5dppx)">',
            ],
            $this->renderer()->preloadLinks($request, $this->fakeProcessor()),
        );
    }
}
