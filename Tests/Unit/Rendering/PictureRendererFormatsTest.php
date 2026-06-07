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

final class PictureRendererFormatsTest extends TestCase
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
                throw new \LogicException('not used in render tests', 1717600400);
            }
        };
    }

    public function testStacksAvifThenWebpThenFallbackImg(): void
    {
        $request = new ImageRenderRequest(
            storageUid: 1,
            fileUid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [new BreakpointRatio(new AspectRatio(16, 9))],
            format: 'jpeg',
            quality: 72,
            alt: 'A hero',
            formats: ['avif', 'webp'],
        );

        $html = $this->renderer()->render($request, $this->fakeProcessor());

        self::assertStringContainsString(
            '<source type="image/avif" srcset="/url/avif/320x180 320w, /url/avif/640x360 640w" sizes="auto">',
            $html
        );
        self::assertStringContainsString(
            '<source type="image/webp" srcset="/url/webp/320x180 320w, /url/webp/640x360 640w" sizes="auto">',
            $html
        );
        self::assertMatchesRegularExpression(
            '#<img [^>]*srcset="/url/jpeg/320x180 320w, /url/jpeg/640x360 640w"#',
            $html
        );
        // Tier order: AVIF source before WebP source.
        self::assertLessThan((int)strpos($html, 'image/webp'), (int)strpos($html, 'image/avif'));
    }

    public function testArtDirectionRepeatsEachBreakpointPerFormat(): void
    {
        $request = new ImageRenderRequest(
            storageUid: 1,
            fileUid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [
                new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
                new BreakpointRatio(new AspectRatio(1, 1), '(max-width:991px)'),
            ],
            format: 'jpeg',
            quality: 72,
            alt: 'A hero',
            formats: ['avif', 'webp'],
        );

        $html = $this->renderer()->render($request, $this->fakeProcessor());

        self::assertStringContainsString(
            '<source type="image/avif" media="(min-width:992px)" srcset="/url/avif/320x180 320w, /url/avif/640x360 640w" sizes="auto">',
            $html
        );
        self::assertStringContainsString(
            '<source type="image/webp" media="(min-width:992px)" srcset="/url/webp/320x180 320w, /url/webp/640x360 640w" sizes="auto">',
            $html
        );
        self::assertStringContainsString(
            '<source type="image/avif" media="(max-width:991px)" srcset="/url/avif/320x320 320w, /url/avif/640x640 640w" sizes="auto">',
            $html
        );
        self::assertStringContainsString(
            '<source type="image/webp" media="(max-width:991px)" srcset="/url/webp/320x320 320w, /url/webp/640x640 640w" sizes="auto">',
            $html
        );
        // Fallback <img> uses the original format and the default (last) breakpoint's ratio.
        self::assertMatchesRegularExpression('#<img [^>]*srcset="/url/jpeg/320x320 320w, /url/jpeg/640x640 640w"#', $html);
        // Ordering: breakpoint-major, format-minor.
        $order = static fn (string $needle): int => (int)strpos($html, $needle);
        self::assertLessThan($order('image/webp" media="(min-width:992px)"'), $order('image/avif" media="(min-width:992px)"'));
        self::assertLessThan($order('media="(max-width:991px)"'), $order('media="(min-width:992px)"'));
    }
}
