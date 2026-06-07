<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Service;

use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\Breakpoint;
use Schliesser\Imaginator\Dto\BreakpointRatio;

/**
 * Turns the content-element's `{breakpoint: ratio}` JSON into the {@see BreakpointRatio[]} the
 * {@see \Schliesser\Imaginator\Rendering\PictureRenderer} consumes — ordered largest-first so the
 * `<picture>` emits `<source media>` tiers before the base `<img>`.
 *
 * A breakpoint absent from the JSON (or set to `auto`/an unparsable ratio) is omitted, so the
 * native `<picture>` inheritance carries the next-smaller ratio up. The min-0 breakpoint becomes
 * the base entry (`media: null`).
 */
final class RatioMapResolver
{
    /**
     * @param Breakpoint[] $breakpoints
     * @return BreakpointRatio[]
     */
    public function fromJson(string $json, array $breakpoints): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || $decoded === []) {
            return [];
        }

        // Sort breakpoints largest-first so the <picture> emits <source media> tiers before the
        // base <img>; the min-0 breakpoint becomes the base (media null).
        $ordered = $breakpoints;
        usort($ordered, static fn (Breakpoint $a, Breakpoint $b): int => $b->minWidth <=> $a->minWidth);

        $result = [];
        foreach ($ordered as $breakpoint) {
            $raw = $decoded[$breakpoint->key] ?? null;
            if (!is_string($raw)) {
                continue;
            }
            $ratio = $this->parseRatio($raw);
            if ($ratio === null) {
                continue;
            }
            $result[] = new BreakpointRatio(
                $ratio,
                $breakpoint->minWidth === 0 ? null : '(min-width:' . $breakpoint->minWidth . 'px)',
            );
        }

        return $result;
    }

    private function parseRatio(string $value): ?AspectRatio
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'auto') {
            return null;
        }
        try {
            return AspectRatio::fromString($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
