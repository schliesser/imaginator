<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Rendering;

use Schliesser\Imaginator\Dto\AspectRatio;
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
 * so there is zero CLS. Priority images drop lazy-loading for fetchpriority + an explicit sizes.
 */
final readonly class PictureRenderer
{
    public function __construct(private LadderFactory $ladderFactory) {}

    /**
     * `<link rel="preload">` tags for the `<head>` so a priority/LCP image is discoverable before
     * the body is parsed. Returns an empty list for non-priority images.
     *
     * @return string[]
     */
    public function preloadLinks(ImageRenderRequest $request, ImageProcessorInterface $processor): array
    {
        if (!$request->priority) {
            return [];
        }
        $format = $request->format;
        // When the tier set is resolution-gated, the media-null base is the <img> (preload-scanned
        // anyway), so a media-null preload for it would just duplicate that fetch.
        $hasGated = array_filter($request->breakpoints, static fn(BreakpointRatio $b): bool => $b->resolutionGated) !== [];
        $links = [];
        foreach ($request->breakpoints as $breakpoint) {
            if ($hasGated && $breakpoint->media === null) {
                continue;
            }
            $rungs = $this->rungs($breakpoint, $request);
            $attrs = [
                'rel' => 'preload',
                'as' => 'image',
                'imagesrcset' => $this->srcset($request, $rungs, $processor),
                'imagesizes' => '100vw',
                'type' => 'image/' . $format,
                'fetchpriority' => 'high',
            ];
            if ($breakpoint->media !== null) {
                $attrs['media'] = htmlspecialchars($breakpoint->media, ENT_QUOTES);
            }
            $links[] = '<link' . $this->attrs($attrs) . '>';
        }

        return $links;
    }

    /**
     * Render an excluded file (vector/animated formats) as a plain `<img>` pointing straight at the
     * original. Keeps the same priority semantics as the processed path (fetchpriority/high vs loading/lazy).
     */
    public function renderPassthrough(string $src, string $alt, ?string $class, bool $priority): string
    {
        $attrs = [
            'src' => htmlspecialchars($src, ENT_QUOTES),
            'alt' => htmlspecialchars($alt, ENT_QUOTES),
        ];
        if ($class !== null && $class !== '') {
            $attrs['class'] = htmlspecialchars($class, ENT_QUOTES);
        }
        if ($priority) {
            $attrs['fetchpriority'] = 'high';
            $attrs['loading'] = 'eager';
        } else {
            $attrs['loading'] = 'lazy';
        }
        $attrs['decoding'] = 'async';

        return '<img' . $this->attrs($attrs) . '>';
    }

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
        $rungs = $this->rungs($breakpoint, $request);
        $largest = $rungs[array_key_last($rungs)];

        // width/height per <source> so the browser sizes the box to the *selected* breakpoint's
        // ratio, not the <img>'s — without them an art-directed <picture> renders every source in
        // the <img>'s aspect ratio (the box never changes shape across breakpoints).
        return '<source' . $this->attrs([
            'media' => htmlspecialchars((string) $breakpoint->media, ENT_QUOTES),
            'srcset' => $this->srcset($request, $rungs, $processor),
            'sizes' => $request->priority ? '100vw' : 'auto',
            'width' => (string) $largest->width,
            'height' => (string) $largest->height,
        ]) . '>';
    }

    private function renderImg(ImageRenderRequest $request, BreakpointRatio $breakpoint, ImageProcessorInterface $processor): string
    {
        $rungs = $this->rungs($breakpoint, $request);
        $largest = $rungs[array_key_last($rungs)];

        $attrs = [
            'src' => $processor->buildUrl($this->variant($request, $largest)),
            'srcset' => $this->srcset($request, $rungs, $processor),
            'sizes' => $request->priority ? '100vw' : 'auto',
            'width' => (string) $largest->width,
            'height' => (string) $largest->height,
            'alt' => htmlspecialchars($request->alt, ENT_QUOTES),
        ];
        $class = $this->classAttribute($request);
        if ($class !== null) {
            $attrs['class'] = $class;
        }
        if ($request->priority) {
            $attrs['fetchpriority'] = 'high';
            $attrs['loading'] = 'eager';
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

    /**
     * Build one tier's width ladder, passing the tier's fixed height (if any) so a hero pins its
     * height while the ratio tiers derive it. Processor-agnostic: only widths/heights vary here.
     *
     * @return non-empty-list<Rung>
     */
    private function rungs(BreakpointRatio $breakpoint, ImageRenderRequest $request): array
    {
        return $this->ladderFactory->build(
            $breakpoint->ratio,
            $request->sourceWidth,
            $request->sourceHeight,
            $breakpoint->fixedHeight,
            $breakpoint->minRenderWidth,
        );
    }

    private function variant(ImageRenderRequest $request, Rung $rung): ImageVariant
    {
        return new ImageVariant(
            $request->isReference,
            $request->uid,
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

        // No base (media-null) tier. Normally the smallest media tier becomes the <img>; if the set
        // is empty (a ratio map that resolved to nothing) synthesize the native source ratio so the
        // image still renders instead of indexing array_key_last([]).
        if ($request->breakpoints === []) {
            return new BreakpointRatio(new AspectRatio(max(1, $request->sourceWidth), max(1, $request->sourceHeight)));
        }

        return $request->breakpoints[array_key_last($request->breakpoints)];
    }

    /**
     * Merge the author class with the LQIP class. The LQIP *rule* (background color or cover
     * background-image) is registered as a nonce-able `<style>` by the caller, so the `<img>` only
     * ever carries a class — never a CSP-hostile inline `style=""` attribute.
     */
    private function classAttribute(ImageRenderRequest $request): ?string
    {
        $classes = [];
        if ($request->class !== null && $request->class !== '') {
            $classes[] = htmlspecialchars($request->class, ENT_QUOTES);
        }
        if ($request->lqipClass !== null && $request->lqipClass !== '') {
            $classes[] = $request->lqipClass;
        }

        return $classes === [] ? null : implode(' ', $classes);
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
