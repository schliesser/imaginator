<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$GLOBALS['TCA']['tt_content']['columns']['aspect_ratio'] = [
    'label' => 'LLL:EXT:imaginator/Resources/Private/Language/locallang_db.xlf:tt_content.aspect_ratio',
    'description' => 'LLL:EXT:imaginator/Resources/Private/Language/locallang_db.xlf:tt_content.aspect_ratio.description',
    'config' => [
        'type' => 'user',
        'renderType' => 'imaginatorAspectRatios',
        'allowedRatios' => '1:1,4:3,3:2,16:9,21:9,9:16,2:3,3:4',
    ],
];

ExtensionManagementUtility::addFieldsToPalette(
    'tt_content',
    'mediaAdjustments',
    '--linebreak--,aspect_ratio',
    'after:imageborder',
);
