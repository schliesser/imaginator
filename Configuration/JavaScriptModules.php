<?php

declare(strict_types=1);

/**
 * Backend ES modules served via importmap (TYPO3 v13/v14). Maps the `@schliesser/imaginator/`
 * specifier to the extension's public JavaScript so the FormEngine element can `import` the
 * aspect-ratios web component.
 */
return [
    'dependencies' => ['backend'],
    'imports' => [
        '@schliesser/imaginator/' => 'EXT:imaginator/Resources/Public/JavaScript/',
    ],
];
