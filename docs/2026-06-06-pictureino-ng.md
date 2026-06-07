# Pictureino NG — Architecture & Design Plan

> **Status:** Design RFC. Core decisions locked (see §12); remaining **[DECIDE]** items are minor.
> This document is the input to the bite-sized TDD implementation plan, not the task list itself.
>
> **Locked 2026-06-06:** HMAC-signed readable URLs · external providers Thumbor + imgproxy + imgix +
> Cloudflare Images + Cloudinary · local backend GraphicsMagick-only (vips → v2) · aspect-ratio
> element = per-breakpoint ratio at **content-element level** (applies to all media in the CE), no
> live preview · **TYPO3 13.4 + 14.x, PHP 8.3 floor** · LQIP default **ThumbHash** · queue/warmup → v2
> (seams in v1) · extension key **`zeroseven/pictureino` `^2.0`** (new major). All decisions in §12.

**Goal:** A new TYPO3 v13 + v14 responsive-image extension that keeps Pictureino's
"editor writes nothing, no `sizes` to maintain" benefit, but renders **sharp images
on first paint with zero JS dependency** by serving a native `srcset` ladder, and
supports **pluggable image processing** (local *or* offloaded to an external service/CDN).

**Architecture (one paragraph):** At page-render time the extension emits a real responsive
`<picture>`/`<img>` with a quantized **width ladder** in `srcset`, `sizes="auto"`,
reserved aspect-ratio space, and an LQIP. The browser's preload scanner selects and loads the
correctly-sized image directly — one round-trip, CDN-cacheable, no JSON hop. Each candidate URL
is produced by a pluggable **ImageProcessor** (local TYPO3 processing behind a signed endpoint,
or an external provider like imgix/Cloudinary/Cloudflare/Thumbor that does the pixels at the edge).
JavaScript is demoted to pure progressive enhancement (Safari `sizes` fallback + optional
pixel-perfect refinement), never load-bearing for sharpness.

**Tech stack:** PHP 8.2+ (8.3 target), TYPO3 v13.4 LTS + v14, Symfony DI, TypeScript (Vite),
Web Components, HMAC-signed URLs, pluggable processors.

---

## 1. What we keep, what we change

### Keep (the value-prop)
- **Editor/developer writes no `sizes`, no breakpoints per image.** Layout is editor-driven
  (float, columns, nesting, container queries) — unknowable at render time. This is the core reason
  the extension exists and must survive.
- Per-image **aspect-ratio configuration** in the backend, per breakpoint.
- Crop variant / focus-area awareness from FAL.
- Site-Set based configuration (already the v13 approach).

### Change (the performance fixes from the research)
| Problem in current ext | Root cause | Fix in NG |
|---|---|---|
| Blurry 1–2s | Only an 80px q40 placeholder is server-rendered; sharp image waits on JS | Render a real `srcset` ladder server-side |
| Double round-trip | Middleware returns JSON `{url}`, client then fetches the image | Candidate URLs ARE the image (signed endpoint streams bytes / 302) |
| Preload scanner blind | Real URL computed client-side after layout | Ladder is in the HTML; scanner fetches directly |
| LCP penalty | Hero image can't start until JS runs | `priority` flag → eager + `fetchpriority=high` + preload |
| Cold-cache cost on critical path | Synchronous GMagick inside the request | Quantized ladder + edge cache + optional warmup + offloading |
| Retina handled manually (1x/2x JSON) | Custom DPR logic | **Drop it** — `w`-descriptor `srcset` lets the browser pick by DPR natively |

---

## 2. The width-ladder model

### Why a ladder (recap of the trade)
Pixel-perfect (exact element width) saves ~10% bytes vs a ladder but costs the entire JS-gated,
double-round-trip latency. Every big service ladders instead. A geometric ladder of ~8 rungs has
≤~22% worst-case overdraw, ~10% average — that is the whole price, paid in bytes, to delete the blur.

### How `sizes="auto"` preserves "no sizes to write"
`<img srcset sizes="auto" loading="lazy">` makes the browser fill in the **real laid-out width**
and pick from the ladder — exactly what the old ResizeObserver did, but native, at layout time,
no fetch, no JS. The developer still writes nothing. Caveats handled below.

### Ladder definition
- Configurable rungs; default geometric, e.g. `[320, 420, 560, 740, 980, 1300, 1720, 2000]`,
  capped at `maxImageDimensions` (Site-Set setting, default 2000).
- **No separate `2x` rungs.** With `w`-descriptor `srcset`, the browser multiplies the CSS width by
  `devicePixelRatio` when choosing — DPR is handled for free. This removes the entire retina
  subsystem from the old extension.
- Width is **quantized to rungs** before signing → bounded set of processed files (cache efficiency
  + the signature is only valid for rung widths, capping the processed-file explosion).
- `height` derived from the per-breakpoint aspect ratio (reuse `AspectRatio` math).

### Output shape (art direction via `<picture>`)
When the editor sets different ratios per breakpoint → emit `<picture>` with one `<source>` per
breakpoint, each carrying its own ladder. Single ratio → plain `<img>`. AVIF/WebP/fallback handled
as additional `<source type>` tiers (see §5).

```html
<picture>
  <source type="image/avif" media="(min-width:992px)"
          srcset="…/980x551.avif 980w, …/1300x731.avif 1300w, …" sizes="auto">
  <source type="image/webp" media="(min-width:992px)"
          srcset="…/980x551.webp 980w, …/1300x731.webp 1300w, …" sizes="auto">
  <!-- smaller breakpoints … -->
  <img src="…/740x416.webp" srcset="…ladder…" sizes="auto" loading="lazy"
       width="1300" height="731" decoding="async" alt="…"
       style="background:#8a7f6e"> <!-- LQIP/dominant color, zero CLS via width/height -->
</picture>
```

---

## 3. URL pattern — discussion & recommendation **[DECIDE]**

### Current Pictureino
```
/-/pictureino/img/{view}{retina}x{ENCRYPTED_CONFIG}/{webp}/{w}x{h}/
```
- **Encrypted** opaque config blob. Pros: hides FAL ids, tamper-proof. Cons: opaque/undebuggable,
  per-request crypto cost, **URL tied to a secret key → key rotation invalidates every cached URL**,
  long URLs, no real file extension (CDNs/browsers don't see it as an image).

### How the services do it
| Service | Pattern |
|---|---|
| imgix | `https://x.imgix.net/path.jpg?w=640&h=360&fit=crop&auto=format&s=<hmac>` |
| Cloudinary | `/image/upload/w_640,h_360,c_fill,f_auto,q_auto/sample.jpg` |
| Thumbor | `/<hmac>/640x360/smart/<source-url>` |
| Cloudflare | `/cdn-cgi/image/width=640,format=auto/<source-url>` |
| Next.js | `/_next/image?url=…&w=640&q=75` |

Common winners: **params are readable**, URL **ends in a real image extension or sets correct
`Content-Type`**, and integrity is an **HMAC signature, not encryption**.

### Decision — HMAC-signed, readable (LOCKED)
```
/_pictureino/{sig}/{storage}-{fileUid}/{cropVariant}/{w}x{h}.{ext}
e.g.  /_pictureino/a1b2c3d4/1-4567/hero/1300x731.avif
```
- **`{sig}`** = first 16 hex of `HMAC-SHA256(secret, canonical_params)`. Signing (not encryption)
  is cheaper, debuggable, and — critically — **prevents image-resize DoS**: an attacker can't request
  arbitrary `w×h` to exhaust the processor, because only signed (= rung) sizes verify.
- **Real extension** (`.avif`/`.webp`/`.jpg`) → CDN + browser treat it as a cacheable image with the
  right `Content-Type`; no `Vary: Accept` cache fragmentation.
- **Readable params** → trivial to debug in devtools/logs.
- **Key rotation:** support an array of valid secrets (current + previous) so rotating the signing key
  doesn't cold-cache the whole site. (The old encrypted scheme can't do this cleanly.)
- **Private images mode [DECIDE]:** optional variant where `{storage}-{fileUid}` is replaced by an
  encrypted token, for access-restricted files. Default = signed-but-readable.

Path-segment params (above) over query strings — cleaner CDN path caching, real extension.
- **Key rotation:** array of valid secrets (current + previous) so rotating the signing key doesn't
  cold-cache the site. Sign with current, verify against any.
- **Private-image mode:** deferred to v2 (signed-only for v1).

---

## 4. Pluggable image processing **[DECIDE on first providers]**

The render layer is **processor-agnostic**. It asks the active processor for the candidate URL of an
abstract variant. Same HTML regardless of who does the pixels.

### Core abstraction
```php
interface ImageProcessorInterface
{
    // Render-time: return the URL that belongs in srcset for this variant.
    public function buildUrl(ImageVariant $variant): string;

    // True if pixels happen elsewhere (external CDN/service); local processing skipped.
    public function isOffloaded(): bool;

    // Local processors only: produce/return the processed binary for the signed endpoint.
    public function materialize(ImageVariant $variant): ProcessedImage;
}

final class ImageVariant   // immutable value object
{
    public function __construct(
        public readonly FileInterface $file,
        public readonly int $width,
        public readonly int $height,
        public readonly string $format,        // avif|webp|jpeg|png
        public readonly ?Area $crop,
        public readonly ?Area $focus,
        public readonly int $quality,
    ) {}
}
```

### Two processor families

**A. Local (default) — `LocalImageProcessor`**
- `buildUrl()` → the signed `/_pictureino/…` endpoint URL.
- A PSR-15 middleware (or route) verifies the signature, calls `materialize()`, and **streams the
  bytes / 302-redirects to the processed FAL file** (no JSON). Result is written to a configured
  storage (`_processed_` or a dedicated FAL storage) so repeat hits are static-served.
- **v1 backend: GraphicsMagick only** (TYPO3 `GraphicalFunctions`, zero new infra). Still behind a
  thin `LocalBackendInterface` so **libvips** can drop in as a **v2** backend without touching callers.

**B. Offloaded — `ExternalImageProcessor` + per-provider `UrlBuilder` (v1 set LOCKED)**
- `isOffloaded() === true`; `buildUrl()` maps `ImageVariant` → the provider's URL grammar and points
  `srcset` **directly at the provider**. The webserver never touches pixels.
- v1 `UrlBuilderInterface` implementations:
  - **Thumbor** — open-source, self-hostable, HMAC-signed paths.
  - **imgproxy** — open-source, Go/libvips, very fast, native signed URLs. The other self-hosted option.
  - **imgix** — SaaS, cleanest URL API.
  - **Cloudflare Images** — `/cdn-cgi/image/...`; many TYPO3 sites already front Cloudflare.
  - **Cloudinary** — SaaS, path-segment transforms.
- Provider needs to fetch the origin file once → requires the source image to be publicly reachable
  (or a signed origin URL). Handle private files by passing a short-lived signed origin URL.
- The two self-hosted options (Thumbor, imgproxy) directly answer "offload the webserver without a
  SaaS bill"; the three SaaS ones cover teams that prefer managed edge processing.

**C. Hybrid (documented pattern, no code):** `LocalImageProcessor` behind a reverse-proxy CDN
(Cloudflare/Varnish/Fastly). Pixels are local but only computed once globally. Often the best
real-world setup; we just need cache headers right (immutable, long max-age, content-addressed URLs).

### Configuration (Site Set settings)
```yaml
pictureino.processor: local            # local | thumbor | imgproxy | imgix | cloudflare | cloudinary
pictureino.processor.baseUrl: ''       # external base
pictureino.processor.signKey: ''       # provider signing key (or env ref)
pictureino.local.backend: graphicsmagick   # v1: graphicsmagick only (vips in v2)
pictureino.formats: [avif, webp]       # negotiated tiers, fallback original kept
pictureino.ladder: [320,420,560,740,980,1300,1720,2000]
pictureino.quality.avif: 50
pictureino.quality.webp: 72
pictureino.lqip: thumbhash             # thumbhash | dominant-color | none
```

**Resolved:** v1 ships all five — Thumbor, imgproxy, imgix, Cloudflare Images, Cloudinary —
behind a common `UrlBuilderInterface`. Each builder is small and independently testable
(golden-file test per provider grammar).

---

## 5. Format negotiation

- Emit **AVIF + WebP + original** as stacked `<picture>` `<source type>` tiers, each with its own
  ladder. Browser picks the first it supports → no `Vary: Accept`, fully CDN-cacheable per-URL.
- Format is **baked into the URL extension** (`.avif`/`.webp`), so each is an independent cache entry.
- AVIF gives ~50% over JPEG, ~20% over WebP, at higher CPU cost — hence offloading/vips matter.
- Quality per format from settings; AVIF can go lower (q≈50) for the same perceived quality.

---

## 6. LQIP (low-quality placeholder) **[DECIDE: default]**

Pluggable `LqipGeneratorInterface`. **Default: ThumbHash.**
- **ThumbHash (default)** — ~25 byte hash, decodes to a smooth blurred preview, better color than
  BlurHash, inlined as a tiny data-URI or rendered to a CSS gradient.
- **Dominant color** — single `background-color`, near-zero bytes, no decode, no flash.
- **None** — just reserved aspect-ratio box.

LQIP sits behind the `<img>` as `background` (or a `<canvas>`), and **the sharp image renders on top
immediately** from the ladder — so unlike today, the LQIP is a fallback for the first few hundred ms
of decode, not a 1–2s blocking phase. Zero CLS because width/height/aspect-ratio reserve the box.

---

## 7. JavaScript — progressive enhancement only

The custom element survives but is **no longer load-bearing for sharpness**.

Responsibilities, in order of importance:
1. **Safari / no-`sizes=auto` fallback:** if the browser lacks auto-sizing, measure the laid-out
   width once and set a concrete `sizes="640px"`. The browser re-picks **from the ladder already in
   the HTML** — no JSON, no extra request. (Sharp either way; this just tightens selection.)
2. **Optional pixel-perfect refinement:** after a good image is on screen, optionally swap `src` to an
   exact-width signed URL to recover the ladder's ~10% overdraw. Off by default; opt-in per project.
3. **`sizes=auto` requires `loading=lazy`** → for `priority`/LCP images we don't use auto-sizing;
   we render an explicit `sizes` (default `100vw`, overridable) + `fetchpriority=high` + a
   `<link rel=preload imagesrcset>` in `<head>`.

Worst case (JS disabled, old Safari): correctly-sized-enough image, **zero blur**. Today's worst case
is a permanent blur — strict improvement.

---

## 8. Improved aspect-ratio backend element

Replaces the current `AspectRatioElement` custom FormEngine node. **Scope (LOCKED):** the element
configures **per-breakpoint aspect ratios at the content-element level** — the chosen ratios apply to
**all images/media files in that CE**, not per individual file. Therefore **no per-image live preview**
(there isn't a single image to preview against).

### Design
- **Per-breakpoint ratio rows**: editor adds rows like `xs → 1:1`, `md → 4:3`, `lg → 16:9`. Each row
  = one breakpoint (from the Site-Set breakpoint list) + one ratio. This drives the `<picture>`
  art-direction sources at render time, applied uniformly to every media file in the CE.
- **Ratio input UX**: presets (1:1, 4:3, 3:2, 16:9, 21:9) + free numeric `w:h` + **"auto"**
  (auto = keep each file's original ratio for that breakpoint).
- **Visual ratio swatch**: render each chosen ratio as a proportioned box (a generic shape, not a
  specific image) so the editor sees the *shape* per breakpoint at a glance — cheap, no image load.
- **Single structured JSON field** (portable, no FlexForm), schema-validated. Shape:
  `[{"breakpoint":"lg","minWidth":992,"ratio":"16:9"}, …]`.
- **Crop/focus stays per-file** in TYPO3's native crop tool; the CE-level ratio simply selects which
  crop-variant/area the processor uses per breakpoint. (CE sets the *ratio*; FAL keeps the *crop*.)
- **Web Component** shared between v13 and v14; a thin adapter isolates FormEngine API differences
  (v14 ships FormEngine changes) so the UI code is version-agnostic.
- Keyboard-accessible, screen-reader labels (a11y).

**Deferred to v2:** per-breakpoint *distinct crop region* selection in the element (bigger UI lift;
v1 reuses native FAL crop variants).

---

## 9. More ideas (your "do you have more ideas?")

1. **Zero-CLS guarantee** via `width`/`height` + CSS `aspect-ratio` on every image — measurable Core
   Web Vitals win, independent of everything else.
2. **Warmup on publish (v2):** DataHandler hook + Symfony Messenger/Scheduler queue pre-renders the
   ladder for `priority` images so the first visitor is never cold. v1 ships only the seam
   (`WarmupInterface` no-op + event).
3. **Off-request bulk processing (v2):** heavy local processing pushed to a queue worker; the endpoint
   serves a "processing" 202 / LQIP until ready, or blocks only on first hit.
4. **Client Hints mode (optional):** `Accept-CH: Sec-CH-Width, Sec-CH-DPR` lets a CDN pick exact width
   server-side — a no-JS *pixel-perfect* path for setups that can enable it. Nice-to-have, not core.
5. **`image-set()` for CSS backgrounds:** a ViewHelper/API to emit responsive `background-image`
   ladders, so editor background images get the same treatment, not just `<img>`.
6. **CKEditor/RTE inline images:** make bodytext images go through the ladder too (a common gap).
7. **Telemetry (opt-in):** log which ladder rungs are actually selected to auto-tune the ladder per
   site. Carry over the old `MetricsUtility` idea but make it optional + privacy-safe.
8. **Defense in depth:** keep a rate limiter on the local endpoint (old ext had one) on top of HMAC
   signing — signing stops arbitrary sizes, rate limiting stops flooding signed-but-expensive ones.
9. **Immutable, content-addressed cache headers** (`Cache-Control: public, max-age=31536000, immutable`)
   since the signature changes when inputs change.
10. **`decoding="async"`** everywhere; never block the main thread on decode.
11. **Graceful no-JS / no-processor degradation:** if processing fails, fall back to the original FAL
    public URL rather than a broken image.
12. **Test matrix:** functional tests for signature verify + each `UrlBuilder` grammar; the element via
    acceptance tests; golden-file tests for ladder/`<picture>` output.

---

## 10. Proposed module / file structure

```
Classes/
  Dto/ImageVariant.php                     # immutable value object
  Imaging/
    ImageProcessorInterface.php
    Local/LocalImageProcessor.php
    Local/Backend/GraphicsMagickBackend.php
    Local/Backend/VipsBackend.php          # [DECIDE v1?]
    External/ExternalImageProcessor.php
    External/UrlBuilder/UrlBuilderInterface.php
    External/UrlBuilder/{Imgix,Cloudinary,Cloudflare,Thumbor}UrlBuilder.php
  Ladder/LadderFactory.php                 # rungs, quantization, height-from-ratio
  Url/SignedUrlBuilder.php                 # HMAC sign/verify, key rotation
  Lqip/LqipGeneratorInterface.php
  Lqip/{ThumbHash,DominantColor}Generator.php
  Rendering/PictureRenderer.php            # builds <picture>/<img> from variants
  ViewHelpers/ImageViewHelper.php          # thin; delegates to PictureRenderer
  Middleware/ProcessImageRequest.php       # verify sig -> materialize -> stream/302
  Backend/Form/Element/AspectRatioElement.php
  Configuration/SettingsProvider.php       # reads Site Set settings
Configuration/
  Sets/Pictureino/{config.yaml,settings.definitions.yaml,setup.typoscript}
  Services.yaml
  RequestMiddlewares.php
Resources/Private/JavaScript/
  frontend/   (enhancement: safari-sizes-fallback, optional refine)
  backend/aspect-ratio/   (web component)
```

---

## 11. Build sequence (phased roadmap)

Once the **[DECIDE]** items are settled, each phase becomes a bite-sized TDD task plan. High-level order:

1. **Foundation:** `ImageVariant`, `LadderFactory`, `SignedUrlBuilder` (+ key rotation), settings.
   Pure units, fully testable, no TYPO3 rendering yet.
2. **Local processor + endpoint:** `LocalImageProcessor` (GraphicsMagick), `ProcessImageRequest`
   middleware streaming bytes / 302. Functional test: signed URL → real resized bytes; bad sig → 403.
3. **Rendering:** `PictureRenderer` → `<picture>`/`<img>` with ladder, `sizes=auto`, aspect/CLS,
   LQIP. Golden-file tests. `ImageViewHelper` wired.
4. **Format tiers + LQIP:** AVIF/WebP sources, ThumbHash/dominant-color generators.
5. **JS enhancement:** Safari `sizes` fallback; `priority`/preload path; optional refine.
6. **External processors:** `UrlBuilderInterface` + first provider(s) from §4 decision.
7. **Aspect-ratio element:** web component, per-breakpoint ratios, live preview, v13/v14 adapter.
8. **Polish (v1):** warmup/telemetry **seams** (no-op interfaces + events), optional rate limiting, docs.
9. **v2 (separate plan):** Messenger/Scheduler warmup worker, libvips backend, per-breakpoint crop in
   the element, private-image URL mode, telemetry collection.

---

## 12. Decisions

### Locked (2026-06-06)
- [x] **URL scheme:** HMAC-signed, readable, path-segment params, real extension. Key-rotation via
  secret array. Private-image mode → v2.
- [x] **External providers (v1):** Thumbor, imgproxy, imgix, Cloudflare Images, Cloudinary.
- [x] **Local backend (v1):** GraphicsMagick only; libvips → v2 (interface in place).
- [x] **Aspect-ratio element:** per-breakpoint ratio at **content-element level** (applies to all
  media in the CE), no live preview; per-breakpoint distinct crop → v2.

### Locked (2026-06-06, round 2)
- [x] **Min versions:** TYPO3 **13.4 LTS + 14.x**, PHP **8.3** floor (test matrix **8.3 / 8.4 / 8.5**).
  Rationale: 8.3 is the lowest that satisfies both v13 (8.2–8.4) and v14 (8.3+) without excluding
  v13 sites on 8.3. Matrix runs up to 8.5 for forward-compat. Bump floor once v13 support is dropped.
- [x] **LQIP default:** **ThumbHash** (~25 B, smooth, good color), inlined as data-URI. Alternatives
  `dominant-color` (zero decode) and `none` ship behind `LqipGeneratorInterface`, selectable per site.
- [x] **Queue/warmup:** **v2.** v1 processes on first hit + relies on edge cache. But v1 lays the
  **seams**: a `pictureino.afterFileProcessed`-style event + a `WarmupInterface` no-op, so v2 adds the
  Messenger/Scheduler worker without touching the render or processing code.
- [x] **Extension key / name:** **`imaginator`** — composer `schliesser/imaginator`, TER key
  `imaginator`, PHP namespace `Schliesser\Imaginator\`. Implementation plan:
  `2026-06-06-imaginator-v1-foundation.md`.

### Constraints (composer)
```json
"require": {
  "php": ">=8.3",
  "typo3/cms-core": "^13.4 || ^14.0",
  "typo3/cms-backend": "^13.4 || ^14.0",
  "typo3/cms-frontend": "^13.4 || ^14.0",
  "typo3/cms-fluid": "^13.4 || ^14.0"
}
```
No `symfony/rate-limiter` hard dep unless §9.8 rate limiting ships in v1 (then `^7.0`).
```
