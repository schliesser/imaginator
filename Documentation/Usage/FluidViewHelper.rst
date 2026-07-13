..  include:: /Includes.rst.txt

..  _usage-viewhelper:

==================
Fluid ViewHelper
==================

Declare the ViewHelper namespace once per template and call :html:`<i:image>`.

..  code-block:: html
    :caption: Fluid template

    <html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"
          data-namespace-typo3-fluid="true">

        {# Simplest case: one ratio, renders an <img> with the full ladder #}
        <i:image image="{fileReference}" aspectRatio="16:9"
                 alt="{fileReference.description}"/>

        {# By file UID or path #}
        <i:image src="{file.uid}" treatIdAsReference="0" aspectRatio="4:3"
                 alt="A product"/>

        {# Art direction: per-breakpoint ratios render a <picture> with one
           <source> each. Keys are breakpoint aliases (xs, lg, …) or px min-widths
           (0, 992, …); each becomes a (min-width:Npx) <source>, and the min-0/xs
           entry becomes the <img> fallback. #}
        <i:image image="{hero}"
                 aspectRatio="{xs: '1:1', lg: '16:9'}"
                 alt="Hero"/>

        {# Full-bleed hero: a fixed CSS height instead of a ratio. Width climbs
           the ladder, the height stays pinned (the rendered image only gets
           wider/flatter from tablet to 4K, never taller). #}
        <i:image image="{hero}" aspectRatio="600px" alt="Hero"/>

        {# Mix ratio and fixed-height tiers per breakpoint: a portrait-ish ratio
           on small screens, a pinned height on large ones. #}
        <i:image image="{hero}"
                 aspectRatio="{xs: '16:9', lg: '600px'}" alt="Hero"/>

        {# LCP / above-the-fold image #}
        <i:image image="{hero}" aspectRatio="16:9" alt="Hero" priority="1"/>
    </html>

..  _usage-arguments:

Arguments
=========

..  list-table::
    :header-rows: 1
    :widths: 20 20 15 45

    *   -   Argument
        -   Type
        -   Default
        -   Description
    *   -   ``image``
        -   ``File`` / ``FileReference``
        -   –
        -   FAL object. Use this **or** ``src``.
    *   -   ``src``
        -   string
        -   ``''``
        -   File **UID** or path (e.g. :file:`fileadmin/img/x.jpg`).
    *   -   ``treatIdAsReference``
        -   bool
        -   ``false``
        -   Treat ``src`` as a ``sys_file_reference`` UID.
    *   -   ``aspectRatio``
        -   string | map
        -   –
        -   ``"W:H"``, a fixed CSS height ``"600px"`` (full-bleed hero: width
            climbs the ladder, height pinned), a ``{breakpoint: "W:H"|"Npx"}``
            map, or the raw :ref:`aspect_ratio JSON <editor>`. Omitted: the crop
            variant (for a reference) or original image ratio. A malformed tier
            value raises an error; ``auto`` / empty skips the tier.
    *   -   ``cropVariant``
        -   string
        -   ``default``
        -   FAL crop variant to use.
    *   -   ``alt``
        -   string
        -   ``''``
        -   Alternative text.
    *   -   ``title``
        -   string
        -   –
        -   ``title`` attribute on the :html:`<img>`.
    *   -   ``class``
        -   string
        -   –
        -   CSS class on the :html:`<img>`.
    *   -   ``priority``
        -   bool
        -   ``false``
        -   Mark as the LCP image (see :ref:`usage-priority`).

:html:`width` and :html:`height` are always emitted from the largest rung, so
there is zero layout shift.

..  _usage-fixed-height:

Fixed-height tiers
==================

A tier value of ``"600px"`` (or ``"600"``) pins the **height** instead of
deriving it from a ratio. Width still climbs the ladder, so a full-bleed hero
keeps a constant CSS height across viewports while the served crop only gets
wider and flatter, but never needlessly taller, from tablet to 4K.

The ``px`` value is a **CSS** height. To stay sharp on high-DPR screens the tier
is emitted as several ``<source>``\ s gated by ``min-resolution``: 1× serves the
given height, 2× serves twice it, 3× three times up to
:confval:`fixedHeightDprCap <conf-fixed-height-dpr-cap>` (default 3). A low-DPR
screen matches no ``min-resolution`` source and gets the cheap 1× height; a
retina screen matches a gated source and stays crisp. Height is chosen by the
device pixel ratio, width by the layout. So the served pixels match the need
with no waste. Heights are never scaled beyond the source image's own height.

Pair a fixed-height image with CSS such as
``width:100%; height:600px; object-fit:cover``; any surplus served height is
re-cropped by ``object-fit``.

..  _usage-priority:

Priority / LCP images
=====================

Set :html:`priority="1"` on the above-the-fold (LCP) image. Imaginator then:

*   drops :html:`loading="lazy"` and adds :html:`fetchpriority="high"` on the
    :html:`<img>`;
*   renders an explicit :html:`sizes="100vw"` instead of :html:`sizes="auto"`;
*   adds a :html:`<link rel="preload" as="image" imagesrcset="…"
    imagesizes="100vw" fetchpriority="high">` to the :html:`<head>`, so the
    request is discoverable in the initial document.

This satisfies Lighthouse's *LCP request is discoverable*, *not lazily loaded*
and *fetchpriority should be applied* audits.

..  important::
    Use :html:`priority="1"` on the single most important above-the-fold image.
