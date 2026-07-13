..  include:: /Includes.rst.txt

..  _introduction-what:

================
What does it do?
================

Imaginator renders responsive images without any per-image configuration. At
page-render time it emits a real :html:`<picture>` / :html:`<img>` carrying a
quantized **width-ladder** :html:`srcset` plus :html:`sizes="auto"`, so the
browser's preload scanner picks the correctly-sized candidate in one
round-trip. The :html:`width` and :html:`height` attributes are always emitted
from the largest ladder rung, so there is zero layout shift (CLS).

How it works
============

**The width ladder.** Instead of generating a derivative for every width a
layout could ever request, Imaginator works from a fixed, configurable list of
rung widths. The default :confval:`ladder <conf-ladder>` (
``320,420,560,740,980,1300,1720,2000,2560,3200,3840``) covers everything from
small phones to 4K (UHD) displays out of the box. Every rung becomes one
``srcset`` candidate; its height follows from the aspect ratio. Rungs larger
than the source image (or :confval:`maxDimension <conf-maxdimension>`) are
capped and deduplicated, so a small source never gets upscaled. Because any
requested width is quantized **up** to the nearest rung, at most one ladder of
derivatives ever exists per image and ratio, no matter how many different
layout widths the site renders it at.

The :ref:`\<i:image\> ViewHelper <usage-viewhelper>` optionally takes either a
single aspect ratio or a **per-breakpoint ratio map** and turns it into the
markup. A single ratio renders an :html:`<img>` with the full ladder; a map
renders a :html:`<picture>` with one :html:`<source>` per breakpoint, each its
own ladder — true **art direction** with no hand-written ``media`` queries.
Editors set that map per content element in the :ref:`aspect-ratio field
<editor>`, and the chosen :ref:`crop & focus area <image-endpoint-crop>` is
honoured server-side, so the subject stays in frame at every size.

**Pluggable processing.** The URLs in ``srcset`` come from a
:ref:`processor <configuration-processors>`; the render layer is identical
whoever produces the pixels. You choose where the work happens:

*   **Async** (the default): a cold derivative is processed on first request via
    a signed endpoint, then served as a static file; the render itself stays
    cheap and processing is spread over real traffic.
*   **Sync**: derivatives are materialized at render time and written straight
    into ``srcset`` as static files (no PHP hop per image, higher cold-render
    cost).
*   **External** (e.g. imgproxy): ``srcset`` points straight at the provider, so
    the webserver never touches pixels. The lightest load on your TYPO3 server.

**Bounded and safe.** Every requested width is quantized **up** to a fixed
ladder rung. That bounds the set of processed files, and on the signed async
endpoint means only rung sizes ever verify, so an attacker cannot request
arbitrary dimensions.

..  _introduction-sizes-auto:

Why ``sizes="auto"`` is the new cool way
========================================

A responsive :html:`<img srcset="…">` only picks the right candidate if it also
knows **how wide it will be laid out**. Traditionally that meant hand-writing a
:html:`sizes` attribute — a list of media-query/width pairs that *re-describes*
your CSS layout in the markup:

..  code-block:: html
    :caption: The old way — a sizes list that duplicates the layout

    <img srcset="img-320.jpg 320w, img-640.jpg 640w, img-1280.jpg 1280w"
         sizes="(min-width: 1200px) 760px, (min-width: 768px) 50vw, 100vw"
         alt="…">

That is brittle. The numbers are a guess, they have to be repeated for every
image, and the moment the CSS changes — a new grid, a wider container, a sidebar
that collapses — the :html:`sizes` list is silently wrong and the browser
downloads the wrong size (usually too big). It is exactly the kind of
per-image configuration Imaginator exists to remove.

:html:`sizes="auto"` flips this around. The browser already knows the final
laid-out width of the image from your CSS — so it just **uses it**, picking the
matching ``srcset`` candidate with no hand-written numbers at all:

..  code-block:: html
    :caption: The new way — the browser reads the real layout width

    <img srcset="img-320.jpg 320w, … img-3840.jpg 3840w"
         sizes="auto" loading="lazy" width="3840" height="2160" alt="…">

This is why it pairs perfectly with the width ladder: Imaginator emits the
candidates and lets the browser — which is the only party that actually knows
the rendered width — choose. No layout duplication, no drift when the design
changes, correct on every viewport and container. Because the value is resolved
during the browser's preload scan, the right image is fetched in the **first**
round-trip.

..  note::
    :html:`sizes="auto"` is supported natively in Chrome/Edge (126+) and Firefox,
    and in Safari / iOS Safari from **version 27**. Older Safari/iOS treat the
    unknown value as ``100vw`` and fetch an oversized — but still sharp —
    candidate. To close that gap Imaginator ships a tiny vendored
    `Shopify/autosizes <https://github.com/Shopify/autosizes>`__ polyfill that
    writes a concrete pixel :html:`sizes` on those browsers. It is **never
    load-bearing** — sharpness comes from the server-rendered ladder, so the page
    works fully with JavaScript disabled. The polyfill is the *only* JavaScript
    the extension uses; see its :ref:`CSP note <configuration-csp>`.

    :html:`sizes="auto"` is spec-valid only on :html:`loading="lazy"` images,
    which is exactly where Imaginator emits it — :ref:`priority/LCP images
    <usage-priority>` get an explicit ``sizes="100vw"`` instead.
