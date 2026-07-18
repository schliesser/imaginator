..  include:: /Includes.rst.txt

..  _extend-custom-processor:

================
Custom Processor
================

A processor turns an :php:`ImageVariant` into the URL that goes into ``srcset``
(and, for local processors, processes the file). All built-ins (``local:async``,
``local:sync``, ``imgproxy`` and ``imagor``) implement the same interface; your
own is added the same way: put :php:`#[AsImaginatorProcessor('my-key')]` on your
class, pick the interface that fits, set ``processor = my-key`` — no YAML.

New CDN provider: a URL builder is enough
=========================================

For an external provider (CDN / image service) you rarely need a full processor —
the non-grammar work (source-URL resolution, editor-crop replay, offloaded
semantics) is already written once in :php:`ExternalImageProcessor`. Implement
only the provider's URL grammar:

..  code-block:: php
    :caption: Classes/UrlBuilder/MyCdnUrlBuilder.php (in your extension)

    use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
    use Schliesser\Imaginator\Dto\ExternalConfig;
    use Schliesser\Imaginator\Dto\ImageVariant;
    use Schliesser\Imaginator\Dto\Rectangle;
    use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;

    #[AsImaginatorProcessor('my-cdn')]
    final readonly class MyCdnUrlBuilder implements UrlBuilderInterface
    {
        public function __construct(private ExternalConfig $config) {}

        public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
        {
            return sprintf(
                '%s/%dx%d/%s',
                rtrim($this->config->baseUrl, '/'),
                $variant->width,
                $variant->height,
                $sourceUrl,
            );
        }
    }

The compiler pass wraps the builder in a configured
:php:`ExternalImageProcessor` automatically. Convention: the builder takes
:php:`ExternalConfig` as its **sole constructor argument** — it carries the
unified :confval:`processorBaseUrl <conf-processorbaseurl>` /
:confval:`processorSignKey <conf-processorsignkey>` /
:confval:`processorSalt <conf-processorsalt>` settings; ignore the fields your
provider has no equivalent for (imagor ignores ``salt``). Keep the builder pure
(no I/O), so it stays golden-file testable.

Provider-specific extras (e.g. a Cloudflare account hash) arrive in
:php:`ExternalConfig::$options`; read them in the builder via
:php:`$this->config->option('accountHash')` or
:php:`$this->config->requireOption('accountHash')` — the latter throws a
descriptive exception naming the exact configuration path, so a
misconfiguration fails loudly instead of emitting broken URLs.

Where the options live depends on who ships the builder. Extension
Configuration is namespaced per extension, so a builder shipped in **your own
extension** declares its extension key on the attribute and keeps the options
in **its own** namespace — its own :file:`ext_conf_template.txt`, its own
backend form:

..  code-block:: php

    #[AsImaginatorProcessor('my-cdn', extensionKey: 'my_cdn')]
    final readonly class MyCdnUrlBuilder implements UrlBuilderInterface { /* … */ }

..  code-block:: php
    :caption: config/system/settings.php (or your ext_conf_template.txt backend form)

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['my_cdn'] = [
        'accountHash' => 'abc123',
    ];

Without ``extensionKey`` the options come from imaginator's own
``processorOptions`` map (used by the built-in providers; it has no backend
UI):

..  code-block:: php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator']['processorOptions'] = [
        'accountHash' => 'abc123',
    ];

The full interface
==================

When URL grammar is not enough (local processing, exotic materialization),
implement the processor interface itself — same attribute:

..  code-block:: php
    :caption: Classes/Imaging/ImageProcessorInterface.php

    interface ImageProcessorInterface
    {
        /** Render-time: URL that belongs in srcset for this variant. */
        public function buildUrl(ImageVariant $variant): string;

        /** True when pixels happen elsewhere (external CDN/service); local processing is skipped. */
        public function isOffloaded(): bool;

        /** Local processors only: produce/return the processed binary. */
        public function materialize(ImageVariant $variant): ProcessedImage;
    }

*   Return ``true`` from :php:`isOffloaded()` for an external service (CDN /
    provider). :php:`buildUrl()` then maps the variant onto the provider's URL
    grammar and :php:`materialize()` is never called (the webserver never
    touches pixels).
*   Return ``false`` for a local processor; :php:`materialize()` produces the
    derivative through TYPO3's ``ImageService``.

Register it
===========

One attribute covers both shapes — the implemented interface decides:

..  code-block:: php

    #[AsImaginatorProcessor('my-cdn')]
    final class MyProcessor implements ImageProcessorInterface { /* … */ }

*   :php:`ImageProcessorInterface` → the service itself is registered under the
    key.
*   :php:`UrlBuilderInterface` → a configured :php:`ExternalImageProcessor`
    wrapping the builder is registered under the key.
*   Neither or both → the container compile fails with a descriptive exception;
    duplicate and empty keys fail too (nothing silently last-wins).

Manual tagging remains a supported alternative — e.g. for multi-key aliasing or
when your service needs its own factory. Tag it with
``imaginator.image_processor`` and a ``key``; the registry is a lazy service
locator indexed by that key:

..  code-block:: yaml
    :caption: Configuration/Services.yaml (in your extension)

    Vendor\MyExt\Imaging\MyCdnProcessor:
      tags:
        - { name: 'imaginator.image_processor', key: 'my-cdn' }

Escalation ladder: attribute on a URL builder (zero configuration code) →
builder + options (``extensionKey`` for your own namespace, or imaginator's
``processorOptions``) → manual yaml named service with your own factory
→ full :php:`ImageProcessorInterface` class (same attribute).

Select it with the :confval:`processor <conf-processor>` setting:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator']['processor'] = 'my-cdn';

..  note::
    TYPO3 caches the compiled container without tracking class files — after
    adding or changing the attribute, flush caches (same as after editing a
    yaml tag).

..  note::
    Keep the field set and order of :php:`CanonicalParams` untouched if you reuse
    the signed-endpoint path — the HMAC signature depends on it byte-for-byte.
    An external processor that points ``srcset`` straight at its provider does not
    sign at all.
