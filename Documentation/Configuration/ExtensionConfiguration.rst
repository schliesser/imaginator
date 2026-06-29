..  include:: /Includes.rst.txt

..  _configuration-settings:

=======================
Extension Configuration
=======================

All settings below are read from the instance-wide Extension Configuration. The
imgproxy-only keys are documented with the :ref:`processors
<configuration-processors>`.

Settings
========

..  confval:: processor
    :name: conf-processor
    :type: string
    :default: ``local:async``

    Image processor: ``local:async`` (signed endpoint + 302), ``local:sync``
    (static ``srcset``, no middleware) or ``imgproxy`` (offloaded). See
    :ref:`configuration-processors`.

..  confval:: maxDimension
    :name: conf-maxdimension
    :type: integer
    :default: ``2000``

    Largest image dimension in pixels. The width ladder is capped to this value.

..  confval:: fixedHeightDprCap
    :name: conf-fixed-height-dpr-cap
    :type: integer
    :default: ``3``

    Highest device pixel ratio a :ref:`fixed-height tier <usage-fixed-height>`
    ships a ``min-resolution`` ``<source>`` for. ``3`` emits 1×/2×/3× tiers; ``2``
    drops the 3× tier; ``1`` disables per-DPR sources (single 1× height).

..  confval:: ladder
    :name: conf-ladder
    :type: string
    :default: ``320,420,560,740,980,1300,1720,2000``

    Width-ladder rungs (comma-separated px). An arbitrary requested width is
    quantized **up** to the nearest rung, which bounds the number of processed
    files and the set of signable URLs.

..  confval:: breakpoints
    :name: conf-breakpoints
    :type: string
    :default: ``xs:0,sm:576,md:768,lg:992,xl:1200``

    Design-system breakpoints (``alias:min-width`` px) used to resolve a
    per-breakpoint aspect-ratio map. ``xs:0`` is the base ratio.

..  confval:: format
    :name: conf-format
    :type: string
    :default: ``avif``

    The single output format — ``avif`` or ``webp`` — applied uniformly to the
    :html:`<img>` and every :html:`<source>`.

..  confval:: quality.avif
    :name: conf-quality-avif
    :type: integer
    :default: ``50``

    AVIF quality. AVIF's scale sits lower than JPEG / WebP for the same
    perceived quality.

..  confval:: quality.webp
    :name: conf-quality-webp
    :type: integer
    :default: ``72``

    WebP quality.

..  confval:: lqip
    :name: conf-lqip
    :type: string
    :default: ``thumbhash``

    Low-quality image placeholder. One of ``thumbhash``, ``dominant-color`` or
    ``none``. See :ref:`configuration-csp`.

..  confval:: excludeExtensions
    :name: conf-excludeextensions
    :type: string
    :default: ``svg,ai,eps,gif``

    File extensions served verbatim as a plain :html:`<img>`, never processed
    (vector / animated formats that carry no meaningful width ladder).

..  confval:: secretsRotation
    :name: conf-secretsrotation
    :type: string
    :default: (empty)

    Additional valid signing secrets (comma-separated) for key rotation. See
    :ref:`configuration-signing`.

..  confval:: processorBaseUrl
    :name: conf-processorbaseurl
    :type: string
    :default: (empty)

    imgproxy only: base URL of the imgproxy endpoint (e.g.
    ``https://imgproxy.example``). See :ref:`configuration-imgproxy`.

..  confval:: processorSignKey
    :name: conf-processorsignkey
    :type: string
    :default: (empty)

    imgproxy only: HMAC **key** (hex). Empty key or salt → unsigned ``insecure``
    URLs (dev only).

..  confval:: processorSalt
    :name: conf-processorsalt
    :type: string
    :default: (empty)

    imgproxy only: HMAC **salt** (hex).

..  confval:: processorSourceBaseUrl
    :name: conf-processorsourcebaseurl
    :type: string
    :default: (empty)

    imgproxy only: origin prefix prepended to the source path. Leave empty when
    imgproxy has ``IMGPROXY_BASE_URL`` set (it then resolves the relative path
    itself).

..  _configuration-signing:

Signing & key rotation
======================

The active signing secret is always derived from the global
:php:`encryptionKey`. :confval:`secretsRotation <conf-secretsrotation>` lets old
secrets keep verifying while you rotate the key, so already-cached HTML
pointing at the previous signatures keeps working until it is re-rendered.
