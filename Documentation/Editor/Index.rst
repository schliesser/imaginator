..  include:: /Includes.rst.txt

..  _editor:

================================
Content-element aspect ratios
================================

Imaginator adds an :guilabel:`Aspect ratios` field to the content element's
:guilabel:`mediaAdjustments` palette (next to :guilabel:`Image width`,
:guilabel:`Image height` and :guilabel:`Border`). It appears only on content
types that render images.

..  figure:: /Images/AspectRatioField.png
    :alt: The Aspect ratios field in a content element's Media Adjustments palette
    :class: with-border with-shadow
    :zoom: lightbox

    The :guilabel:`Aspect ratios` field: each design-system breakpoint picks a
    ratio (or inherits the next-smaller one), previewed as a swatch.

The field stores a per-breakpoint ratio map as a JSON string, for example:

..  code-block:: json
    :caption: Stored aspect_ratio value

    {"xs": "1:1", "md": "4:3", "lg": "16:9"}

Keys are :confval:`breakpoint aliases <conf-breakpoints>` (``md``, ``lg``, …) or
literal min-widths in pixels (``768``; ``0`` is the base). A breakpoint that is
absent — or set to ``auto`` — inherits the next-smaller ratio.

A value may also be a fixed CSS height (``"600px"``) instead of a ``"W:H"``
ratio: that tier pins the height while the width climbs the ladder — a
full-bleed hero. See :ref:`usage-fixed-height`.

Binding the field
=================

The raw column can be passed straight into the ViewHelper; no DataProcessor is
needed:

..  code-block:: html
    :caption: Fluid template

    <i:image image="{ref}" aspectRatio="{ce.data.aspect_ratio}"
             alt="{ce.data.header}"/>

The ViewHelper decodes the JSON map and resolves it to per-breakpoint
:html:`<source>` tiers using the configured
:confval:`breakpoints <conf-breakpoints>`.

..  _editor-content-blocks:

Content Blocks
==============

When `EXT:content_blocks
<https://extensions.typo3.org/extension/content_blocks>`__ is installed, the
same field is available as a field type so block authors can declare it in YAML:

..  code-block:: yaml
    :caption: ContentBlocks field definition

    - identifier: aspect_ratio
      type: AspectRatio
      allowedRatios: '16:9,21:9'   # optional; defaults to the full set

``allowedRatios`` is a comma-separated list of the ratios offered in the editor.
Omit it to expose the full default set.

..  note::
    The Content Blocks integration is optional. The field type class only loads
    when :file:`EXT:content_blocks` is present.
