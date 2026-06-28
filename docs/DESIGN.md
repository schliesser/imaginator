# Imaginator — Design & Decisions

`schliesser/imaginator` · TER key `imaginator` · namespace `Schliesser\Imaginator\` ·
TYPO3 13.4 + 14.x · PHP 8.3 floor. Single source of truth for *intent*; the code is the
source of truth for *behavior*. The locked decisions below are binding constraints.

## Goal

Zero-config responsive images. The editor/developer writes no `sizes` and no per-image
breakpoints. At render time the extension emits a real `<picture>`/`<img>` with a quantized
**width-ladder** `srcset` + `sizes="auto"`, so the browser's preload scanner fetches the
correctly-sized image in one round-trip — sharp on first paint, no JSON hop. JS is progressive
enhancement only, never load-bearing for sharpness.

## Data flow

1. **Width ladder** (`Ladder/LadderFactory`) — configured rungs + an `AspectRatio` + source width
   → capped, deduped `Rung{width,height}`s. `nearestRung()` quantizes any width UP to a rung.
   Quantization bounds the processed-file set **and** makes signed URLs DoS-safe (only rung sizes verify).
2. **Signed URLs** (`Dto/CanonicalParams` + `UrlBuilder/LocalAsyncUrlBuilder`) — the candidate URL *is*
   the image: `/_imaginator/{16-hex-sig}/{storage}-{fileUid}/{cropVariant}/{w}x{h}.{ext}`. Sig =
   first 16 hex of `HMAC-SHA256(secret, CanonicalParams::canonicalString())` — HMAC, not encryption:
   cheaper, debuggable, DoS-safe. Builder takes a **list of secrets** (index 0 signs, all verify) for
   key rotation without cold-caching the site. Real extension + readable params keep it CDN/devtools-friendly.
3. **Pluggable processing** (`Imaging/ImageProcessorInterface`: `buildUrl`/`isOffloaded`/`materialize`).
   One shared interface; the render layer only ever sees it. Processors selected by the `processor`
   setting via `Imaging/ImageProcessorRegistry` (lazy DI locator over services tagged
   `imaginator.image_processor` with a `key`). **Integrators add their own** by tagging a service with a
   new `key` — no core change. Built-ins:
   - **`local:async`** (`Imaging/Local/LocalImageProcessor`) — `buildUrl()` returns the signed
     `/_imaginator/…` URL **only while the derivative is cold**; once it exists, a read-only
     `sys_file_processedfile` probe (sharing `materialize()`'s exact instructions, so the checksum
     matches) emits the static `_processed_/…` URL directly — a warm image skips the middleware + 302.
     For cold URLs the PSR-15 `Middleware/ProcessImageRequest` verifies the sig, re-checks width against
     the ladder, calls `materialize()`, and **302-redirects** to the processed FAL file with immutable
     cache headers. Bad/forged sig → **403**. No JSON anywhere.
   - **`local:sync`** (`Imaging/Local/LocalSyncImageProcessor`) — `buildUrl()` materializes synchronously
     at render time and writes the static `_processed_/…` URL straight into `srcset`; the signed endpoint +
     middleware are never involved. Higher cold-render cost, plain static serves thereafter.
   - Both local modes drive **TYPO3's `ImageService` exclusively** — no direct GraphicsMagick/ImageMagick/GD.
     Signing is used on the `local:async` path only.
   - **External — `imgproxy`** (`Imaging/External/ExternalImageProcessor` via `ImgproxyProcessorFactory`,
     URL grammar in `UrlBuilder/ImgproxyUrlBuilder`) — `isOffloaded() === true`; `buildUrl()` maps the
     variant onto the provider's URL grammar and points `srcset` at the provider. The webserver never
     touches pixels. More providers (Thumbor, imgix, Cloudflare Images, Cloudinary) plug in as
     additional `UrlBuilderInterface` implementations.
4. **Rendering** (`Rendering/PictureRenderer` ← `ViewHelpers/ImageViewHelper`) — single ratio → `<img>`
   with the ladder; multiple per-breakpoint ratios → `<picture>`, one `<source media>` per breakpoint,
   each its own ladder. `width`/`height` from the largest rung → zero CLS. `priority` images drop
   `loading=lazy`, get `fetchpriority="high"` + explicit `sizes`, and a preload `<head>` link.
   AVIF/WebP/original emitted as stacked `<source type>` tiers, format baked into the URL extension
   (no `Vary: Accept`).
5. **LQIP** (`Lqip/LqipGeneratorInterface`) — default **ThumbHash** (`ThumbHashGenerator`); alternatives
   `DominantColorGenerator` and `NullLqipGenerator`, selectable per site. Sits behind the `<img>`; the
   sharp image renders on top immediately, so the LQIP covers only the first few hundred ms of decode.
6. **Configuration** (`Configuration/Settings` ← Site-Set settings under `imaginator.*`): ladder rungs,
   `maxDimension`, signing secrets (derived from `encryptionKey` + optional rotation list), formats,
   qualities, processor selection. Pure parsing stays unit-testable.

## Locked decisions

- **URL scheme:** HMAC-signed, readable, path-segment params, real extension. Key rotation via secret
  array (sign with index 0, verify against all). Private-image (encrypted-token) mode → later.
- **Local processing:** TYPO3 `ImageService` only; two modes `local:async` (middleware + 302) and
  `local:sync` (static `srcset`). Processor selection is an open tagged registry.
- **External providers:** `imgproxy` shipped; Thumbor / imgix / Cloudflare Images / Cloudinary are
  follow-on `UrlBuilder`s behind the same interface.
- **Aspect-ratio element** (`Backend/Form/Element/AspectRatiosElement` + ContentBlocks
  `AspectRatiosFieldType`): per-breakpoint ratio at **content-element level** — the chosen ratios apply
  to all media in the CE, no per-image live preview. Stored as one structured JSON field in the
  **unprefixed `aspect_ratio`** tt_content column. Per-breakpoint distinct crop → later. Crop/focus stay
  per-file via TYPO3's native crop tool; the CE ratio selects which crop-variant/area the processor uses.
- **Versions:** TYPO3 13.4 LTS + 14.x, PHP 8.3 floor (test matrix 8.3 / 8.4 / 8.5). Keep code
  dual-version-clean — no APIs exclusive to one major without a guard/adapter.
- **LQIP default:** ThumbHash, inlined as data-URI.
- **No retina rungs:** `w`-descriptor `srcset` lets the browser multiply by DPR natively.

## Key invariants when changing things

- The render layer is **processor-agnostic**: identical HTML regardless of who does the pixels. Don't
  leak local-vs-external assumptions into `PictureRenderer`/`ImageViewHelper`.
- `CanonicalParams`' field set and order must stay identical everywhere it's constructed
  (`LocalAsyncUrlBuilder`, `ImageVariant::toCanonicalParams()`, the middleware verify path) — the
  signature depends on it byte-for-byte.
- Only rung-quantized widths may be signed/served; never let an arbitrary requested `w×h` reach the
  processor (DoS surface).

## Status & roadmap

Implemented: signing + ladder core, local `async`/`sync` + imgproxy processors, registry/factory,
`PictureRenderer` + `ImageViewHelper`, LQIP (ThumbHash/dominant/null), `ProcessImageRequest` middleware,
aspect-ratio element + ContentBlocks field, Site-Set settings, backend TypeScript.

Follow-on: remaining external providers (Thumbor, imgix, Cloudflare Images, Cloudinary), JS enhancement
(Safari `sizes` fallback, optional pixel-perfect refine), queue/warmup worker (v1 ships only the seams —
event + `WarmupInterface` no-op), per-breakpoint distinct crop in the element, private-image URL mode,
opt-in telemetry to auto-tune the ladder.

## Tests (layering matters for speed)

- **`Tests/Unit/`** — pure PHPUnit, no TYPO3 bootstrap. The signing + ladder core is deliberately
  framework-free so it runs fast. Keep new pure logic unit-testable.
- **`Tests/Functional/`** — boots TYPO3 + DB (`typo3/testing-framework`). For FAL, middleware,
  ViewHelpers, `ImageService` processing. Renderer output verified with **golden-file** tests (inject a
  fake processor returning predictable URLs, assert exact HTML).
