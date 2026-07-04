<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External;

use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Offloaded processor: `srcset` URLs point straight at the external provider, which fetches the
 * origin image and does the pixels. The webserver never touches bytes, so the signed `/_imaginator/`
 * endpoint and {@see materialize()} are never reached.
 *
 * For reference variants the editor's crop variant is replayed in the provider URL: the same
 * ratio-fitted, focus-positioned rect local processing feeds to ImageService ({@see CropCalculator})
 * goes into the provider's crop op, so external output matches local output. A reference resolves to
 * its original file's public URL (the provider crops, not TYPO3). Plain file variants carry no crop
 * and fall back to the provider's smart gravity.
 */
final readonly class ExternalImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private UrlBuilderInterface $builder,
        private ResourceFactory $resourceFactory,
        private CropResolver $cropResolver,
        private CropCalculator $cropCalculator,
        /** Optional origin prefix when the provider has no own base URL; empty = pass the path as-is. */
        private string $sourceBaseUrl = '',
    ) {}

    public function buildUrl(ImageVariant $variant): string
    {
        return $this->builder->build($variant, $this->sourceUrl($variant), $this->cropRect($variant));
    }

    /**
     * The editor's crop for a reference variant, ratio-fitted and focus-positioned — identical
     * geometry to LocalImageProcessor's processing plan. Null for plain files and for references
     * without a stored crop/focus area (smart fallback: provider detection beats a synthetic
     * centre crop when the editor expressed no intent).
     */
    private function cropRect(ImageVariant $variant): ?Rectangle
    {
        if (!$variant->isReference) {
            return null;
        }
        $resolution = $this->cropResolver->resolve($variant->isReference, $variant->uid, $variant->cropVariant);
        if (!$resolution->hasEditorCrop) {
            return null;
        }

        return $this->cropCalculator->fit(
            $resolution->cropArea,
            $resolution->focusArea,
            new AspectRatio($variant->width, $variant->height),
        );
    }

    public function isOffloaded(): bool
    {
        return true;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        throw new \LogicException('imaginator: an offloaded processor does not materialize pixels.', 1718200001);
    }

    private function sourceUrl(ImageVariant $variant): string
    {
        $file = $variant->isReference
            ? $this->resourceFactory->getFileReferenceObject($variant->uid)->getOriginalFile()
            : $this->resourceFactory->getFileObject($variant->uid);

        $source = ltrim((string) $file->getPublicUrl(), '/');
        if ($this->sourceBaseUrl !== '') {
            return rtrim($this->sourceBaseUrl, '/') . '/' . $source;
        }

        return $source;
    }
}
