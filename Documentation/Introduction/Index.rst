..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

Imaginator renders responsive images without any per-image configuration. At
page-render time it emits a real :html:`<picture>` / :html:`<img>` carrying a
quantized **width-ladder** :html:`srcset` plus :html:`sizes="auto"`, so the
browser's preload scanner picks the correctly-sized candidate in one
round-trip. The :html:`width` and :html:`height` attributes are always emitted
from the largest ladder rung, so there is zero layout shift (CLS).

..  toctree::
    :maxdepth: 2
    :titlesonly:

    WhatDoesItDo
    Support
    Contribution

Requirements
============

*   PHP **8.3+** (tested on 8.3 / 8.4 / 8.5)
*   TYPO3 **13.4 LTS** or **14.3 LTS**
*   A working image processor (GraphicsMagick or ImageMagick, configured through
    TYPO3's standard :guilabel:`GFX` settings) for local processing; alternatively
    an external processor (e.g. imgproxy)
*   A non-empty :php:`encryptionKey` (standard on every TYPO3 install)
