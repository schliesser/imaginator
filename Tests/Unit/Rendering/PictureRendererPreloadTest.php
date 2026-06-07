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
        return new class () implements ImageProcessorInterface {
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
            storageUid: 1,
            fileUid: 9,
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
}
