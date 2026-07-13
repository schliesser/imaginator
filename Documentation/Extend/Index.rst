..  include:: /Includes.rst.txt

..  _extend:

======
Extend
======

Imaginator's render layer is **processor-agnostic**: it only ever sees the
:php:`ImageProcessorInterface`, so the same :html:`<picture>` / :html:`<img>` is
emitted regardless of who produces the pixels. Adding a new processor (a CDN,
another self-hosted service) is a pure integration concern, no core change.

..  toctree::
    :maxdepth: 2
    :titlesonly:

    CustomProcessor
