<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Content-element-level per-breakpoint aspect ratios: applies to every image rendered by
// EXT:imaginator in this element. Stored as a `{"<breakpoint>": "<ratio>"}` JSON map; a breakpoint
// absent (or `auto`) inherits the next-smaller ratio. Edited via the custom `imaginatorAspectRatios`
// FormEngine web-component element. `allowedRatios` lists the offered ratios and can be narrowed per
// CType via `types[...].columnsOverrides`.
$GLOBALS['TCA']['tt_content']['columns']['tx_imaginator_aspect_ratios'] = [
    'label' => 'Imaginator aspect ratios',
    'description' => 'Per-breakpoint aspect ratios applied to all images in this element. Empty = original / crop ratio.',
    'config' => [
        'type' => 'user',
        'renderType' => 'imaginatorAspectRatios',
        'allowedRatios' => '1:1,4:3,3:2,16:9,21:9,9:16,2:3,3:4',
    ],
];

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'tx_imaginator_aspect_ratios',
    '',
    'after:imageorient',
);
