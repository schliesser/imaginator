<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\ViewHelpers;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Dto\ImageRenderRequest;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Lqip\LqipGeneratorFactory;
use Schliesser\Imaginator\Rendering\PictureRenderer;
use Schliesser\Imaginator\Service\DprTierExpander;
use Schliesser\Imaginator\Service\RatioMapResolver;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Thin Fluid entry point: resolve the FAL file, turn the aspectRatio argument into a per-breakpoint
 * ratio map and delegate to {@see PictureRenderer}. No measuring, no JS dependency for correctness.
 */
final class ImageViewHelper extends AbstractViewHelper
{
    /** Shared identifier so every LQIP rule lands in one merged `<style>`. */
    private const LQIP_STYLE_IDENTIFIER = 'imaginator-lqip';

    protected $escapeOutput = false;

    public function __construct(
        private readonly ImageService $imageService,
        private readonly PictureRenderer $renderer,
        private readonly ImageProcessorInterface $processor,
        private readonly SettingsFactory $settingsFactory,
        private readonly LqipGeneratorFactory $lqipFactory,
        private readonly AssetCollector $assetCollector,
        private readonly PageRenderer $pageRenderer,
        private readonly CropResolver $cropResolver,
        private readonly RatioMapResolver $ratioMapResolver,
        private readonly DprTierExpander $dprTierExpander,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'File path or uid', false, '');
        $this->registerArgument('image', 'object', 'A FAL File or FileReference object', false, null);
        $this->registerArgument('treatIdAsReference', 'bool', 'Treat src as sys_file_reference uid', false, false);
        $this->registerArgument('aspectRatio', 'mixed', 'Ratio "16:9", a fixed CSS height "600px" (full-bleed hero: width climbs the ladder, height pinned), a {breakpoint: ratio|height} map, or the raw aspect_ratio JSON; omit to use the crop variant ratio (reference) or the original image ratio', false, null);
        $this->registerArgument('cropVariant', 'string', 'FAL crop variant', false, 'default');
        $this->registerArgument('alt', 'string', 'Alternative text', false, '');
        $this->registerArgument('title', 'string', 'Title attribute', false, null);
        $this->registerArgument('class', 'string', 'CSS class', false, null);
        $this->registerArgument('priority', 'bool', 'LCP image: eager + fetchpriority=high', false, false);
    }

    public function render(): string
    {
        $file = $this->imageService->getImage(
            (string) ($this->arguments['src'] ?? ''),
            $this->arguments['image'] ?? null,
            (bool) ($this->arguments['treatIdAsReference'] ?? false),
        );
        // getImage() is typed as the bare FileInterface, which declares neither getUid() nor
        // getOriginalFile(); in practice it returns a File or FileReference. Narrow so those calls
        // are type-safe (and fail loudly on the impossible case).
        if (!$file instanceof File && !$file instanceof FileReference) {
            throw new \RuntimeException('imaginator: unsupported image resource ' . $file::class, 1718000001);
        }
        $isReference = $file instanceof FileReference;
        $original = $isReference ? $file->getOriginalFile() : $file;
        $cropVariant = (string) $this->arguments['cropVariant'];
        $settings = $this->settingsFactory->create();

        // Vector/animated formats (svg, ai, eps, gif by default) carry no meaningful width ladder and must
        // not be transcoded: serve the original file verbatim as a plain <img>.
        if (in_array(strtolower($original->getExtension()), $settings->excludeExtensions, true)) {
            return $this->renderer->renderPassthrough(
                (string) $original->getPublicUrl(),
                (string) $this->arguments['alt'],
                $this->arguments['class'] !== null ? (string) $this->arguments['class'] : null,
                (bool) $this->arguments['priority'],
            );
        }

        // Bound the ladder by the croppable region (the crop area for references), so the
        // LadderFactory width+height clamp yields the fitted-rect width per breakpoint ratio.
        $resolution = $this->cropResolver->resolve($isReference, $file->getUid(), $cropVariant);
        $sourceWidth = $resolution->sourceWidth;
        $sourceHeight = $resolution->sourceHeight;

        // Per-DPR expansion: each fixed-height tier becomes 1x..cap min-resolution sources so a hero
        // serves its pinned height at the device's real pixel ratio. Ratio tiers pass through.
        $breakpoints = $this->dprTierExpander->expand(
            $this->breakpoints($this->arguments['aspectRatio'], $settings->breakpoints, $sourceWidth, $sourceHeight),
            $settings->fixedHeightDprCap,
            (bool) $this->arguments['priority'],
        );

        $request = new ImageRenderRequest(
            isReference: $isReference,
            uid: $file->getUid(),
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            cropVariant: $cropVariant,
            breakpoints: $breakpoints,
            format: $settings->format,
            quality: $settings->qualities[$settings->format] ?? 80,
            alt: (string) $this->arguments['alt'],
            class: $this->arguments['class'] !== null ? (string) $this->arguments['class'] : null,
            priority: (bool) $this->arguments['priority'],
            lqipClass: $this->registerLqip($this->lqipFactory->get($settings->lqip)->generate($original)),
        );

        // Priority/LCP images get a <head> preload so the request is discoverable immediately.
        foreach ($this->renderer->preloadLinks($request, $this->processor) as $link) {
            $this->pageRenderer->addHeaderData($link);
        }

        return $this->renderer->render($request, $this->processor);
    }

    /**
     * Register the LQIP rule in a single, deduplicated, nonce-able inline stylesheet (rendered in
     * one `<style>` by the PageRenderer/AssetRenderer) and return the class that selects it.
     * Returns null when there is no placeholder.
     */
    private function registerLqip(?string $lqip): ?string
    {
        if ($lqip === null || $lqip === '') {
            return null;
        }
        $class = 'imaginator-lqip-' . substr(sha1($lqip), 0, 12);
        $declaration = str_starts_with($lqip, 'data:')
            ? 'background-image:url(' . $lqip . ');background-size:cover'
            : 'background:' . $lqip;
        $rule = '.' . $class . '{' . $declaration . '}';

        // All placeholders accumulate under ONE identifier, so the AssetRenderer emits a single
        // `<style>` for the whole page instead of one per image. The class is a content hash, so
        // checking for its selector dedups identical placeholders shared across images.
        $existing = $this->assetCollector->getInlineStyleSheets()[self::LQIP_STYLE_IDENTIFIER]['source'] ?? '';
        if (!str_contains($existing, '.' . $class . '{')) {
            $options = (new Typo3Version())->getMajorVersion() >= 14
                ? ['csp' => true]
                : ['useNonce' => true];
            $this->assetCollector->addInlineStyleSheet(
                self::LQIP_STYLE_IDENTIFIER,
                $existing . $rule,
                [],
                $options,
            );
        }

        return $class;
    }

    /**
     * Turn the `aspectRatio` argument into per-breakpoint ratios: a single ratio ("16:9") or a
     * {breakpoint: ratio} map; otherwise the croppable region's native ratio.
     *
     * @param \Schliesser\Imaginator\Dto\Breakpoint[] $configuredBreakpoints design-system breakpoints (alias → min-width)
     * @return BreakpointRatio[]
     */
    private function breakpoints(mixed $aspectRatio, array $configuredBreakpoints, int $sourceWidth, int $sourceHeight): array
    {
        // A {breakpoint: ratio} map (pictureino-compat) arrives either as a Fluid array (inline
        // {md: '4:3'}) or as the raw `aspect_ratio` DB column's JSON string bound straight into the
        // tag — no DataProcessor step in between. Keys are configured breakpoint aliases (md, lg, …)
        // or literal min-widths in px (768, key 0 = base); the resolver turns them into largest-first
        // <source media="(min-width:Npx)"> tiers, never leaking a raw alias as a media string. An
        // empty result (all keys unknown / ratios `auto`) falls through to the native ratio below so
        // the renderer never receives an empty breakpoint set.
        if (is_string($aspectRatio) && str_starts_with(ltrim($aspectRatio), '{')) {
            $resolved = $this->ratioMapResolver->fromJson($aspectRatio, $configuredBreakpoints);
            if ($resolved !== []) {
                return $resolved;
            }
        } elseif (is_array($aspectRatio)) {
            $resolved = $this->ratioMapResolver->fromMap($aspectRatio, $configuredBreakpoints);
            if ($resolved !== []) {
                return $resolved;
            }
        } elseif ($aspectRatio !== null && $aspectRatio !== '') {
            // A single scalar: either a "16:9" ratio or a fixed CSS height ("600px"/"600") for a
            // full-bleed hero whose width climbs the ladder while height stays pinned. `auto`/empty
            // returns null and falls through to the native ratio below.
            $spec = $this->ratioMapResolver->parseSpec((string) $aspectRatio);
            if ($spec instanceof AspectRatio) {
                return [new BreakpointRatio($spec)];
            }
            if (is_int($spec)) {
                return [new BreakpointRatio(fixedHeight: $spec)];
            }
        }

        // Nothing usable: use the croppable region's own ratio (crop area for a reference, else
        // the original image), so nothing is cropped beyond what the editor already chose.
        return [new BreakpointRatio(new AspectRatio(max(1, $sourceWidth), max(1, $sourceHeight)))];
    }
}
