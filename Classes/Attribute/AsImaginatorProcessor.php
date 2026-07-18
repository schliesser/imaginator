<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsImaginatorProcessor
{
    /**
     * @param string $key          value of the `processor` setting that selects this processor
     * @param string $extensionKey URL-builder shape only: extension key whose Extension Configuration
     *                             supplies `ExternalConfig::$options`, so a provider extension ships
     *                             its options under its own namespace (own `ext_conf_template.txt`,
     *                             own backend UI) instead of imaginator's. Empty = imaginator's
     *                             `processorOptions` subtree.
     */
    public function __construct(
        public string $key,
        public string $extensionKey = '',
    ) {}
}
