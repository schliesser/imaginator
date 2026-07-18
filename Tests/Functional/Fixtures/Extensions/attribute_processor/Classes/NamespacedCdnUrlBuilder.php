<?php

declare(strict_types=1);

namespace Schliesser\ImaginatorFixture\AttributeProcessor;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;

/**
 * URL-builder shape with `extensionKey`: provider options come from THIS extension's own
 * Extension Configuration (`EXTENSIONS['attribute_processor']`), not imaginator's
 * `processorOptions` subtree.
 */
#[AsImaginatorProcessor('namespaced-cdn', extensionKey: 'attribute_processor')]
final readonly class NamespacedCdnUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private ExternalConfig $config) {}

    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return sprintf(
            '%s/ns/%s/%dx%d/%s',
            rtrim($this->config->baseUrl, '/'),
            $this->config->requireOption('accountHash'),
            $variant->width,
            $variant->height,
            $sourceUrl,
        );
    }
}
