<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Activate an editor-selectable focus area on the "default" crop variant of every file reference.
// EXT:imaginator's processing already honors it (CropResolver reads getFocusArea; CropCalculator
// centers the crop on it) — core just never offers a focus area to draw. Gated by the
// `imaginator.focusArea` feature toggle (default on; see ext_localconf.php).
if (empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['imaginator.focusArea'])) {
    return;
}

// Core never writes cropVariants to TCA — its default variant lives in ImageManipulationElement's
// static config and is dropped wholesale the moment any cropVariants entry exists (a variant without
// allowedAspectRatios then throws 1620147893). So mirror core's stock default verbatim, then append
// the focus area. Both writes are guarded with ??= — never clobber a variant or focus area that
// another extension or the site already configured.
$config = &$GLOBALS['TCA']['sys_file_reference']['columns']['crop']['config'];
$labels = 'LLL:EXT:core/Resources/Private/Language/locallang_wizards.xlf:imwizard';

$config['cropVariants']['default'] ??= [
    'title' => $labels . '.crop_variant.default',
    'allowedAspectRatios' => [
        '16:9' => ['title' => $labels . '.ratio.16_9', 'value' => 16 / 9],
        '3:2' => ['title' => $labels . '.ratio.3_2', 'value' => 3 / 2],
        '4:3' => ['title' => $labels . '.ratio.4_3', 'value' => 4 / 3],
        '1:1' => ['title' => $labels . '.ratio.1_1', 'value' => 1.0],
        'NaN' => ['title' => $labels . '.ratio.free', 'value' => 0.0],
    ],
    'excludeFromSync' => false,
    'selectedRatio' => 'NaN',
    'cropArea' => ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0],
];

$config['cropVariants']['default']['focusArea'] ??= [
    'x' => 1 / 3,
    'y' => 1 / 3,
    'width' => 1 / 3,
    'height' => 1 / 3,
];

unset($config, $labels);
