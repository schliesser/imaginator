import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests for the aspect-ratios FormEngine element. They drive a *running* TYPO3 backend
 * (the demo instance built by Build/Scripts/setup-demo-instance.sh), so they are a separate suite
 * from the fast jsdom Vitest tests.
 *
 * Configure via env: IMAGINATOR_BASE_URL, IMAGINATOR_BE_USER, IMAGINATOR_BE_PASS.
 */
export default defineConfig({
  testDir: './Tests/E2E',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  use: {
    baseURL: process.env.IMAGINATOR_BASE_URL ?? 'https://imaginator.ddev.site',
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      // Use the system Chrome (set PLAYWRIGHT_CHANNEL=chromium to use a bundled browser instead).
      // The sizes="auto" polyfill spec is WebKit-only (Chrome has native support, nothing to assert).
      testIgnore: /sizes-auto-polyfill\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], channel: process.env.PLAYWRIGHT_CHANNEL ?? 'chrome' },
    },
    {
      // WebKit has no native sizes="auto" yet (Safari/iOS), so it exercises the autosizes polyfill.
      name: 'webkit',
      testMatch: /sizes-auto-polyfill\.spec\.ts/,
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
