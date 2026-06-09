<?php

declare(strict_types=1);

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1717600100] = [
    'nodeName' => AspectRatiosElement::NODE_NAME,
    'priority' => 40,
    'class' => AspectRatiosElement::class,
];
