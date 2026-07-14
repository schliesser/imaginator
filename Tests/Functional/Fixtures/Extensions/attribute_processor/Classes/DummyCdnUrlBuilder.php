<?php

declare(strict_types=1);

namespace Schliesser\ImaginatorFixture\AttributeProcessor;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;

/**
 * URL-builder registration shape: pure grammar + attribute; the compiler pass synthesizes the
 * ExternalImageProcessor around it. Constructor follows the documented convention — ExternalConfig
 * as sole argument.
 */
#[AsImaginatorProcessor('dummy-cdn')]
final readonly class DummyCdnUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private ExternalConfig $config) {}

    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return sprintf(
            '%s/dummy/%dx%d/q%d/%s',
            rtrim($this->config->baseUrl, '/'),
            $variant->width,
            $variant->height,
            $variant->quality,
            $sourceUrl,
        );
    }
}
