..  include:: /Includes.rst.txt

..  _configuration-processors:

==========
Processors
==========

The :confval:`processor <conf-processor>` setting selects who turns the source
file into the derivatives that land in ``srcset``. Both local modes drive
TYPO3's ``ImageService`` exclusively (the binary is whatever your ``GFX`` config
selects — GraphicsMagick or ImageMagick); there are no direct
GraphicsMagick/ImageMagick/GD calls.

``local:async`` (default)
    Processing is deferred to the first request per variant: a cold ``srcset``
    candidate points at the signed ``/_imaginator/`` endpoint and a middleware
    materializes the derivative, then 302-redirects to the processed file. Once a
    derivative exists, ``srcset`` points at the static ``_processed_/…`` file
    directly, so warm images skip the middleware entirely.

``local:sync``
    Derivatives are materialized synchronously at render time and the static
    ``_processed_/…`` file URL is written straight into ``srcset``, so the
    middleware is never involved and requests are plain static-file serves.
    Trade-off: higher cold-render cost, then no PHP hop per image.

``imgproxy``
    Offloaded; ``srcset`` points straight at the provider (see
    :ref:`configuration-imgproxy`). imgproxy has no result cache — a fronting
    CDN / reverse-proxy cache is **mandatory** in production.

``imagor``
    Offloaded; ``srcset`` points straight at the provider (see
    :ref:`configuration-imagor`). Enable imagor's result storage (or put a
    fronting cache in front) so derivatives are rendered once, not per hit.

..  tip::
    Integrators can register a **custom processor** by tagging a service with
    ``imaginator.image_processor`` (attribute ``key``) and selecting it here. See
    :ref:`extend-custom-processor`.

..  _image-endpoint:

The image endpoint & signing
============================

In ``local:async`` mode each candidate URL has one of these forms:

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

The signing secret is derived automatically from the global
:php:`encryptionKey`; no configuration is needed. See :ref:`configuration-signing`
for key rotation, and :ref:`image-endpoint-crop` for how the editor's crop is
honoured server-side.

..  _configuration-imgproxy:

Offloaded processing with imgproxy
==================================

With :confval:`processor <conf-processor>` set to ``imgproxy``, ``srcset`` URLs
point straight at an `imgproxy <https://imgproxy.net/>`__ service — the webserver
never touches pixels, and the signed ``/_imaginator/`` endpoint is unused. Each
candidate is built as::

    {processorBaseUrl}/{signature}/rs:fill:{w}:{h}/g:sm/q:{quality}/plain/{source}@{ext}

For a **reference with a stored crop or focus area** the editor's crop variant
is replayed externally: the same ratio-fitted, focus-positioned rect local
processing uses goes into imgproxy's crop op (``c:{cw}:{ch}:nowe:{x}:{y}``,
replacing ``g:sm``), so external output matches local output. The reference
resolves to its original file — imgproxy does the cropping. Plain files and
references without editor crop use smart gravity. Offloading
also sidesteps local encoder limits — e.g. AVIF at large dimensions that a thin
GraphicsMagick / libheif build fails to encode.

..  tip::
    For local development, the `ddev-imgproxy
    <https://github.com/barbieswimcrew/ddev-imgproxy>`__ add-on runs imgproxy
    next to the web container:

    ..  code-block:: bash

        ddev add-on get barbieswimcrew/ddev-imgproxy
        ddev restart

    It sets ``IMGPROXY_BASE_URL`` to the web container and runs keyless, so
    :confval:`processorBaseUrl <conf-processorbaseurl>` is the add-on URL
    (``https://<project>.ddev.site:8081``) and the sign key/salt stay empty.

..  _configuration-imagor:

Offloaded processing with imagor
================================

With :confval:`processor <conf-processor>` set to ``imagor``, ``srcset`` URLs
point straight at an `imagor <https://imagor.net/>`__ service (thumbor-compatible
URL grammar). Each candidate is built as::

    {processorBaseUrl}/{signature}/{w}x{h}/smart/filters:quality({q}):format({ext})/{source}

The signature is a URL-safe base64 HMAC of the path, keyed with
:confval:`processorSignKey <conf-processorsignkey>` — imagor's plain-string
``IMAGOR_SECRET`` (run imagor with ``IMAGOR_SIGNER_TYPE=sha256``).
:confval:`processorSalt <conf-processorsalt>` is unused; imagor's scheme has no
salt. An empty key emits ``unsafe`` URLs, which imagor only accepts with
``IMAGOR_UNSAFE=1`` (dev only). As with imgproxy, a reference's stored editor
crop is replayed externally — the crop rect becomes imagor's manual crop segment
(``{x1}x{y1}:{x2}x{y2}``, replacing ``smart``); plain files and references
without editor crop fall back to ``smart`` detection.

Unlike the edge-cached SaaS providers, imagor caches derivatives **only when
result storage is enabled** (``FILE_RESULT_STORAGE_BASE_DIR`` or the S3
equivalent). Enable it — or front imagor with a CDN / reverse-proxy cache —
otherwise every browser hit reprocesses the image.

..  tip::
    For local development this repository ships
    ``.ddev/docker-compose.imagor.yaml``: imagor listens on
    ``https://<project>.ddev.site:8083`` with the secret
    ``imaginator-dev-secret`` and result storage on a volume — so
    :confval:`processorBaseUrl <conf-processorbaseurl>` is that URL and
    :confval:`processorSignKey <conf-processorsignkey>` the dev secret.
