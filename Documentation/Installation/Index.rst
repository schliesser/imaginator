..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

Imaginator can be installed with Composer or from the TYPO3 Extension Repository
(TER) via the Extension Manager.

..  tip::
    Installing the extension is enough to use the :html:`<i:image>` ViewHelper.
    No Site Set or TypoScript is required. Configure the extension under
    :guilabel:`Settings > Extension Configuration > imaginator`
    (see :ref:`configuration`).

Installation with Composer
==========================

..  code-block:: bash
    :caption: Install via Composer

    composer require schliesser/imaginator

Installation from TER
=====================

In a non-Composer (legacy) installation, fetch the extension from the
`TYPO3 Extension Repository
<https://extensions.typo3.org/extension/imaginator>`__ through
:guilabel:`Admin Tools > Extensions` in the backend, then activate it there.

Signing key
===========

The signing secret is derived automatically from
:php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']`, which is set on
every standard TYPO3 install. No configuration is needed.

..  warning::
    Changing :php:`encryptionKey` invalidates previously generated image URLs.
    Flush the frontend caches after changing it. Already-cached HTML pointing
    at the old signatures returns :ref:`403 <image-endpoint>` until re-rendered.

..  _installation-versioning:

Versioning
==========

This project uses `semantic versioning <https://semver.org/>`__, which means
that

*   **bugfix updates** (e.g. 1.0.0 => 1.0.1) just include small bugfixes or
    security-relevant stuff without breaking changes,
*   **minor updates** (e.g. 1.0.0 => 1.1.0) include new features and smaller
    tasks without breaking changes, and
*   **major updates** (e.g. 1.0.0 => 2.0.0) contain breaking changes, which can
    be refactorings, features or bugfixes.
