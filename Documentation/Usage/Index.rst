..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

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
           <source> each. The map is {mediaQuery: ratio}; the entry whose media
           is empty (or the last entry) becomes the <img> fallback. #}
        <i:image image="{hero}"
                 aspectRatio="{'(min-width:992px)': '16:9', '(max-width:991px)': '1:1'}"
                 alt="Hero"/>

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
        -   ``"W:H"``, a ``{breakpoint: "W:H"}`` map, or the raw
            :ref:`aspect_ratio JSON <editor>`. Omitted: the crop variant (for a
            reference) or original image ratio.
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

Only the most-preferred format is preloaded (gated by :html:`type`, so other
browsers don't double-download), and its :html:`imagesrcset` / :html:`imagesizes`
mirror the rendered tier exactly. This satisfies Lighthouse's *LCP request is
discoverable*, *not lazily loaded* and *fetchpriority should be applied* audits.

..  important::
    Use :html:`priority="1"` on **one** image per page — the single most
    important above-the-fold image.

..  toctree::
    :maxdepth: 1
    :titlesonly:

    ImageEndpoint
