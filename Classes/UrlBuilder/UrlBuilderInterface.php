<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\UrlBuilder;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;

/**
 * Maps an {@see ImageVariant} (+ the origin image's source string) onto a provider's URL grammar.
 * Implementations are pure (no I/O) so they are covered by exact-string golden-file tests.
 *
 * Registration convention: put `#[AsImaginatorProcessor('key')]` on the implementation and take
 * {@see \Schliesser\Imaginator\Dto\ExternalConfig} as the sole constructor argument — the
 * {@see \Schliesser\Imaginator\Imaging\External\ExternalProcessorFactory} constructs the builder
 * from the unified provider settings and wraps it in an offloaded
 * {@see \Schliesser\Imaginator\Imaging\External\ExternalImageProcessor}. Ignore the config fields
 * your provider has no equivalent for (e.g. imagor ignores `salt`); provider-specific extras
 * arrive in `ExternalConfig::$options` — from imaginator's `processorOptions` setting, or from your
 * own extension's configuration when the attribute declares `extensionKey` (keeps a provider
 * extension's options in its own namespace, with its own `ext_conf_template.txt` backend UI).
 */
interface UrlBuilderInterface
{
    /**
     * @param string    $sourceUrl the origin image the provider should fetch. Either an absolute URL or
     *                             a root-relative path, depending on how the provider resolves sources
     *                             (e.g. imgproxy with IMGPROXY_BASE_URL takes a relative path).
     * @param Rectangle $crop      the editor's crop in absolute source pixels, already ratio-fitted and
     *                             focus-positioned ({@see \Schliesser\Imaginator\Imaging\CropCalculator}).
     *                             Null = no editor crop; the provider falls back to its own smart/center
     *                             gravity.
     */
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string;
}
