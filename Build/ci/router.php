<?php

declare(strict_types=1);

// Router for PHP's built-in server (CI e2e job):
//   php -S 127.0.0.1:8080 -t .Build/public Build/ci/router.php
// Existing files (the local processor's materialised images under fileadmin/_processed_, _assets,
// the importmap, etc.) are served statically; every other path is routed through TYPO3's front
// controller so page + signed /_imaginator/ requests work.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../../.Build/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server serve the static file
}

require __DIR__ . '/../../.Build/public/index.php';
