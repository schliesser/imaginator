<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Offloaded processor: `srcset` URLs point straight at the external provider, which fetches the
 * origin image and does the pixels. The webserver never touches bytes, so the signed `/_imaginator/`
 * endpoint and {@see materialize()} are never reached.
 *
 * The provider's crop is `g:sm` (smart gravity); the editor's per-reference crop variant is not
 * replayed externally, so a reference resolves to its original file's public URL.
 */
final readonly class ExternalImageProcessor implements ImageProcessorInterface
{
    public function __construct(
        private UrlBuilderInterface $builder,
        private ResourceFactory $resourceFactory,
        /** Optional origin prefix when the provider has no own base URL; empty = pass the path as-is. */
        private string $sourceBaseUrl = '',
    ) {}

    public function buildUrl(ImageVariant $variant): string
    {
        return $this->builder->build($variant, $this->sourceUrl($variant));
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
