..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

Composer
========

..  code-block:: bash
    :caption: Install via Composer

    composer require schliesser/imaginator

Then activate the extension:

..  code-block:: bash
    :caption: Activate the extension

    vendor/bin/typo3 extension:setup

You can also activate it through the :guilabel:`Admin Tools > Extensions`
backend module.

..  tip::
    Installing the extension is enough to use the :html:`<i:image>` ViewHelper.
    No Site Set or TypoScript is required. Configure the extension under
    :guilabel:`Settings > Extension Configuration > imaginator`
    (see :ref:`configuration`).

Signing key
===========

The signing secret is derived automatically from
:php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']`, which is set on
every standard TYPO3 install. No configuration is needed.

..  warning::
    Changing :php:`encryptionKey` invalidates previously generated image URLs.
    They re-sign on the next render, but already-cached HTML pointing at the old
    signatures will return :ref:`403 <image-endpoint>` until re-rendered. See
    :ref:`configuration-signing` for key rotation.
