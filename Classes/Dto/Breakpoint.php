<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * One design-system breakpoint: a key (e.g. `lg`) and its min-width in px. The min-0 breakpoint
 * (`xs`) is the base ratio that bubbles up to larger viewports until a larger breakpoint overrides
 * it. Sourced once from {@see \Schliesser\Imaginator\Configuration\Settings::$breakpoints}.
 */
final readonly class Breakpoint
{
    public function __construct(
        public string $key,
        public int $minWidth,
    ) {}
}
