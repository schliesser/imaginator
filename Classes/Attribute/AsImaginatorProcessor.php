<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Attribute;

/**
 * Registers the class as an imaginator processor under `$key` (selectable via the `processor`
 * setting) — no Services.yaml tag needed. The implemented interface decides the registration shape
 * ({@see \Schliesser\Imaginator\DependencyInjection\ProcessorRegistrationPass}):
 *
 * - {@see \Schliesser\Imaginator\Imaging\ImageProcessorInterface} — the service itself is tagged;
 *   full control over URL building and materialization.
 * - {@see \Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface} — a configured
 *   {@see \Schliesser\Imaginator\Imaging\External\ExternalImageProcessor} wrapping the builder is
 *   synthesized; a new CDN provider is one pure URL-grammar class, zero YAML. The builder must take
 *   {@see \Schliesser\Imaginator\Dto\ExternalConfig} as its sole constructor argument.
 *
 * Deliberately not repeatable: one key per class. Multi-key aliasing stays a manual-yaml case
 * (tag the service twice), which the pass never forbids.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsImaginatorProcessor
{
    public function __construct(
        public string $key,
    ) {}
}
