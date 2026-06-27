..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

What it does
============

Imaginator renders responsive images without any per-image configuration. At
page-render time it emits a real :html:`<picture>` / :html:`<img>` carrying a
quantized **width-ladder** :html:`srcset` plus :html:`sizes="auto"`, so the
browser's preload scanner picks the correctly-sized candidate in one
round-trip. The :html:`width` and :html:`height` attributes are always emitted
from the largest ladder rung, so there is zero layout shift (CLS).

How it works
============

#.  The :ref:`\<i:image\> ViewHelper <usage>` turns one ratio (or a
    per-breakpoint ratio map) into a width ladder and renders the markup.
#.  In the default ``local:async`` mode each candidate is a **signed URL**
    pointing at the :ref:`image endpoint <image-endpoint>`.
#.  A PSR-15 middleware verifies the signature, re-checks the width against the
    ladder, processes the image through TYPO3's ``ImageService`` (GraphicsMagick
    or ImageMagick, per your ``GFX`` config) and 302-redirects to the processed
    file with immutable cache headers. (In ``local:sync`` mode the processed-file
    URL is written straight into ``srcset`` and the middleware is skipped — see
    :ref:`processor <conf-processor>`.)

Quantizing every requested width up to a fixed ladder rung is what bounds the
set of processed files **and** makes the signed URLs safe: only rung sizes ever
verify, so an attacker cannot request arbitrary dimensions.

..  _introduction-status:

Status
======

Local processing (async + sync modes, via TYPO3's ``ImageService``), the
:html:`<i:image>` ViewHelper, stacked AVIF + WebP :html:`<picture>` tiers,
low-quality placeholders and the content-element aspect-ratio field work
end-to-end.

..  note::
    The JavaScript enhancement layer, external CDN providers
    (Thumbor / imgproxy / imgix / Cloudflare / Cloudinary) and per-site
    overrides of the extension settings are planned follow-ups.

Requirements
============

*   PHP **8.3+** (tested on 8.3 / 8.4 / 8.5)
*   TYPO3 **13.4 LTS** or **14.x**
*   A working image processor (GraphicsMagick or ImageMagick, configured through
    TYPO3's standard :guilabel:`GFX` settings)
*   A non-empty :php:`encryptionKey` (standard on every TYPO3 install)
