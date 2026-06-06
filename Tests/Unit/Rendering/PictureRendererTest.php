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
        return new class () implements ImageProcessorInterface {
            public function buildUrl(ImageVariant $variant): string
            {
                return sprintf('/img/%d/%dx%d.%s', $variant->fileUid, $variant->width, $variant->height, $variant->format);
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
            storageUid: 1,
            fileUid: 9,
            sourceWidth: 4000,
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
}
