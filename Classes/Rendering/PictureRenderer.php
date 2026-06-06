<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Rendering;

use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Dto\ImageRenderRequest;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Ladder\Rung;

/**
 * Builds the responsive markup. Processor-agnostic: the same HTML is emitted regardless of who
 * produces the pixels — it only asks the processor for each candidate URL. A single breakpoint
 * yields an `<img>` with a width ladder + sizes="auto"; width/height come from the largest rung
 * so there is zero CLS. priority images drop lazy-loading for fetchpriority + an explicit sizes.
 */
final readonly class PictureRenderer
{
    public function __construct(private LadderFactory $ladderFactory) {}

    public function render(ImageRenderRequest $request, ImageProcessorInterface $processor): string
    {
        if (count($request->breakpoints) > 1) {
            return $this->renderPicture($request, $processor);
        }

        return $this->renderImg($request, $this->defaultBreakpoint($request), $processor);
    }

    private function renderPicture(ImageRenderRequest $request, ImageProcessorInterface $processor): string
    {
        $sources = '';
        foreach ($request->breakpoints as $breakpoint) {
            if ($breakpoint->media !== null) {
                $sources .= $this->sourceTag($request, $breakpoint, $processor);
            }
        }
        $img = $this->renderImg($request, $this->defaultBreakpoint($request), $processor);

        return '<picture>' . $sources . $img . '</picture>';
    }

    private function sourceTag(ImageRenderRequest $request, BreakpointRatio $breakpoint, ImageProcessorInterface $processor): string
    {
        $rungs = $this->ladderFactory->build($breakpoint->ratio, $request->sourceWidth);

        return '<source' . $this->attrs([
            'media' => htmlspecialchars((string)$breakpoint->media, ENT_QUOTES),
            'srcset' => $this->srcset($request, $rungs, $processor),
            'sizes' => $request->priority ? '100vw' : 'auto',
        ]) . '>';
    }

    private function renderImg(ImageRenderRequest $request, BreakpointRatio $breakpoint, ImageProcessorInterface $processor): string
    {
        $rungs = $this->ladderFactory->build($breakpoint->ratio, $request->sourceWidth);
        $largest = $rungs[array_key_last($rungs)];

        $attrs = [
            'src' => $processor->buildUrl($this->variant($request, $largest)),
            'srcset' => $this->srcset($request, $rungs, $processor),
            'sizes' => $request->priority ? '100vw' : 'auto',
            'width' => (string)$largest->width,
            'height' => (string)$largest->height,
            'alt' => htmlspecialchars($request->alt, ENT_QUOTES),
        ];
        if ($request->class !== null && $request->class !== '') {
            $attrs['class'] = htmlspecialchars($request->class, ENT_QUOTES);
        }
        if ($request->priority) {
            $attrs['fetchpriority'] = 'high';
        } else {
            $attrs['loading'] = 'lazy';
        }
        $attrs['decoding'] = 'async';

        return '<img' . $this->attrs($attrs) . '>';
    }

    /** @param Rung[] $rungs */
    private function srcset(ImageRenderRequest $request, array $rungs, ImageProcessorInterface $processor): string
    {
        $candidates = [];
        foreach ($rungs as $rung) {
            $candidates[] = $processor->buildUrl($this->variant($request, $rung)) . ' ' . $rung->width . 'w';
        }

        return implode(', ', $candidates);
    }

    private function variant(ImageRenderRequest $request, Rung $rung): ImageVariant
    {
        return new ImageVariant(
            $request->storageUid,
            $request->fileUid,
            $request->cropVariant,
            $rung->width,
            $rung->height,
            $request->format,
            $request->quality,
        );
    }

    private function defaultBreakpoint(ImageRenderRequest $request): BreakpointRatio
    {
        foreach ($request->breakpoints as $breakpoint) {
            if ($breakpoint->media === null) {
                return $breakpoint;
            }
        }

        return $request->breakpoints[array_key_last($request->breakpoints)];
    }

    /** @param array<string, string> $attrs */
    private function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $name => $value) {
            $out .= ' ' . $name . '="' . $value . '"';
        }

        return $out;
    }
}
