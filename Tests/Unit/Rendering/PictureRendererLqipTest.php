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

final class PictureRendererLqipTest extends TestCase
{
    private function render(?string $lqip): string
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
            lqip: $lqip,
        );

        return (new PictureRenderer(new LadderFactory([320, 640], 2000)))->render($request, $this->fakeProcessor());
    }

    private function fakeProcessor(): ImageProcessorInterface
    {
        return new class () implements ImageProcessorInterface {
            public function buildUrl(ImageVariant $variant): string
            {
                return sprintf('/u/%dx%d.%s', $variant->width, $variant->height, $variant->format);
            }

            public function isOffloaded(): bool
            {
                return false;
            }

            public function materialize(ImageVariant $variant): ProcessedImage
            {
                throw new \LogicException('not used', 1717600500);
            }
        };
    }

    public function testHexLqipBecomesBackgroundColor(): void
    {
        self::assertStringContainsString('style="background:#8a7f6e"', $this->render('#8a7f6e'));
    }

    public function testDataUriLqipBecomesCoverBackgroundImage(): void
    {
        $html = $this->render('data:image/png;base64,AAAA');
        self::assertStringContainsString(
            'style="background-image:url(data:image/png;base64,AAAA);background-size:cover"',
            $html
        );
    }

    public function testNullLqipAddsNoStyle(): void
    {
        self::assertStringNotContainsString('style=', $this->render(null));
    }
}
