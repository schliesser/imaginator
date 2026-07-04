# Plan — add an imgproxy remote-materializer alongside the offloaded one

> Implementation plan. Not yet built. `docs/DESIGN.md` stays the source of truth for locked intent;
> fold the relevant bits into it once this lands.

## Goal

Ship a **second** imgproxy processor that renders pixels behind the existing local-async machinery,
**without removing** the offloaded one. The two coexist as separate registry keys; the integrator picks
by whether they own a fronting cache.

- Keep the current offloaded processor (`srcset` points straight at imgproxy).
- Add a remote materializer: the server builds a crop+focus-aware imgproxy URL, GETs the bytes, writes
  them into `_processed_` as a normal `sys_file_processedfile`. Warm renders serve the static
  `_processed_` URL — identical to `local:async`. Only *who renders the pixels* differs (imgproxy instead
  of GraphicsMagick).

## Naming convention

**Offloaded is the default for every external processor — the bare provider name means offloaded.**
Materializing is the exception, so only it carries a suffix. This keeps the common case clean
(`imgproxy`, `imagor`, `thumbor`, …) and marks the rare server-fetch shape explicitly.

| key | `isOffloaded()` | `srcset` points at | who owns the derivative cache |
|-----|-----------------|--------------------|-------------------------------|
| `<provider>`             | `true`  | the provider          | integrator's CDN / reverse-proxy (mandatory) |
| `<provider>:materialize` | `false` | our `/_imaginator/` → `_processed_` | TYPO3 (`_processed_`) |

- `imgproxy` — today's `ExternalImageProcessor` (offloaded). Unchanged; no rename, no alias needed.
- `imgproxy:materialize` — the new processor in this plan. **imgproxy is the only provider that ships a
  materialize variant** — it exists to showcase and test the non-offloaded path.
- Other providers (`imagor`, future `thumbor`/`imgix`/…) ship **offloaded only**, with no `:materialize`
  variant. A provider grows a `:materialize` key only if a real need appears.

## Why this shape

The local-async path already does exactly what the materializer wants — cold → signed `/_imaginator/` →
middleware → `materialize()` → 302 → `_processed_`; warm → static URL via a read-only
`ProcessedFileRepository` probe. The middleware is already processor-agnostic
(`ImageProcessorInterface`). So reuse all of it and only swap the body of `materialize()`.

**Focus correctness comes for free:** `CropCalculator::fit(cropArea, focusArea, ratio)` already bakes the
focus area into one absolute crop rectangle (the same rect local mode feeds to `ImageService`). Hand that
exact rect to imgproxy's crop op → imgproxy output matches local output pixel-for-pixel, focus included.
No imgproxy `g:fp` guesswork needed.

## Decisions (locked)

- **Both processors coexist.** `ExternalImageProcessor` + `ImgproxyProcessorFactory` are **kept**, not
  deleted — they back the default offloaded `imgproxy` key. The materializer is an additive new class +
  factory + `imgproxy:materialize` key.
- **Source access:** imgproxy fetches the original's **public URL**, reusing the existing
  `processorSourceBaseUrl` setting (empty = pass the FAL public path as-is). Shared by both shapes.
- **`isOffloaded()`:** default `imgproxy` → `true` (unchanged); `imgproxy:materialize` → `false`.
- **imgproxy may be private for the materializer.** The server fetches it (PHP → imgproxy); the client
  never sees the imgproxy URL, so the base URL can be an internal Coolify hostname. Signing the imgproxy
  URL is optional on that hop — keep the existing optional HMAC support regardless. (The offloaded shape
  still needs imgproxy public + a fronting cache, as before.)
- **Quality:** the middleware builds the variant with `quality = 0` (fine for local, which ignores it).
  The materializer must source quality from `Settings` (per-format `qualities`), **not** from
  `ImageVariant->quality`.

## Changes

### 1. Extract shared processing plan — `Classes/Imaging/ProcessingPlan.php` (new service)
Pull `processingPlan()` out of `LocalImageProcessor` verbatim into a collaborator returning
`{original, instructions, cropRect|null}`. Refactor `LocalImageProcessor` to use it.
**Critical:** the instructions array must stay byte-identical so the `_processed_` checksum is unchanged
(existing golden tests guard this).

### 2. Extend `ImgproxyUrlBuilder` — crop-aware grammar, without breaking the offloaded call
**Largely landed ahead of this plan:** `UrlBuilderInterface::build()` now takes an optional
`?Rectangle $crop` and both offloaded builders emit the crop-aware grammar
(imgproxy: `/c:{cw}:{ch}:nowe:{x}:{y}/rs:fill:{w}:{h}/q:{q}/plain/{src}@{ext}`, `g:sm` fallback;
imagor: `{x1}x{y1}:{x2}x{y2}/{w}x{h}/…`, `smart` fallback), and `ExternalImageProcessor` resolves the
rect via `CropResolver` + `CropCalculator::fit()` for reference variants. The materializer reuses
`build()` with the rect from `ProcessingPlan` — no separate `buildSigned` needed; only the `g:ce`
(vs `g:sm`) choice for plain files remains a materializer-specific decision.

### 3. New `Classes/Imaging/Remote/ImgproxyMaterializeProcessor.php` (backs `imgproxy:materialize`)
- `isOffloaded()` → `false`
- `buildUrl()` → warm-probe via `ProcessedFileRepository` (identical to local) → static URL; else signed
  `/_imaginator/` endpoint via `LocalAsyncUrlBuilder`
- `materialize()`:
  1. get `{original, instructions, cropRect}` from `ProcessingPlan`
  2. resolve quality from `Settings`
  3. `$pf = $repo->findOneByOriginalFileAndTaskTypeAndConfiguration($original, CONTEXT_IMAGECROPSCALEMASK, $instructions)`
  4. if `!$pf->isProcessed()`: build imgproxy URL (`buildSigned`) → `RequestFactory::request()` GET →
     temp file → guard non-200 / 0-byte (fail loud) → `$pf->updateWithLocalFile($tmp)` →
     `$repo->add($pf, $pf->getTask())`
  5. return `ProcessedImage(getImageUri($pf), localPath, mime)`
- inject: `ProcessingPlan`, `ImgproxyUrlBuilder`, `RequestFactory`, `ProcessedFileRepository`,
  `ImageService`, `Settings`/`SettingsFactory`, `ResourceFactory` (source URL)

### 4. DI wiring — `Configuration/Services.yaml`
- Keep `imaginator.processor.imgproxy` → `ExternalImageProcessor` via `ImgproxyProcessorFactory`
  (default offloaded key, unchanged).
- Add **`imgproxy:materialize`** → new `ImgproxyMaterializeProcessor` + its factory.
- Keep `ExternalConfig` / `UrlBuilderInterface`.

### 5. Docs
Update `DESIGN.md` §3 imgproxy bullet + the "Locked decisions → External providers" line + `CLAUDE.md`
architecture note: external processors are offloaded by default (bare name), `imgproxy:materialize` is
the opt-in non-offloaded remote engine (crop/focus applied; imgproxy may be private), imgproxy being the
sole provider that showcases it.

## TDD order

1. **Unit** `ImgproxyUrlBuilderTest` (extend) — `buildSigned`: crop rect → `c:..nowe..` + `rs:fill` +
   quality + signature; plain → `g:ce`. Existing offloaded `build()` tests untouched + green. Pure, fast.
2. **Functional** `ImgproxyMaterializeProcessorTest` — fake `RequestFactory` returns known PNG bytes:
   - cold `buildUrl()` → signed `/_imaginator/` URL
   - `materialize()` → creates `_processed_` file, returns its static URL + correct mime
   - after materialize, warm `buildUrl()` → static `_processed_` URL (probe hit)
   - non-200 / 0-byte → throws
3. **Functional** middleware unchanged-behavior with `imgproxy:materialize` selected (cold → 302 to
   `_processed_`).
4. **Functional** default `imgproxy` still emits provider `srcset` URLs — regression guard that the
   offloaded path is intact.
5. Refactor `LocalImageProcessor` onto `ProcessingPlan`; existing golden tests must stay green
   (checksum parity).

## Risks / watch-items

- **Checksum parity** when extracting `ProcessingPlan` — any drift in the instructions array changes the
  `_processed_` filename and breaks warm-probe. Golden tests are the guard.
- **Don't regress the offloaded output** when extending `ImgproxyUrlBuilder` — keep the old method's
  grammar byte-identical; the new grammar lives in `buildSigned` only.
- **`updateWithLocalFile` + `repo->add($pf, $pf->getTask())`** is the persistence path (mirrors core
  `FileProcessingService`). Confirm `ProcessedFile::getTask()` returns the task built by
  `findOneByOriginalFileAndTaskTypeAndConfiguration`, and that `isProcessed()` flips true after
  `updateWithLocalFile`. Functional test verifies.
- **DoS surface unchanged:** the middleware still re-checks rung width before `materialize()`. The
  imgproxy URL is server-internal for the materializer — only built inside `materialize()`, never exposed
  to the client.
