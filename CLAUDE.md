# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

This repo is **pre-code**: it currently contains only design docs in `docs/` and has no
commits yet. The work is a TYPO3 extension, **`schliesser/imaginator`** (TER key `imaginator`,
PHP namespace `Schliesser\Imaginator\`, new major `^2.0`, successor to the old `pictureino`).

The implementation is meant to be built by following the plans in `docs/` task-by-task with TDD.
Before writing any code, read:
- `docs/2026-06-06-pictureino-ng.md` — the architecture/design RFC. **§12 holds the locked decisions**;
  everything else flows from them. Treat the locked decisions as binding constraints.
- `docs/2026-06-06-imaginator-v1-foundation.md` — the first implementation plan (Tasks 0–10). It contains
  the exact `composer.json`, file layout, and full code/tests for the foundation. **Start here.**
- The remaining `docs/2026-06-06-imaginator-v1-*.md` are independent follow-on plans (formats+LQIP,
  JS enhancement, external providers, aspect-ratio element, polish) — build only after the foundation.

## How to work in this repo

The plans are written for **strict TDD** and are not optional process: each task is
write-failing-test → run (verify FAIL) → minimal implementation → run (verify PASS) → commit.
Follow the steps in order and commit at the boundaries the plan specifies — do not batch tasks
together or skip the failing-test step. Use the `superpowers:subagent-driven-development` or
`superpowers:executing-plans` skill to drive execution (the foundation plan names these explicitly).

## Commands

Once Task 0 has scaffolded the extension (`composer.json`, `Build/phpunit/*.xml`), dependencies install
into `.Build/` (a Composer `vendor-dir`, not the default `vendor/`):

```bash
composer update                                          # installs into .Build/
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml        # all unit tests (pure PHP, fast)
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Url/SignedUrlBuilderTest.php   # one test file
.Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml  # functional tests (boot TYPO3 + DB)
```

Dev tooling pinned in `require-dev`: `phpstan/phpstan ^2.1`, `friendsofphp/php-cs-fixer ^3.51`.

## Test layering (important for speed)

Tests split into two suites by what they need to boot:
- **`Tests/Unit/`** — pure PHPUnit `TestCase`, no TYPO3 bootstrap. The signing + ladder core
  (`Dto/AspectRatio`, `Ladder/*`, `Url/CanonicalParams`, `Url/SignedUrlBuilder`, `Dto/ImageVariant`)
  is deliberately framework-free so it runs fast here. Keep new pure logic unit-testable.
- **`Tests/Functional/`** — `typo3/testing-framework`, boots TYPO3 + DB. Used for anything touching FAL,
  the middleware, ViewHelpers, or GraphicsMagick processing. Renderer output is verified with
  **golden-file** tests (inject a fake processor returning predictable URLs, assert exact HTML).

## Architecture (the big picture)

The extension renders **zero-config responsive images**: the editor/developer writes no `sizes` and
no per-image breakpoints. At page-render time it emits a real `<picture>`/`<img>` with a quantized
**width-ladder** `srcset` + `sizes="auto"`, so the browser's preload scanner fetches the correctly-sized
image in one round-trip. JS is progressive enhancement only — never load-bearing for sharpness.

Data flow and the seams that hold it together:

1. **Width ladder** (`Ladder/LadderFactory`) — turns a configured rung list + an `AspectRatio` +
   the source width into capped, deduped `Rung{width,height}`s. `nearestRung()` quantizes an arbitrary
   width UP to a rung. Quantization is what bounds the set of processed files **and** what makes signed
   URLs DoS-safe (only rung sizes ever verify).

2. **Signed URLs** (`Url/CanonicalParams` + `Url/SignedUrlBuilder`) — the candidate URL *is* the image:
   `/_imaginator/{16-hex-sig}/{storage}-{fileUid}/{cropVariant}/{w}x{h}.{ext}`. The signature is an
   **HMAC** (not encryption) over the deterministic `CanonicalParams::canonicalString()`. The builder
   takes a **list of secrets** — index 0 signs, all verify — to support key rotation without
   cold-caching the site. Readable params + a real file extension keep it CDN/devtools-friendly.

3. **Pluggable processing** (`Imaging/ImageProcessorInterface`: `buildUrl` / `isOffloaded` / `materialize`).
   `buildUrl()` is what goes in `srcset`. Two families share this interface:
   - **Local** (`Imaging/Local/LocalImageProcessor`) — `buildUrl()` returns the signed `/_imaginator/…`
     URL; a PSR-15 middleware (`Middleware/ProcessImageRequest`) verifies the sig, re-checks the width
     against the ladder, calls `materialize()`, and **302-redirects to the processed FAL file** with
     immutable cache headers. Bad/forged signature → **403**. No JSON anywhere. v1 local backend is
     **GraphicsMagick only**, behind `Local/Backend/LocalBackendInterface` so libvips can drop in for v2.
   - **External** (follow-on plan) — `isOffloaded() === true`; `buildUrl()` maps the variant onto a
     provider's URL grammar (Thumbor, imgproxy, imgix, Cloudflare Images, Cloudinary) and points
     `srcset` straight at the provider. The webserver never touches pixels.

4. **Rendering** (`Rendering/PictureRenderer` ← `ViewHelpers/ImageViewHelper`) — single ratio → `<img>`
   with the ladder; multiple per-breakpoint ratios → `<picture>` with one `<source media>` per breakpoint,
   each its own ladder. `width`/`height` from the largest rung guarantees zero CLS. `priority` images
   drop `loading=lazy`, get `fetchpriority="high"` + explicit `sizes`, and (later) a preload `<head>` link.

5. **Configuration** (`Configuration/Settings` reading **Site-Set settings** under `imaginator.*`):
   ladder rungs, `maxDimension`, signing secrets (derived from `encryptionKey` + optional rotation list),
   formats, qualities, processor selection. Pure parsing parts stay unit-testable.

### Key invariants when changing things
- The render layer is **processor-agnostic**: the same HTML is emitted regardless of who does the pixels.
  Don't leak local-vs-external assumptions into `PictureRenderer`/`ImageViewHelper`.
- `CanonicalParams`' field set and order must stay identical everywhere it's constructed
  (`SignedUrlBuilder`, `ImageVariant::toCanonicalParams()`, the middleware's verify path) — the signature
  depends on it byte-for-byte.
- Only rung-quantized widths may be signed/served; never let an arbitrary requested `w×h` reach the
  processor (DoS surface).
- Supported floor is **PHP 8.3 / TYPO3 13.4**, test matrix PHP 8.3/8.4/8.5 and TYPO3 13.4 + 14.x.
  Keep code dual-version-clean (no APIs exclusive to one major without a guard/adapter).
