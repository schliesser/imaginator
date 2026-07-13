..  include:: /Includes.rst.txt

..  _image-endpoint-crop:

==================
Crop & Focus Area
==================

When an ``image`` (a FAL :php:`FileReference`) is rendered, the editor's **crop
variant** is resolved server-side: imaginator reads the reference's crop JSON and
fits the requested ratio inside the **crop area**, centered on the **focus area**.
The framing the editor chose is honoured and in ``local:async`` mode the URL
keys only on the reference uid (``r…``).

The ladder is bounded by the crop area, so a tightly-cropped region is never
upscaled. A ``src`` given as a path or plain file uid (``f…``) has no crop data
and is center-cropped to the ratio.

..  seealso::
    The focus area is a default-on feature toggle (see
    :ref:`configuration-focus-area`). For the signed URL forms and the middleware,
    see :ref:`image-endpoint`.
