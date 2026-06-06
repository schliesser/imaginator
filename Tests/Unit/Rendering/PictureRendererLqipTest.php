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

/**
 * The renderer never emits an inline style="" (CSP-hostile: style-src-attr cannot be nonced).
 * It only places a CSS class; the LQIP rule itself is registered as a nonce-able <style> by the
 * ViewHelper via the AssetCollector.
 */
final class PictureRendererLqipTest extends TestCase
{
    private function render(?string $lqipClass, ?string $class = null): string
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
            class: $class,
            lqipClass: $lqipClass,
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

    public function testLqipClassIsAddedToImg(): void
    {
        self::assertStringContainsString('class="imaginator-lqip-abc123"', $this->render('imaginator-lqip-abc123'));
    }

    public function testLqipClassIsMergedWithUserClass(): void
    {
        self::assertStringContainsString('class="lead imaginator-lqip-abc123"', $this->render('imaginator-lqip-abc123', 'lead'));
    }

    public function testNeverEmitsInlineStyle(): void
    {
        self::assertStringNotContainsString('style=', $this->render('imaginator-lqip-abc123'));
    }

    public function testNoLqipNoClassAttribute(): void
    {
        $html = $this->render(null);
        self::assertStringNotContainsString('class=', $html);
        self::assertStringNotContainsString('style=', $html);
    }
}
