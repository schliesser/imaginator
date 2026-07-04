<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\UrlBuilder;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;

/**
 * Maps an {@see ImageVariant} (+ the origin image's source string) onto a provider's URL grammar.
 * Implementations are pure (no I/O) so they are covered by exact-string golden-file tests.
 */
interface UrlBuilderInterface
{
    /**
     * @param string    $sourceUrl the origin image the provider should fetch. Either an absolute URL or
     *                             a root-relative path, depending on how the provider resolves sources
     *                             (e.g. imgproxy with IMGPROXY_BASE_URL takes a relative path).
     * @param Rectangle $crop      the editor's crop in absolute source pixels, already ratio-fitted and
     *                             focus-positioned ({@see \Schliesser\Imaginator\Imaging\CropCalculator}).
     *                             Null = no editor crop; the provider falls back to its own smart/centre
     *                             gravity.
     */
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string;
}
