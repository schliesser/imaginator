..  include:: /Includes.rst.txt

..  _migrations-pictureino:

==========
Pictureino
==========

Imaginator is built on the **per-breakpoint aspect-ratio** idea pioneered by
`EXT:pictureino <https://extensions.typo3.org/extension/pictureino>`__
(``zeroseven/pictureino``) — that extension is where the concept comes from. The
two **conflict** by design — they register the same backend field — so they
cannot run side by side:

..  code-block:: bash
    :caption: Swap the package

    composer remove zeroseven/pictureino
    composer require schliesser/imaginator

No database migration
=====================

The aspect-ratio field is the **unprefixed** ``aspect_ratio`` column — the same
column pictureino used — so existing editor data is preserved as-is. There is no
schema change and no data migration to run.

Template changes
================

Replace the pictureino ViewHelper call with :ref:`\<i:image\> <usage-viewhelper>`.
The per-breakpoint ``{alias: ratio}`` map keys resolve to the configured
:confval:`breakpoints <conf-breakpoints>`, matching pictureino's behaviour:

..  code-block:: html
    :caption: Fluid template

    <html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"
          data-namespace-typo3-fluid="true">

        <i:image image="{ref}" aspectRatio="{ce.data.aspect_ratio}"
                 alt="{ce.data.header}"/>
    </html>

After swapping templates, review the :ref:`configuration <configuration>` — the
:confval:`ladder <conf-ladder>`, :confval:`format <conf-format>` and
:confval:`processor <conf-processor>` defaults differ from pictureino's.
