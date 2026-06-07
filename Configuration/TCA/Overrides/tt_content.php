<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Content-element-level aspect ratio: applies to every image rendered by EXT:imaginator in this
// element. Empty keeps each image's original (or crop variant) ratio. A future backend element will
// extend this to per-breakpoint ratios; for now it is a single "W:H" value.
$GLOBALS['TCA']['tt_content']['columns']['tx_imaginator_aspect_ratio'] = [
    'label' => 'Imaginator aspect ratio',
    'description' => 'e.g. 16:9, 4:3, 1:1 — applied to all images in this element. Empty = original / crop ratio.',
    'config' => [
        'type' => 'input',
        'size' => 10,
        'max' => 32,
        'eval' => 'trim',
        'placeholder' => 'e.g. 16:9',
        'valuePicker' => [
            'items' => [
                ['label' => '1:1', 'value' => '1:1'],
                ['label' => '4:3', 'value' => '4:3'],
                ['label' => '3:2', 'value' => '3:2'],
                ['label' => '16:9', 'value' => '16:9'],
                ['label' => '21:9', 'value' => '21:9'],
            ],
        ],
    ],
];

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'tx_imaginator_aspect_ratio',
    '',
    'after:imageorient',
);
