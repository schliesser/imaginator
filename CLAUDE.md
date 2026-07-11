# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

This is a TYPO3 extension, **`schliesser/imaginator`** (TER key `imaginator`, PHP namespace
`Schliesser\Imaginator\`, first release targeting `1.0`). The foundation (signing + ladder core,
processors, renderer, ViewHelper, middleware) is implemented; work now proceeds on the follow-on items.

`docs/DESIGN.md` is the source of truth for *intent* — architecture, data flow, and the **locked
decisions** (treat those as binding constraints). The code is the source of truth for *behavior*.

## How to work in this repo

Work **TDD**: write-failing-test → run (verify FAIL) → minimal implementation → run (verify PASS) →
commit. Don't batch unrelated changes or skip the failing-test step; commit at logical boundaries.

## Commands

Dependencies install into `.Build/` (a Composer `vendor-dir`, not the default `vendor/`):

```bash
composer update                                          # installs into .Build/
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml        # all unit tests (pure PHP, fast)
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/UrlBuilder/LocalAsyncUrlBuilderTest.php   # one test file
.Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml  # functional tests (boot TYPO3 + DB)
```

Functional tests that exercise real image processing use **ImageMagick** via TYPO3's `ImageService`
(shared GFX config in the `UsesImageProcessing` trait). The binary path defaults to `/usr/bin/` (CI);
point it elsewhere locally, e.g. `typo3ProcessorPath=/opt/homebrew/bin/`. Booting TYPO3 can exceed the
default CLI `memory_limit` — run with `php -d memory_limit=1G` if a bootstrap OOM appears.

Dev tooling pinned in `require-dev`: `phpstan/phpstan ^2.1`, `friendsofphp/php-cs-fixer ^3.51`.

### DDEV testbed

The committed `.ddev/` config (nginx-fpm, PHP 8.3, MariaDB, imgproxy addon) provides a clickable
sandbox. Custom commands wrap the workflows — after a fresh clone, `ddev start` then:

```bash
ddev setup              # composer install into .Build/
ddev demo               # (re)build the default demo at https://imaginator.ddev.site/ (.Build/, current version)
ddev install-v13        # clickable TYPO3 v13 demo at https://v13.imaginator.ddev.site/  (--fresh to rebuild)
ddev install-v14        # clickable TYPO3 v14 demo at https://v14.imaginator.ddev.site/
ddev install-all        # both side-by-side
ddev test [unit|functional|all]   # PHPUnit suites
ddev lint               # PHPStan + php-cs-fixer (dry-run); `ddev cgl-fix` auto-fixes
```

Backend login on every instance: `admin` / `Password.1`. The `v13`/`v14` instances live in Docker
volumes (`/var/www/html/v13|v14`, served by `.ddev/nginx_full/v13.conf|v14.conf`), each a full TYPO3
base-distribution with EXT:imaginator wired via a Composer path repository to the working tree — edits
to `Classes/` reflect live. The shared seeder is `Build/Scripts/setup-demo-instance.sh` (path/version
parameterised); `setup-version-instance.sh` drives the per-version scaffold. The demo content + the
`schliesser/imaginator-demo` Site Set render `Resources/Private/Demo/Templates/Home.html`.

## Test layering (important for speed)

Tests split into two suites by what they need to boot:
- **`Tests/Unit/`** — pure PHPUnit `TestCase`, no TYPO3 bootstrap. The signing + ladder core
  (`Dto/AspectRatio`, `Ladder/*`, `Dto/CanonicalParams`, `UrlBuilder/LocalAsyncUrlBuilder`, `Dto/ImageVariant`)
  is deliberately framework-free so it runs fast here. Keep new pure logic unit-testable.
- **`Tests/Functional/`** — `typo3/testing-framework`, boots TYPO3 + DB. Used for anything touching FAL,
  the middleware, ViewHelpers, or `ImageService` processing. Renderer output is verified with
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

2. **Signed URLs** (`Dto/CanonicalParams` + `UrlBuilder/LocalAsyncUrlBuilder`) — the candidate URL *is* the image:
   `/_imaginator/{16-hex-sig}/{storage}-{fileUid}/{cropVariant}/{w}x{h}.{ext}`. The signature is an
   **HMAC** (not encryption) over the deterministic `CanonicalParams::canonicalString()`. The secret
   is derived from the global `encryptionKey`; changing the key requires a frontend-cache flush so
   re-rendered HTML carries fresh signatures. Readable params + a real file extension keep it
   CDN/devtools-friendly.

3. **Pluggable processing** (`Imaging/ImageProcessorInterface`: `buildUrl` / `isOffloaded` / `materialize`).
   `buildUrl()` is what goes in `srcset`. **One shared interface** — every processor implements it and
   the render layer only ever sees it. Processors are selected by the `processor` setting through the
   `Imaging/ImageProcessorRegistry` (a lazy DI service locator over services tagged
   `imaginator.image_processor` with a `key`). **Integrators add their own** purely by tagging a service
   with a new `key` and selecting it via `processor` — no core change. Built-ins:
   - **Local — `local:async`** (`Imaging/Local/LocalImageProcessor`) — `buildUrl()` returns the signed
     `/_imaginator/…` URL **only while the derivative is cold**; once it exists, a read-only
     `sys_file_processedfile` probe (sharing `materialize()`'s exact instructions, so the checksum
     matches) makes `buildUrl()` emit the **static `_processed_/…` URL directly**, so a warm image skips
     the middleware + 302 altogether. For cold URLs a PSR-15 middleware (`Middleware/ProcessImageRequest`)
     verifies the sig, re-checks the width against the ladder, calls `materialize()`, and **302-redirects
     to the processed FAL file** with immutable cache headers. Bad/forged signature → **403**. No JSON anywhere.
     (Signing is used on this path only — `local:sync` and external processors never sign.)
   - **Local — `local:sync`** (`Imaging/Local/LocalSyncImageProcessor`) — `buildUrl()` materializes the
     variant synchronously at render time and writes the **static `_processed_/…` file URL straight into
     `srcset`**; the signed endpoint + middleware are never involved, so requests are plain static-file
     serves with no PHP hop. Trade-off: higher cold-render cost (one processing op per rung per
     breakpoint), warm renders reuse existing derivatives.
   - Both local modes drive **TYPO3's `ImageService` exclusively** — no direct GraphicsMagick/ImageMagick/GD
     calls. The actual binary is whatever TYPO3's GFX config selects (GraphicsMagick by default).
   - **External — `imgproxy`** (`Imaging/External/ExternalImageProcessor`, built by `ImgproxyProcessorFactory`)
     — `isOffloaded() === true`; `buildUrl()` maps the variant onto the provider's URL grammar and points
     `srcset` straight at the provider. The webserver never touches pixels. More providers (Thumbor, imgix,
     Cloudflare Images, Cloudinary) plug in as additional URL builders.

4. **Rendering** (`Rendering/PictureRenderer` ← `ViewHelpers/ImageViewHelper`) — single ratio → `<img>`
   with the ladder; multiple per-breakpoint ratios → `<picture>` with one `<source media>` per breakpoint,
   each its own ladder. `width`/`height` from the largest rung guarantees zero CLS. `priority` images
   drop `loading=lazy`, get `fetchpriority="high"` + explicit `sizes`, and (later) a preload `<head>` link.

5. **Configuration** (`Configuration/Settings` reading **Site-Set settings** under `imaginator.*`):
   ladder rungs, `maxDimension`, the signing secret (derived from `encryptionKey`),
   formats, qualities, processor selection. Pure parsing parts stay unit-testable.

### Key invariants when changing things
- The render layer is **processor-agnostic**: the same HTML is emitted regardless of who does the pixels.
  Don't leak local-vs-external assumptions into `PictureRenderer`/`ImageViewHelper`.
- `CanonicalParams`' field set and order must stay identical everywhere it's constructed
  (`LocalAsyncUrlBuilder`, `ImageVariant::toCanonicalParams()`, the middleware's verify path) — the signature
  depends on it byte-for-byte.
- Only rung-quantized widths may be signed/served; never let an arbitrary requested `w×h` reach the
  processor (DoS surface).
- Supported floor is **PHP 8.3 / TYPO3 13.4**, test matrix PHP 8.3/8.4/8.5 and TYPO3 13.4 + 14.x.
  Keep code dual-version-clean (no APIs exclusive to one major without a guard/adapter).
