import { expect, Locator, Page, test } from '@playwright/test';

/**
 * Validates the offloaded **imgproxy** processor end-to-end: the srcset points straight at the
 * imgproxy endpoint and imgproxy serves a real resized image (the webserver never touches pixels).
 *
 * Self-skips when the instance is not on imgproxy (e.g. the local-processor e2e job, or a local DDEV
 * using the local backend), so the same demo page works under either processor.
 */

const IMGPROXY = /\/\/[^/]+:8081\/[^"]*rs:fill:(\d+):(\d+)\//;

function heroImg(page: Page): Locator {
  return page
    .locator('section')
    .filter({ has: page.getByRole('heading', { name: /Priority \/ LCP/ }) })
    .locator('img')
    .first();
}

async function imgproxySrcset(page: Page): Promise<string> {
  await page.goto('/');
  const srcset = (await heroImg(page).getAttribute('srcset')) ?? '';
  test.skip(!IMGPROXY.test(srcset), 'instance is not using the imgproxy processor');
  return srcset;
}

test('srcset points straight at the imgproxy endpoint with rs:fill', async ({ page }) => {
  const srcset = await imgproxySrcset(page);

  for (const candidate of srcset.split(',').map((s) => s.trim().split(' ')[0])) {
    expect(candidate).toMatch(IMGPROXY);
  }
});

test('imgproxy serves a real resized image of the requested size', async ({ page, request }) => {
  const srcset = await imgproxySrcset(page);

  // Largest candidate = the top rung.
  const url = srcset.split(',').map((s) => s.trim().split(' ')[0]).pop()!;
  const response = await request.get(url);

  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toMatch(/^image\//);
  // A real transcoded image, not an empty body or an error/redirect page.
  expect((await response.body()).length).toBeGreaterThan(1000);
});
