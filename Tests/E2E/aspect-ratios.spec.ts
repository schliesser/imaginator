import { expect, FrameLocator, Page, test } from '@playwright/test';

const BE_USER = process.env.IMAGINATOR_BE_USER ?? 'admin';
const BE_PASS = process.env.IMAGINATOR_BE_PASS ?? 'Password.1';

/** The hero demo content element seeded with xs 1:1 -> md 4:3 -> lg 16:9. */
const CE_LABEL = /id=2 - Per-breakpoint: xs 1:1/;

async function login(page: Page): Promise<void> {
  await page.goto('/typo3/');
  await page.getByRole('textbox', { name: 'Username' }).fill(BE_USER);
  await page.getByRole('textbox', { name: 'Password' }).fill(BE_PASS);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/typo3\/module\//);
}

/** Opens the demo content element's edit form and returns the modal iframe locator. */
async function openAspectRatioField(page: Page): Promise<FrameLocator> {
  await page.goto('/typo3/module/web/layout?id=1');
  const list = page.frameLocator('iframe[name="list_frame"]');
  await list.getByLabel(CE_LABEL).getByRole('button', { name: 'Edit' }).click();

  const modal = page.frameLocator('iframe[name="modal_frame"]');
  // The field sits on the "Media" tab (added after imageorient); it is hidden until that tab is open.
  await modal.getByRole('tab', { name: 'Media' }).click();
  await modal.locator('imaginator-aspect-ratios .imaginator-ar-row').first().waitFor({ state: 'visible' });
  return modal;
}

const rowSelect = (modal: FrameLocator, breakpoint: string) =>
  modal.locator(`.imaginator-ar-row[data-breakpoint="${breakpoint}"] select`);

const rowSwatch = (modal: FrameLocator, breakpoint: string) =>
  modal.locator(`.imaginator-ar-row[data-breakpoint="${breakpoint}"] .imaginator-ar-swatch`);

const hiddenInput = (modal: FrameLocator) =>
  modal.locator('imaginator-aspect-ratios input[type="hidden"]');

test.beforeEach(async ({ page }) => {
  await login(page);
});

test('renders one styled select per breakpoint', async ({ page }) => {
  const modal = await openAspectRatioField(page);

  await expect(modal.locator('.imaginator-ar-row')).toHaveCount(5);
  // Uses the TYPO3/Bootstrap select styling, not a raw browser dropdown.
  await expect(rowSelect(modal, 'xs')).toHaveClass(/form-select/);
});

test('changing a breakpoint serializes JSON and marks the field dirty', async ({ page }) => {
  const modal = await openAspectRatioField(page);
  const input = hiddenInput(modal);

  await expect(input).not.toHaveClass(/has-change/);

  await rowSelect(modal, 'lg').selectOption('21:9');

  await expect(input).toHaveClass(/has-change/);
  await expect(input).toHaveValue(/"lg":"21:9"/);
});

test('an inherit row previews the inherited ratio', async ({ page }) => {
  const modal = await openAspectRatioField(page);

  // xl is "inherit" and bubbles up lg's 16:9 -> shaped, muted preview (not the empty hatch).
  const xlSwatch = rowSwatch(modal, 'xl');
  await expect(xlSwatch).toHaveClass(/imaginator-ar-swatch--inherited/);
  await expect(xlSwatch).toHaveCSS('aspect-ratio', '16 / 9');
});

test('closing after a change warns about unsaved changes', async ({ page }) => {
  const modal = await openAspectRatioField(page);

  await rowSelect(modal, 'lg').selectOption('21:9');
  await modal.getByRole('button', { name: 'Close' }).click();

  // FormEngine's unsaved-changes guard fires because the field is now dirty.
  await expect(page.getByText('unsaved changes which will be discarded')).toBeVisible();

  // Leave the instance clean.
  await page.getByRole('button', { name: 'Discard changes' }).click();
});
