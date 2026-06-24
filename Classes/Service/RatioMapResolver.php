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
        if (!is_array($decoded)) {
            return [];
        }

        return $this->fromMap($decoded, $breakpoints);
    }

    /**
     * Resolve a `{breakpoint: ratio}` map into {@see BreakpointRatio[]}. Each key is either a
     * configured breakpoint alias (`md`) or an explicit min-width in px (`768`, key `0` = base);
     * unknown aliases and `auto`/unparsable ratios are dropped. Result is largest-first so the
     * `<picture>` emits `<source media>` tiers before the base `<img>` (min-width 0 → media null).
     *
     * @param array<array-key, mixed> $map
     * @param Breakpoint[]            $breakpoints
     * @return BreakpointRatio[]
     */
    public function fromMap(array $map, array $breakpoints): array
    {
        $aliasMinWidths = [];
        foreach ($breakpoints as $breakpoint) {
            $aliasMinWidths[$breakpoint->key] = $breakpoint->minWidth;
        }

        // Collect keyed by min-width so an alias and its numeric equivalent collapse to one tier.
        // Value is the parsed spec: an AspectRatio or an int fixed height in px.
        $byMinWidth = [];
        foreach ($map as $key => $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $minWidth = $this->resolveMinWidth($key, $aliasMinWidths);
            if ($minWidth === null) {
                continue;
            }
            $spec = $this->parseSpec($raw);
            if ($spec === null) {
                continue;
            }
            $byMinWidth[$minWidth] = $spec;
        }

        // Largest-first; the min-0 tier becomes the base <img> (media null).
        krsort($byMinWidth);

        $result = [];
        foreach ($byMinWidth as $minWidth => $spec) {
            $media = $minWidth === 0 ? null : '(min-width:' . $minWidth . 'px)';
            $result[] = $spec instanceof AspectRatio
                ? new BreakpointRatio($spec, $media)
                : new BreakpointRatio(media: $media, fixedHeight: $spec);
        }

        return $result;
    }

    /**
     * A numeric key is a literal min-width in px; otherwise it must match a configured alias.
     *
     * @param array<string, int> $aliasMinWidths
     */
    private function resolveMinWidth(int|string $key, array $aliasMinWidths): ?int
    {
        if (is_int($key) || preg_match('/^\d+$/', $key) === 1) {
            return max(0, (int) $key);
        }

        return $aliasMinWidths[$key] ?? null;
    }

    /**
     * Parse a single tier value: a `W:H` aspect ratio, an `Npx`/`N` fixed height in px, or null for
     * an empty/`auto` tier (which is skipped so native `<picture>` inheritance carries the smaller
     * ratio up). Any other non-empty token is a malformed entry and throws.
     *
     * @return AspectRatio|int|null int = fixed height in px
     */
    public function parseSpec(string $value): AspectRatio|int|null
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'auto') {
            return null;
        }
        if (preg_match('/^(\d+)(?:px)?$/', $value, $m) === 1) {
            return (int) $m[1];
        }

        // Throws InvalidArgumentException on anything that is not a valid `W:H` ratio.
        return AspectRatio::fromString($value);
    }
}
