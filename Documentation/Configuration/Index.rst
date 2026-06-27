..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Configuration is **instance-wide Extension Configuration**. Edit it under
:guilabel:`Settings > Extension Configuration > imaginator` in the backend, or
set it in :file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = [
        'lqip' => 'dominant-color',
        'ladder' => '320,640,960,1280,1920',
    ];

..  note::
    Settings are global by design, so the render path and the signed-endpoint
    verify path always agree. A per-site model would risk mismatched ladders and
    spurious :ref:`403s <image-endpoint>`.

..  _configuration-settings:

Settings
========

..  confval:: processor
    :name: conf-processor
    :type: string
    :default: ``local:async``

    Image processor. Both local modes drive TYPO3's ``ImageService`` exclusively
    (the binary is whatever your ``GFX`` config selects — GraphicsMagick or
    ImageMagick); no direct GraphicsMagick/ImageMagick/GD calls.

    *   ``local:async`` — ``srcset`` points at the signed ``/_imaginator/`` endpoint;
        a middleware materializes the derivative on first request and 302-redirects
        to the processed file. Processing is deferred to the first hit per variant.
    *   ``local:sync`` — derivatives are materialized synchronously at render time and
        the static ``_processed_/…`` file URL is written straight into ``srcset``, so
        the middleware is never involved and requests are plain static-file serves.
        Trade-off: higher cold-render cost, then no PHP hop per image.
    *   ``imgproxy`` — offloaded; ``srcset`` points straight at the provider (see
        :ref:`configuration-imgproxy`).

    Integrators can register a custom processor by tagging a service with
    ``imaginator.image_processor`` (attribute ``key``) and selecting it here.

..  confval:: maxDimension
    :name: conf-maxdimension
    :type: integer
    :default: ``2000``

    Largest image dimension in pixels. The width ladder is capped to this value.

..  confval:: fixedHeightDprCap
    :name: conf-fixed-height-dpr-cap
    :type: integer
    :default: ``3``

    Highest device pixel ratio a :ref:`fixed-height tier <usage-fixed-height>`
    ships a ``min-resolution`` ``<source>`` for. ``3`` emits 1×/2×/3× tiers; ``2``
    drops the 3× tier; ``1`` disables per-DPR sources (single 1× height).

..  confval:: ladder
    :name: conf-ladder
    :type: string
    :default: ``320,420,560,740,980,1300,1720,2000``

    Width-ladder rungs (comma-separated px). An arbitrary requested width is
    quantized **up** to the nearest rung, which bounds the number of processed
    files and the set of signable URLs.

..  confval:: breakpoints
    :name: conf-breakpoints
    :type: string
    :default: ``xs:0,sm:576,md:768,lg:992,xl:1200``

    Design-system breakpoints (``alias:min-width`` px) used to resolve a
    per-breakpoint aspect-ratio map. ``xs:0`` is the base ratio.

..  confval:: format
    :name: conf-format
    :type: string
    :default: ``avif``

    The single output format — ``avif`` or ``webp`` — applied uniformly to the
    :html:`<img>` and every :html:`<source>`.

..  confval:: quality.avif
    :name: conf-quality-avif
    :type: integer
    :default: ``50``

    AVIF quality. AVIF's scale sits lower than JPEG / WebP for the same
    perceived quality.

..  confval:: quality.webp
    :name: conf-quality-webp
    :type: integer
    :default: ``72``

    WebP quality.

..  confval:: lqip
    :name: conf-lqip
    :type: string
    :default: ``thumbhash``

    Low-quality image placeholder. One of ``thumbhash``, ``dominant-color`` or
    ``none``. See :ref:`configuration-csp`.

..  confval:: excludeExtensions
    :name: conf-excludeextensions
    :type: string
    :default: ``svg,ai,eps,gif``

    File extensions served verbatim as a plain :html:`<img>`, never processed
    (vector / animated formats that carry no meaningful width ladder).

..  confval:: secretsRotation
    :name: conf-secretsrotation
    :type: string
    :default: (empty)

    Additional valid signing secrets (comma-separated) for key rotation. See
    :ref:`configuration-signing`.

..  confval:: processorBaseUrl
    :name: conf-processorbaseurl
    :type: string
    :default: (empty)

    imgproxy only: base URL of the imgproxy endpoint (e.g.
    ``https://imgproxy.example``). See :ref:`configuration-imgproxy`.

..  confval:: processorSignKey
    :name: conf-processorsignkey
    :type: string
    :default: (empty)

    imgproxy only: HMAC **key** (hex). Empty key or salt → unsigned ``insecure``
    URLs (dev only).

..  confval:: processorSalt
    :name: conf-processorsalt
    :type: string
    :default: (empty)

    imgproxy only: HMAC **salt** (hex).

..  confval:: processorSourceBaseUrl
    :name: conf-processorsourcebaseurl
    :type: string
    :default: (empty)

    imgproxy only: origin prefix prepended to the source path. Leave empty when
    imgproxy has ``IMGPROXY_BASE_URL`` set (it then resolves the relative path
    itself).

..  _configuration-focus-area:

Editor focus area
=================

By default imaginator activates a **focus area** on the ``default`` crop variant
of every file reference: a movable box an editor draws to mark the part of the
image that must never be cropped away. When a rendered aspect ratio differs from
the source, the crop is centred on that box (falling back to the crop-area
centre when none is set) — see :ref:`image-endpoint`.

Core offers no focus area out of the box, and declaring a crop variant replaces
core's implicit one, so imaginator re-supplies the stock variant (free crop plus
the standard ``16:9``/``3:2``/``4:3``/``1:1`` ratios) and appends the focus area.
An existing ``default`` variant — or an existing focus area — configured by
another extension or your site is left untouched.

Activation is a default-on **feature toggle**, not an Extension Configuration
setting (it is read while the TCA is built). Disable it instance-wide in
:file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'] = false;

..  _configuration-imgproxy:

Offloaded processing with imgproxy
==================================

With :confval:`processor <conf-processor>` set to ``imgproxy``, ``srcset`` URLs
point straight at an `imgproxy <https://imgproxy.net/>`__ service — the webserver
never touches pixels, and the signed ``/_imaginator/`` endpoint is unused. Each
candidate is built as::

    {processorBaseUrl}/{signature}/rs:fill:{w}:{h}/g:sm/q:{quality}/plain/{source}@{ext}

The editor's per-reference crop variant is **not** replayed externally (imgproxy
uses smart gravity ``g:sm``); a reference resolves to its original file. Offloading
also sidesteps local encoder limits — e.g. AVIF at large dimensions that a thin
GraphicsMagick / libheif build fails to encode.

..  tip::
    For local development, the `ddev-imgproxy
    <https://github.com/barbieswimcrew/ddev-imgproxy>`__ add-on runs imgproxy
    next to the web container:

    ..  code-block:: bash

        ddev add-on get barbieswimcrew/ddev-imgproxy
        ddev restart

    It sets ``IMGPROXY_BASE_URL`` to the web container and runs keyless, so
    :confval:`processorBaseUrl <conf-processorbaseurl>` is the add-on URL
    (``https://<project>.ddev.site:8081``) and the sign key/salt stay empty.

..  _configuration-signing:

Signing & key rotation
======================

The active signing secret is always derived from the global
:php:`encryptionKey`. :confval:`secretsRotation <conf-secretsrotation>` lets old
secrets keep verifying while you rotate the key, so already-cached HTML
pointing at the previous signatures keeps working until it is re-rendered.

..  _configuration-csp:

Content Security Policy
=======================

The placeholder is **not** rendered as an inline :html:`style=""` attribute
(those cannot carry a CSP nonce). The :html:`<img>` only gets a CSS class; the
actual rule is registered through TYPO3's :php:`AssetCollector` and emitted as a
nonced :html:`<style>` element. Identical placeholders are deduplicated to a
single rule.

Depending on the chosen :confval:`lqip <conf-lqip>` option:

``thumbhash`` (default)
    The blurred preview is a :html:`data:` background image, so allow
    ``img-src data:`` (TYPO3's default frontend CSP already does).

``dominant-color``
    A plain background colour. Needs nothing beyond the nonced :html:`<style>`
    — the leanest, strictest-CSP-friendly option.

``none``
    No placeholder, no extra directive.
