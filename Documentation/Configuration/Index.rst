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
    :default: ``local``

    Processing backend. Version 1 ships the ``local`` backend only
    (GraphicsMagick / ImageMagick).

..  confval:: maxDimension
    :name: conf-maxdimension
    :type: integer
    :default: ``2000``

    Largest image dimension in pixels. The width ladder is capped to this value.

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

..  confval:: formats
    :name: conf-formats
    :type: string
    :default: ``avif,webp``

    Negotiated output formats, most-preferred first. Emitted as stacked
    :html:`<source type="image/…">` tiers.

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
