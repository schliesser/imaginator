<?php

declare(strict_types=1);

use Schliesser\Imaginator\Middleware\ProcessImageRequest;

return [
    'frontend' => [
        'schliesser/imaginator/process-image-request' => [
            'target' => ProcessImageRequest::class,
            'after' => [
                'typo3/cms-frontend/site-resolver',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
