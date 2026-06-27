import { expect, Page, test } from '@playwright/test';

/**
 * WebKit end-to-end test for the sizes="auto" enhancement, shipped because Safari/iOS historically
 * have no native sizes="auto" support. It drives the *running* demo frontend (the instance built by
 * Build/Scripts/setup-ci-instance.sh) in WebKit, the engine that matters for this feature.
 *
 * What it guards is the *behavioural outcome*, not the mechanism: in WebKit, sizes must be **resolved
 * to the laid-out box width**, so a fractional-width image fetches a right-sized candidate instead of
 * the oversized one an unresolved `sizes="auto"` would fall back to (100vw → the largest rung). This
 * holds whether the browser resolves auto-sizing natively (newer Safari) or via the vendored
 * Shopify/autosizes polyfill (older Safari) — both paths must land on the small candidate. Asserting
 * the outcome keeps the test correct across WebKit versions rather than pinned to one mechanism.
 *
 * A full-width image cannot discriminate (auto ≈ 100vw), so this targets the "Four in a row" demo
 * section where each image is ~25vw — there an unresolved fallback would over-fetch ~4×.
 *
 * Configure via env: IMAGINATOR_BASE_URL.
 */

/** First lazy <img> in the "Four in a row" demo grid (each laid out at ~25vw). */
function quarterWidthImg(page: Page) {
  return page
    .locator('section')
    .filter({ has: page.getByRole('heading', { name: /Four in a row/ }) })
    .locator('img')
    .first();
}

test.describe('sizes="auto" resolves in WebKit', () => {
  test('a quarter-width image fetches a box-sized candidate, not the 100vw fallback', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/');

    // The enhancement script must reach the browser (queued by the ViewHelper via AssetCollector).
    await expect(page.locator('script[src*="frontend/autosizes.js"]')).toBeAttached();

    const img = quarterWidthImg(page);
    await expect(img).toHaveAttribute('loading', 'lazy'); // never touches priority/eager images
    await img.scrollIntoViewIfNeeded();
    await img.evaluate((el: HTMLImageElement) => el.decode().catch(() => undefined));

    const { sizes, served, needed } = await img.evaluate((el: HTMLImageElement) => {
      const cs = el.currentSrc || el.src || '';
      // imgproxy: rs:fill:W:H/... ; local signed: /{W}x{H}.ext
      const m = cs.match(/rs:fill:(\d+):(\d+)/) || cs.match(/\/(\d+)x(\d+)\.\w+(?:[?#]|$)/);
      return {
        sizes: el.sizes,
        served: m ? Number(m[1]) : null,
        needed: el.clientWidth * window.devicePixelRatio,
      };
    });

    expect(sizes).not.toBe(''); // resolved: native "auto" honoured, or a concrete px from the polyfill
    expect(served).not.toBeNull();
    // The fetched candidate matches the laid-out box (one rung up), not the 100vw worst case: at
    // ~25vw a fallback would over-fetch ~4×, so served stays well under 2× the real need.
    expect(served!).toBeGreaterThanOrEqual(needed * 0.9);
    expect(served!).toBeLessThanOrEqual(needed * 1.8);
  });
});
