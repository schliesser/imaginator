# Imaginator v1 — Formats & LQIP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Emit AVIF + WebP + original `<picture>` tiers (each a full ladder) and a low-quality
placeholder (ThumbHash default, dominant-color and none as options) so the first paint is sharp AND
flash-free.

**Architecture:** Extend `PictureRenderer` (foundation Task 9) to loop *formats × rungs* into stacked
`<source type>` tiers. Add a pluggable `LqipGeneratorInterface` rendered as a background on the `<img>`.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14, `srwiez/thumbhash`, PHPUnit + typo3/testing-framework.

**Depends on:** `2026-06-06-imaginator-v1-foundation.md` (PictureRenderer, ImageVariant, processor, Settings).

---

## File Structure
- `Classes/Rendering/PictureRenderer.php` — extend with format tiers (modify).
- `Classes/Lqip/LqipGeneratorInterface.php` — `generate(FileInterface $file): ?string` (data-URI or `#rrggbb`).
- `Classes/Lqip/ThumbHashGenerator.php`, `Classes/Lqip/DominantColorGenerator.php`, `Classes/Lqip/NullLqipGenerator.php`
- `Classes/Lqip/LqipGeneratorFactory.php` — pick implementation from `imaginator.lqip` setting.

---

## Task 1: Format tiers in PictureRenderer

**Files:**
- Modify: `Classes/Rendering/PictureRenderer.php`
- Test: `Tests/Unit/Rendering/PictureRendererFormatsTest.php` (pure; fake processor returns
  `"/url/{format}/{w}x{h}"`)

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
// (uses a FakeProcessor + minimal Rung list; assert exact <picture> output)

final class PictureRendererFormatsTest extends TestCase
{
    public function testStacksAvifThenWebpThenFallbackImg(): void
    {
        $html = $this->render(formats: ['avif', 'webp'], rungs: [[320, 180], [640, 360]]);

        // AVIF source first, then WebP, then <img> fallback (original ext).
        self::assertStringContainsString(
            '<source type="image/avif" srcset="/url/avif/320x180 320w, /url/avif/640x360 640w" sizes="auto">',
            $html
        );
        self::assertStringContainsString(
            '<source type="image/webp" srcset="/url/webp/320x180 320w, /url/webp/640x360 640w" sizes="auto">',
            $html
        );
        self::assertMatchesRegularExpression('#<img [^>]*srcset="/url/jpeg/320x180 320w, /url/jpeg/640x360 640w"#', $html);
        // Tier order: avif source index < webp source index < img.
        self::assertLessThan(strpos($html, 'image/webp'), strpos($html, 'image/avif'));
    }
}
```

- [ ] **Step 2: Run → FAIL** (`-c Build/phpunit/UnitTests.xml Tests/Unit/Rendering/PictureRendererFormatsTest.php`).

- [ ] **Step 3: Implement**

In `PictureRenderer`, when `formats` is non-empty, always emit a `<picture>`: for each configured
format (in order) emit one `<source type="image/{format}">` whose `srcset` is the ladder rendered in
that format (call `processor->buildUrl()` per rung per format); end with an `<img>` fallback in the
original format. Keep `sizes="auto"`, `loading`, `width`/`height`, LQIP seam intact. A single ratio +
empty `formats` still yields the bare `<img>` from the foundation plan.

- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat: AVIF/WebP/original picture tiers in PictureRenderer`.

- [ ] **Step 6:** failing test combining **art-direction (multi-ratio) × formats** — each breakpoint
  `<source media>` repeated per format with its own ladder; assert ordering media-desc then format.
- [ ] **Steps 7–10:** FAIL → implement nested loop (breakpoint × format) → PASS → commit.

---

## Task 2: LqipGeneratorInterface + DominantColorGenerator

**Files:**
- Create: `Classes/Lqip/LqipGeneratorInterface.php`, `Classes/Lqip/DominantColorGenerator.php`
- Test: `Tests/Functional/Lqip/DominantColorGeneratorTest.php`

- [ ] **Step 1: Failing functional test** — given a fixture that is mostly `#8a7f6e`, `generate()`
  returns a `#rrggbb` within a small per-channel tolerance.

```php
public function testReturnsApproxDominantHex(): void
{
    $hex = $this->get(DominantColorGenerator::class)->generate($this->fixtureFile('warm.jpg'));
    self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
    [$r,$g,$b] = sscanf($hex, "#%02x%02x%02x");
    self::assertEqualsWithDelta(0x8a, $r, 24);
}
```

- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** — interface `generate(FileInterface $file): ?string`; DominantColor
  downscales the file to 1×1 via the local backend (or GD `imagescale` on the processed 16px preview),
  reads the pixel, formats `#rrggbb`.
- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat: LqipGeneratorInterface + dominant-color generator`.

---

## Task 3: ThumbHashGenerator

**Files:**
- Create: `Classes/Lqip/ThumbHashGenerator.php`
- Modify: `composer.json` (+`srwiez/thumbhash`)
- Test: `Tests/Functional/Lqip/ThumbHashGeneratorTest.php`

- [ ] **Step 1: Failing functional test** — `generate()` returns a `data:image/...;base64,` URI that
  is non-empty and decodes to a small raster (assert prefix + min length).
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** — process the source to a ≤100px RGBA preview (local backend), feed pixels
  to `srwiez/thumbhash` `ThumbHash::rgbaToHash()`, then `ThumbHash::hashToDataURL()` for a server-side
  data-URI (no client JS needed). Cache the hash on the processed-file metadata to avoid recompute.
- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat: ThumbHash LQIP generator (server-side data-URI)`.

---

## Task 4: NullLqipGenerator + factory + DI

**Files:**
- Create: `Classes/Lqip/NullLqipGenerator.php`, `Classes/Lqip/LqipGeneratorFactory.php`
- Modify: `Configuration/Services.yaml`, `Configuration/Sets/Imaginator/settings.definitions.yaml`
- Test: `Tests/Unit/Lqip/LqipGeneratorFactoryTest.php`

- [ ] **Step 1: Failing unit test** — factory returns ThumbHash for `'thumbhash'`, DominantColor for
  `'dominant-color'`, Null for `'none'`/unknown.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement** factory (constructor-injected map of generators);
  `NullLqipGenerator::generate()` returns `null`. Add `imaginator.lqip` setting (default `thumbhash`).
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: LQIP factory + none option + settings`.

---

## Task 5: Wire LQIP into PictureRenderer

**Files:**
- Modify: `Classes/Rendering/PictureRenderer.php`
- Test: `Tests/Unit/Rendering/PictureRendererLqipTest.php`

- [ ] **Step 1: Failing test** — with a generator returning `#8a7f6e`, the `<img>` carries
  `style="background:#8a7f6e"`; with a data-URI it carries `background-image:url(...);background-size:cover`;
  with `null` no background style is added.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement** — renderer asks the injected generator once per
  image, fills the LQIP seam on the `<img>` (hex → `background`; data-URI → `background-image` cover).
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: render LQIP as img background (flash-free first paint)`.

---

## Self-Review
- **Spec coverage:** AVIF/WebP tiers (design §5) ✓ T1; ThumbHash default + dominant + none (§6) ✓ T2–4;
  flash-free LQIP behind sharp ladder ✓ T5.
- **Placeholder scan:** pure renderer/factory steps carry full assertions; image-dependent generators
  carry exact behaviour + fixture-based assertions (correct — pixel output isn't byte-stable).
- **Type consistency:** `LqipGeneratorInterface::generate(FileInterface): ?string` identical across
  T2–5; factory key strings match `settings.definitions.yaml`.
