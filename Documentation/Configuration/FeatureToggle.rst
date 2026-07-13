..  include:: /Includes.rst.txt

..  _configuration-focus-area:

==============
Feature toggle
==============

Editor focus area
=================

By default imaginator activates a **focus area** on the ``default`` crop variant
of every file reference: a movable box an editor draws to mark the part of the
image that must never be cropped away. When a rendered aspect ratio differs from
the source, the crop is centered on that box (falling back to the crop-area
center when none is set). See :ref:`image-endpoint-crop`.

Core offers no focus area out of the box, and declaring a crop variant replaces
core's implicit one, so imaginator re-supplies the stock variant (free crop plus
the standard ``16:9``/``3:2``/``4:3``/``1:1`` ratios) and appends the focus area.
An existing crop variant ``default`` configured by another extension or your
site is left untouched.

Activation is a default-on **feature toggle**, not an Extension Configuration
setting (it is read while the TCA is built). Disable it instance-wide in
:file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'] = false;
