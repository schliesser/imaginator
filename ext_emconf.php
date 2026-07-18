<?php

// Kept for classic (non-Composer) installs and full TYPO3 v13 compatibility. v14.3 deprecates this
// file for Composer-based extensions (changelog 108345); that deprecation is accepted on purpose —
// the Composer metadata lives in composer.json's extra.typo3/cms section as well.
$EM_CONF[$_EXTKEY] = [
    'title' => 'Imaginator',
    'description' => 'Zero-config responsive images for TYPO3: signed srcset ladders, local or external processing.',
    'category' => 'fe',
    'author' => 'André Buchmann',
    'author_email' => 'andy.schliesser@gmail.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.3.0-8.5.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [
            'pictureino' => '',
        ],
        'suggests' => [],
    ],
];
