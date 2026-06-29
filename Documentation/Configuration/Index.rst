..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Configuration is **instance-wide Extension Configuration**. Edit it under
:guilabel:`Settings > Extension Configuration > imaginator` in the backend, or
set it in :file:`config/system/settings.php`:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = [
        'lqip' => 'dominant-color',
        'ladder' => '320,640,960,1280,1920',
    ];

..  note::
    Settings are global by design, so the render path and the signed-endpoint
    verify path always agree. A per-site model would risk mismatched ladders and
    spurious :ref:`403s <image-endpoint>`.

..  toctree::
    :maxdepth: 2
    :titlesonly:

    ExtensionConfiguration
    FeatureToggle
    Processors
    Csp
