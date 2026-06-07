<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Backend\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;

/**
 * Custom FormEngine element for per-breakpoint aspect ratios at the content-element level. Renders a
 * web-component host (`<imaginator-aspect-ratios>`) plus a hidden input holding the `{bp: ratio}`
 * JSON. The real rendering is added in Task 4.
 */
final class AspectRatiosElement extends AbstractFormElement
{
    public function render(): array
    {
        return $this->initializeResultArray();
    }
}
