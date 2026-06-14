<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External\UrlBuilder;

use Schliesser\Imaginator\Dto\ImageVariant;

/**
 * Maps an {@see ImageVariant} (+ the origin image's source string) onto a provider's URL grammar.
 * Implementations are pure (no I/O) so they are covered by exact-string golden-file tests.
 */
interface UrlBuilderInterface
{
    /**
     * @param string $sourceUrl the origin image the provider should fetch. Either an absolute URL or
     *                          a root-relative path, depending on how the provider resolves sources
     *                          (e.g. imgproxy with IMGPROXY_BASE_URL takes a relative path).
     */
    public function build(ImageVariant $variant, string $sourceUrl): string;
}
