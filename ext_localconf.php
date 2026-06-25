<?php

declare(strict_types=1);

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1717600100] = [
    'nodeName' => AspectRatiosElement::NODE_NAME,
    'priority' => 40,
    'class' => AspectRatiosElement::class,
];

// Default-on feature toggle gating the editor focus area injected in
// Configuration/TCA/Overrides/sys_file_reference.php. Runs before TCA is loaded; an admin value set
// in SYS/features (settings.php) is loaded earlier and survives the ??=. Disable with `false`.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'] ??= true;
