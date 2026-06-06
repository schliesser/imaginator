<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\ViewHelpers;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Dto\ImageRenderRequest;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\Rendering\PictureRenderer;
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
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'File path or uid', false, '');
        $this->registerArgument('image', 'object', 'A FAL File or FileReference object', false, null);
        $this->registerArgument('treatIdAsReference', 'bool', 'Treat src as sys_file_reference uid', false, false);
        $this->registerArgument('aspectRatio', 'mixed', 'Ratio "16:9" or a {media: ratio} map for art direction', false, '16:9');
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
        $original = $file instanceof FileReference ? $file->getOriginalFile() : $file;
        $settings = $this->settingsFactory->create();
        // v1 foundation emits a single, broadly-supported tier. Stacked AVIF/WebP <source> tiers
        // (driven by $settings->formats) are added in the formats-lqip follow-on plan.
        $format = 'webp';

        $request = new ImageRenderRequest(
            storageUid: $original->getStorage()->getUid(),
            fileUid: $original->getUid(),
            sourceWidth: (int)$file->getProperty('width'),
            cropVariant: (string)$this->arguments['cropVariant'],
            breakpoints: $this->breakpoints($this->arguments['aspectRatio']),
            format: $format,
            quality: $settings->qualities[$format] ?? 72,
            alt: (string)$this->arguments['alt'],
            class: $this->arguments['class'] !== null ? (string)$this->arguments['class'] : null,
            priority: (bool)$this->arguments['priority'],
        );

        return $this->renderer->render($request, $this->processor);
    }

    /**
     * @return BreakpointRatio[]
     */
    private function breakpoints(mixed $aspectRatio): array
    {
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
