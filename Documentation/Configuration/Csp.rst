..  include:: /Includes.rst.txt

..  _configuration-csp:

========================
Content Security Policy
========================

The placeholder (LQIP)
======================

The placeholder is **not** rendered as an inline :html:`style=""` attribute
(those cannot carry a CSP nonce). The :html:`<img>` only gets a CSS class; the
actual rule is registered through TYPO3's :php:`AssetCollector` and emitted as a
nonced :html:`<style>` element. Identical placeholders are deduplicated to a
single rule.

Depending on the chosen :confval:`lqip <conf-lqip>` option:

``thumbhash`` (default)
    The blurred preview is a :html:`data:` background image, so allow
    ``img-src data:`` (TYPO3's default frontend CSP already does).

``dominant-color``
    A plain background colour. Needs nothing beyond the nonced :html:`<style>`
    — the leanest, strictest-CSP-friendly option.

``none``
    No placeholder, no extra directive.

The ``sizes="auto"`` polyfill
=============================

Whenever a processed image renders, Imaginator queues the
:ref:`sizes="auto" polyfill <introduction-sizes-auto>` — an external
:html:`<script>` (``EXT:imaginator/Resources/Public/JavaScript/frontend/autosizes.js``)
registered through the :php:`AssetCollector`. It is **not** inline JavaScript: it
is a same-origin script file, nonce-tagged so a strict policy accepts it (TYPO3
v14 via ``csp``, v13 via ``useNonce`` — the same split the LQIP :html:`<style>`
uses).

So a strict CSP needs to allow the nonced script under ``script-src`` — TYPO3's
default frontend CSP nonces scripts automatically, so nothing extra is required.
If you maintain a custom policy, make sure ``script-src`` permits same-origin
nonced scripts (e.g. ``script-src 'self' 'nonce-…'``). Pages without any
:html:`<i:image>` queue no script at all.
