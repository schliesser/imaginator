..  include:: /Includes.rst.txt

..  _usage:

=====
Usage
=====

Editors pick the framing once per content element with the
:ref:`aspect-ratio field <editor>`; the chosen :ref:`crop & focus area
<image-endpoint-crop>` is honoured server-side; and the
:ref:`\<i:image\> ViewHelper <usage-viewhelper>` turns it all into a real,
zero-config responsive :html:`<picture>` / :html:`<img>`.

..  toctree::
    :maxdepth: 2
    :titlesonly:

    AspectRatioField
    CropAndFocusArea
    FluidViewHelper
