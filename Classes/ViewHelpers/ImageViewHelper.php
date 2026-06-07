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
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Thin Fluid entry point: resolve the FAL file, turn the aspectRatio argument into a per-breakpoint
 * ratio map and delegate to {@see PictureRenderer}. No measuring, no JS dependency for correctness.
 */
final class ImageViewHelper extends AbstractViewHelper
{
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
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'File path or uid', false, '');
        $this->registerArgument('image', 'object', 'A FAL File or FileReference object', false, null);
        $this->registerArgument('treatIdAsReference', 'bool', 'Treat src as sys_file_reference uid', false, false);
        $this->registerArgument('aspectRatio', 'mixed', 'Ratio "16:9" or a {media: ratio} map; omit to use the crop variant ratio (reference) or the original image ratio', false, null);
        $this->registerArgument('cropVariant', 'string', 'FAL crop variant', false, 'default');
        $this->registerArgument('alt', 'string', 'Alternative text', false, '');
        $this->registerArgument('title', 'string', 'Title attribute', false, null);
        $this->registerArgument('class', 'string', 'CSS class', false, null);
        $this->registerArgument('priority', 'bool', 'LCP image: eager + fetchpriority=high', false, false);
    }

    public function render(): string
    {
        $file = $this->imageService->getImage(
            (string)($this->arguments['src'] ?? ''),
            $this->arguments['image'] ?? null,
            (bool)($this->arguments['treatIdAsReference'] ?? false),
        );
        $isReference = $file instanceof FileReference;
        $original = $isReference ? $file->getOriginalFile() : $file;
        $cropVariant = (string)$this->arguments['cropVariant'];
        $settings = $this->settingsFactory->create();
        // Stacked AVIF/WebP <source> tiers from settings; the <img> falls back to the original format.
        $fallbackFormat = $this->fallbackFormat($original->getExtension());

        // Bound the ladder by the croppable region (the crop area for references), so the
        // LadderFactory width+height clamp yields the fitted-rect width per breakpoint ratio.
        $resolution = $this->cropResolver->resolve($isReference, $file->getUid(), $cropVariant);
        $sourceWidth = $resolution->sourceWidth;
        $sourceHeight = $resolution->sourceHeight;

        $request = new ImageRenderRequest(
            isReference: $isReference,
            uid: $file->getUid(),
            sourceWidth: $sourceWidth,
            sourceHeight: $sourceHeight,
            cropVariant: $cropVariant,
            breakpoints: $this->breakpoints($this->arguments['aspectRatio'], $sourceWidth, $sourceHeight),
            format: $fallbackFormat,
            quality: $settings->qualities[$fallbackFormat] ?? 80,
            alt: (string)$this->arguments['alt'],
            class: $this->arguments['class'] !== null ? (string)$this->arguments['class'] : null,
            priority: (bool)$this->arguments['priority'],
            formats: $settings->formats,
            lqipClass: $this->registerLqip($this->lqipFactory->get($settings->lqip)->generate($original)),
        );

        // Priority/LCP images get a <head> preload so the request is discoverable immediately.
        foreach ($this->renderer->preloadLinks($request, $this->processor) as $link) {
            $this->pageRenderer->addHeaderData($link);
        }

        return $this->renderer->render($request, $this->processor);
    }

    /**
     * Register the LQIP rule as a deduplicated, nonce-able inline stylesheet (rendered in a
     * `<style>` by the PageRenderer/AssetRenderer) and return the class that selects it.
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

        // Identifier == class, so identical placeholders are emitted only once.
        $this->assetCollector->addInlineStyleSheet($class, '.' . $class . '{' . $declaration . '}');

        return $class;
    }

    private function fallbackFormat(string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            '', 'jpg' => 'jpeg',
            default => $extension,
        };
    }

    /**
     * @return BreakpointRatio[]
     */
    private function breakpoints(mixed $aspectRatio, int $sourceWidth, int $sourceHeight): array
    {
        // No ratio given: use the croppable region's own ratio (crop area for a reference, else
        // the original image), so nothing is cropped beyond what the editor already chose.
        if ($aspectRatio === null || $aspectRatio === '') {
            return [new BreakpointRatio(new AspectRatio(max(1, $sourceWidth), max(1, $sourceHeight)))];
        }

        if (is_array($aspectRatio)) {
            $breakpoints = [];
            foreach ($aspectRatio as $media => $ratio) {
                $media = (string)$media;
                $breakpoints[] = new BreakpointRatio(
                    AspectRatio::fromString((string)$ratio),
                    $media === '' ? null : $media,
                );
            }

            return $breakpoints;
        }

        return [new BreakpointRatio(AspectRatio::fromString((string)$aspectRatio))];
    }
}
