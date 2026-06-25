import { expect, Locator, Page, test } from '@playwright/test';

/**
 * Frontend end-to-end tests for the rendered <picture>. They drive a *running* demo frontend (the
 * instance built by Build/Scripts/setup-demo-instance.sh), exercising the parts no PHP test can
 * reach: how a real browser sizes the box across breakpoints and which candidate it fetches per DPR.
 *
 * These guard two bugs that only surface in a browser:
 *  - art direction: a <picture> must reshape the box per breakpoint (needs width/height per <source>);
 *  - fixed-height heroes: the served height must follow the device pixel ratio (min-resolution tiers).
 *
 * Configure via env: IMAGINATOR_BASE_URL.
 */

/** The <img> inside the demo section whose heading matches. */
function sectionImg(page: Page, heading: RegExp): Locator {
  return page
    .locator('section')
    .filter({ has: page.getByRole('heading', { name: heading }) })
    .locator('img')
    .first();
}

interface Metrics {
  boxRatio: number;
  served: { w: number; h: number } | null;
  fetchpriority: string | null;
  loading: string | null;
}

/** Scroll a (lazy) image into view, wait for it to decode, then read its rendered + served sizes. */
async function metrics(img: Locator): Promise<Metrics> {
  await img.scrollIntoViewIfNeeded();
  await img.evaluate((el: HTMLImageElement) => el.decode().catch(() => undefined));
  return img.evaluate((el: HTMLImageElement) => {
    const cs = el.currentSrc || el.src || '';
    // imgproxy: rs:fill:W:H/... ; local signed: /{W}x{H}.ext
    const m = cs.match(/rs:fill:(\d+):(\d+)/) || cs.match(/\/(\d+)x(\d+)\.\w+(?:[?#]|$)/);
    return {
      boxRatio: el.clientWidth / el.clientHeight,
      served: m ? { w: Number(m[1]), h: Number(m[2]) } : null,
      fetchpriority: el.getAttribute('fetchpriority'),
      loading: el.getAttribute('loading'),
    };
  });
}

test.describe('art direction reshapes the box per breakpoint', () => {
  // The "Art direction" demo image is template-driven (no DB): (min-width:992px) 16:9, else 1:1.
  test('a wide viewport renders the 16:9 source box', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto('/');

    const { boxRatio } = await metrics(sectionImg(page, /Art direction/));

    expect(boxRatio).toBeCloseTo(16 / 9, 1); // 1.78, not the 1:1 base
  });

  test('a narrow viewport renders the 1:1 source box', async ({ page }) => {
    await page.setViewportSize({ width: 800, height: 900 });
    await page.goto('/');

    const { boxRatio } = await metrics(sectionImg(page, /Art direction/));

    expect(boxRatio).toBeCloseTo(1, 1);
  });
});

// A full-bleed hero (aspectRatio="600px") must serve its pinned height *per device pixel ratio*:
// 1x -> 600, 2x -> 1200, 3x -> 1800. Read the requested rs:fill height, not naturalWidth (which is
// density-corrected for srcset w-descriptors).
for (const { dpr, height } of [
  { dpr: 1, height: 600 },
  { dpr: 2, height: 1200 },
  { dpr: 3, height: 1800 },
]) {
  test.describe(`fixed-height hero at DPR ${dpr}`, () => {
    test.use({ deviceScaleFactor: dpr });

    test(`serves a ${height}px-tall crop`, async ({ page }) => {
      await page.setViewportSize({ width: 1400, height: 900 });
      await page.goto('/');

      const { served } = await metrics(sectionImg(page, /Full-bleed hero/));

      expect(served).not.toBeNull();
      expect(served!.h).toBe(height);
    });
  });
}

test.describe('priority / LCP image', () => {
  test('is eager with fetchpriority and a head preload', async ({ page }) => {
    await page.goto('/');

    const img = sectionImg(page, /Priority \/ LCP/);
    await expect(img).toHaveAttribute('fetchpriority', 'high');
    await expect(img).toHaveAttribute('loading', 'eager');
    await expect(page.locator('head link[rel="preload"][as="image"]').first()).toBeAttached();
  });
});
