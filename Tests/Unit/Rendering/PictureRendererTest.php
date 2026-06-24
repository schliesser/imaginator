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

final class PictureRendererTest extends TestCase
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
                return sprintf('/img/%d/%dx%d.%s', $variant->uid, $variant->width, $variant->height, $variant->format);
            }

            public function isOffloaded(): bool
            {
                return false;
            }

            public function materialize(ImageVariant $variant): ProcessedImage
            {
                throw new \LogicException('not used in render tests', 1717600300);
            }
        };
    }

    public function testSingleRatioRendersImgWithLadder(): void
    {
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [new BreakpointRatio(new AspectRatio(16, 9))],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
        );

        $expected = '<img src="/img/9/640x360.webp"'
            . ' srcset="/img/9/320x180.webp 320w, /img/9/640x360.webp 640w"'
            . ' sizes="auto" width="640" height="360" alt="A hero" loading="lazy" decoding="async">';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }

    public function testMultipleRatiosRenderPictureWithSourcePerBreakpoint(): void
    {
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [
                new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
                new BreakpointRatio(new AspectRatio(4, 3)),
            ],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
        );

        $expected = '<picture>'
            . '<source media="(min-width:992px)"'
            . ' srcset="/img/9/320x180.webp 320w, /img/9/640x360.webp 640w" sizes="auto">'
            . '<img src="/img/9/640x480.webp"'
            . ' srcset="/img/9/320x240.webp 320w, /img/9/640x480.webp 640w"'
            . ' sizes="auto" width="640" height="480" alt="A hero" loading="lazy" decoding="async">'
            . '</picture>';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }

    public function testPriorityDropsLazyAndAddsFetchpriorityAndExplicitSizes(): void
    {
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [new BreakpointRatio(new AspectRatio(16, 9))],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
            class: 'lead',
            priority: true,
        );

        $expected = '<img src="/img/9/640x360.webp"'
            . ' srcset="/img/9/320x180.webp 320w, /img/9/640x360.webp 640w"'
            . ' sizes="100vw" width="640" height="360" alt="A hero" class="lead"'
            . ' fetchpriority="high" loading="eager" decoding="async">';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }

    public function testFixedHeightTierRendersImgWithFlatPinnedHeightLadder(): void
    {
        // A full-bleed hero tier: width climbs the ladder, height is flat-pinned on every rung. The
        // DPR multiple is applied by the expander upstream, not by the ladder.
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [new BreakpointRatio(media: null, fixedHeight: 600)],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
        );

        $expected = '<img src="/img/9/640x600.webp"'
            . ' srcset="/img/9/320x600.webp 320w, /img/9/640x600.webp 640w"'
            . ' sizes="auto" width="640" height="600" alt="A hero" loading="lazy" decoding="async">';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }

    public function testMixedRatioAndFixedHeightTiersRenderPicture(): void
    {
        // {xs: "16:9", lg: "600px"}: base <img> keeps the 16:9 ladder, the lg <source> pins a flat height.
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
                new BreakpointRatio(new AspectRatio(16, 9)),
            ],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
        );

        $expected = '<picture>'
            . '<source media="(min-width:992px)"'
            . ' srcset="/img/9/320x600.webp 320w, /img/9/640x600.webp 640w" sizes="auto">'
            . '<img src="/img/9/640x360.webp"'
            . ' srcset="/img/9/320x180.webp 320w, /img/9/640x360.webp 640w"'
            . ' sizes="auto" width="640" height="360" alt="A hero" loading="lazy" decoding="async">'
            . '</picture>';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }

    public function testEmptyBreakpointsFallsBackToNativeSourceRatioWithoutWarning(): void
    {
        // Defensive: a caller must never hand the renderer an empty breakpoint set, but if it does
        // (e.g. an aspectRatio map that resolved to nothing) we synthesise the native source ratio
        // instead of indexing array_key_last([]) and emitting an "Undefined array key" warning.
        $request = new ImageRenderRequest(
            isReference: false,
            uid: 9,
            sourceWidth: 4000,
            sourceHeight: 4000,
            cropVariant: 'default',
            breakpoints: [],
            format: 'webp',
            quality: 72,
            alt: 'A hero',
            formats: ['webp'],
        );

        $expected = '<picture><img src="/img/9/640x640.webp"'
            . ' srcset="/img/9/320x320.webp 320w, /img/9/640x640.webp 640w"'
            . ' sizes="auto" width="640" height="640" alt="A hero" loading="lazy" decoding="async"></picture>';

        self::assertSame($expected, $this->renderer()->render($request, $this->fakeProcessor()));
    }
}
