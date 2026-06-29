..  include:: /Includes.rst.txt

..  _extend-custom-processor:

================
Custom Processor
================

A processor turns an :php:`ImageVariant` into the URL that goes into ``srcset``
(and, for local processors, materializes the binary). All built-ins —
``local:async``, ``local:sync`` and ``imgproxy`` — implement the same interface;
your own is added the same way.

The interface
=============

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
    grammar and :php:`materialize()` is never called — the webserver never
    touches pixels.
*   Return ``false`` for a local processor; :php:`materialize()` produces the
    derivative through TYPO3's ``ImageService``.

Register it
===========

Tag a service with ``imaginator.image_processor`` and give it a ``key``. The
registry is a lazy service locator indexed by that key:

..  code-block:: yaml
    :caption: Configuration/Services.yaml (in your extension)

    Vendor\MyExt\Imaging\MyCdnProcessor:
      tags:
        - { name: 'imaginator.image_processor', key: 'mycdn' }

Select it with the :confval:`processor <conf-processor>` setting:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator']['processor'] = 'mycdn';

..  note::
    Keep the field set and order of :php:`CanonicalParams` untouched if you reuse
    the signed-endpoint path — the HMAC signature depends on it byte-for-byte.
    An external processor that points ``srcset`` straight at its provider does not
    sign at all.
