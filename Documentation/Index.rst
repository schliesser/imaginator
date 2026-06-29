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

For editors
===========

A backend **aspect-ratio field** per content element lets editors pick the
framing once — **per breakpoint** — and get **uniform, consistent images**
across every element of that type, with no ragged grids from mismatched upload
dimensions. Crop and focus area are honored, so the chosen subject stays in
frame at every size.

For developers
==============

Drop one :html:`<i:image>` and get sharp, perfectly-sized images on every device
— no hand-tuned :html:`sizes`, no breakpoint lists, no layout shift (CLS). The
width ladder bounds processing to a fixed set of sizes, so you serve fewer,
smaller bytes and score better Core Web Vitals (LCP/CLS) out of the box.
Processing is pluggable: classic **sync** on first request, **async** via a
signed middleware endpoint (default), or an **external processor** such as
imgproxy.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension does, how it works, where to get support and how to
        contribute.

    ..  card:: :ref:`Installation <installation>`

        Install via Composer, activate the extension and derive the signing key.

    ..  card:: :ref:`Configuration <configuration>`

        Extension Configuration settings, the focus-area feature toggle,
        processors and Content Security Policy.

    ..  card:: :ref:`Usage <usage>`

        The :html:`<i:image>` Fluid ViewHelper, the aspect-ratio field and
        crop / focus-area handling.

    ..  card:: :ref:`Extend <extend>`

        Register your own image processor by tagging a service.

    ..  card:: :ref:`Migrations <migrations>`

        Move an existing site from EXT:pictureino to Imaginator.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Extend/Index
    Migrations/Index
