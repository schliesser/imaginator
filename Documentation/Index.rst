..  include:: /Includes.rst.txt

..  _start:

==========
Imaginator
==========

:Extension key:
    imaginator

:Package name:
    schliesser/imaginator

:Version:
    |release|

:Language:
    en

:Author:
    André Buchmann

:License:
    This document is published under the
    `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
    |today|

----

Zero-config responsive images for TYPO3. The integrator writes **no**
:html:`sizes` and **no** per-image breakpoints: at render time the extension
emits a real :html:`<picture>` / :html:`<img>` with a quantized width-ladder
:html:`srcset` and :html:`sizes="auto"`, so the browser's preload scanner
fetches the correctly-sized image in a single request. Candidate URLs are
HMAC-signed and served as real image bytes by a local processing endpoint.
JavaScript is never required for sharpness.

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Zero configuration

        Install, drop in :html:`<i:image>`, done. No :html:`sizes`, no
        breakpoint lists, no measuring JavaScript.

    ..  card:: Signed image endpoint

        Candidate URLs are HMAC-signed and only ladder-quantized widths are
        ever processed, so the endpoint cannot be abused to exhaust the server.

    ..  card:: Modern formats + LQIP

        Stacked AVIF + WebP :html:`<picture>` tiers and low-quality
        placeholders (ThumbHash, dominant colour, or none).

    ..  card:: LCP-friendly

        :html:`priority` images drop lazy loading, gain
        :html:`fetchpriority="high"` and a :html:`<head>` preload link.

..  toctree::
    :maxdepth: 1
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Editor/Index
