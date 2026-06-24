<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Service;

use Schliesser\Imaginator\Dto\BreakpointRatio;

/**
 * Expands every fixed-height tier into per-DPR `<source>` variants gated by `min-resolution`, so a
 * full-bleed hero serves its pinned height at the device's real pixel ratio: 1x = H, 2x = 2H, … up
 * to the configured cap. Width stays a `w`-descriptor ladder inside each variant, so a low-DPR
 * screen matches no `min-resolution` source and gets the cheap 1x height, while a high-DPR screen
 * matches a gated source and stays crisp.
 *
 * Ratio tiers pass through untouched. Variants are emitted most-restrictive-first (highest DPR
 * first), in place, so the surrounding `<picture>` order (largest min-width first) is preserved.
 */
final class DprTierExpander
{
    /**
     * @param BreakpointRatio[] $tiers
     * @return BreakpointRatio[]
     */
    public function expand(array $tiers, int $dprCap): array
    {
        $out = [];
        foreach ($tiers as $tier) {
            if ($tier->fixedHeight === null || $dprCap <= 1) {
                $out[] = $tier;
                continue;
            }
            for ($k = $dprCap; $k >= 2; $k--) {
                $out[] = new BreakpointRatio(
                    media: $this->combine($tier->media, $this->resolutionClause($k)),
                    fixedHeight: $k * $tier->fixedHeight,
                    resolutionGated: true,
                );
            }
            $out[] = new BreakpointRatio(media: $tier->media, fixedHeight: $tier->fixedHeight);
        }

        return $out;
    }

    /** Tier k matches devices at >= (k - 0.5) dppx; k=2 -> 1.5dppx, k=3 -> 2.5dppx. */
    private function resolutionClause(int $k): string
    {
        return '(min-resolution:' . ($k - 1) . '.5dppx)';
    }

    private function combine(?string $media, string $clause): string
    {
        return $media === null ? $clause : $media . ' and ' . $clause;
    }
}
