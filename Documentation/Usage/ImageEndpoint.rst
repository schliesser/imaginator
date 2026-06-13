..  include:: /Includes.rst.txt

..  _image-endpoint:

==========================
The image endpoint & signing
==========================

Each candidate URL has one of these forms:

..  code-block:: text
    :caption: Signed candidate URLs

    reference: /_imaginator/{16-hex-signature}/r{referenceUid}/{cropVariant}/{width}x{height}.{ext}
    file:      /_imaginator/{16-hex-signature}/f{fileUid}/{cropVariant}/{width}x{height}.{ext}

The uid is site-unique, so no storage segment is needed. A PSR-15 middleware
verifies the signature, re-checks the width against the
:confval:`ladder <conf-ladder>`, processes the image and **302-redirects to the
processed file** with :html:`Cache-Control: public, max-age=31536000,
immutable`.

..  note::
    A forged or tampered URL returns **403**, and only ladder-quantized widths
    are ever processed — so the endpoint cannot be abused to exhaust the server
    with arbitrary sizes.

..  _image-endpoint-crop:

Crop & focus areas
==================

When an ``image`` (a FAL :php:`FileReference`) is rendered, the URL keys on the
**reference uid** (``r…``) and the middleware resolves the editor's **crop
variant** server-side: it reads the reference's crop JSON and fits the requested
ratio inside the **crop area**, centered on the **focus area**. The framing the
editor chose is honoured and **no crop geometry is exposed in the URL**.

The ladder is bounded by the crop area, so a tightly-cropped region is never
upscaled. A ``src`` given as a path or plain file uid (``f…``) has no crop data
and is centre-cropped to the ratio.
