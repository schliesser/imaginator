# Plan — rework imgproxy into a remote materializer

> Implementation plan. Not yet built. `docs/DESIGN.md` stays the source of truth for locked intent;
> fold the relevant bits into it once this lands.

## Goal

`processor = imgproxy` stops being offloaded. imgproxy becomes the **pixel engine behind the existing
local-async machinery**: the server builds a crop+focus-aware imgproxy URL, GETs the bytes, and writes
them into `_processed_/` as a normal `sys_file_processedfile`. Warm renders then serve the static
`_processed_/` URL — identical to `local:async`. The only thing that changes vs. `local:async` is *who
renders the pixels* (imgproxy instead of GraphicsMagick).

A truly-offloaded imgproxy (`srcset` pointing straight at the provider) is a **future** processor —
out of scope here.

## Why this shape

The local-async path already does exactly what's wanted — cold → signed `/_imaginator/` → middleware →
`materialize()` → 302 → `_processed_`; warm → static URL via a read-only `ProcessedFileRepository`
probe. The middleware is already processor-agnostic (`ImageProcessorInterface`). So reuse all of it and
only swap the body of `materialize()`.

**Focus correctness comes for free:** `CropCalculator::fit(cropArea, focusArea, ratio)` already bakes
the focus area into one absolute crop rectangle (the same rect local mode feeds to `ImageService`). Hand
that exact rect to imgproxy's crop op → imgproxy output matches local output pixel-for-pixel, focus
included. No imgproxy `g:fp` guesswork needed.

## Decisions (locked)

- **Source access:** imgproxy fetches the original's **public URL**, reusing the existing
  `processorSourceBaseUrl` setting (empty = pass the FAL public path as-is).
- **Dead code:** delete `ExternalImageProcessor` + `ImgproxyProcessorFactory` (the offloaded path goes
  away; re-add a class when the future offloaded processor is actually built).
- **`isOffloaded()` → `false`** for imgproxy — it now uses the signed endpoint + middleware like local.
- **imgproxy no longer needs to be public.** The server fetches it (PHP → imgproxy); the client never
  sees the imgproxy URL. Base URL can be an internal Coolify hostname. Signing the imgproxy URL is now
  optional (private hop) — keep the existing optional HMAC support regardless.
- **Quality:** the middleware builds the variant with `quality = 0` (fine for local, which ignores it).
  The materializer must source quality from `Settings` (per-format `qualities`), **not** from
  `ImageVariant->quality`.

## Changes

### 1. Extract shared processing plan — `Classes/Imaging/ProcessingPlan.php` (new service)
Pull `processingPlan()` out of `LocalImageProcessor` verbatim into a collaborator returning
`{original, instructions, cropRect|null}`. Refactor `LocalImageProcessor` to use it.
**Critical:** the instructions array must stay byte-identical so the `_processed_` checksum is
unchanged (existing golden tests guard this).

### 2. Rework `ImgproxyUrlBuilder` — crop-aware grammar
New signature: `build(?Rectangle $crop, int $w, int $h, string $format, int $quality, string $sourceUrl)`
- reference (crop rect): `/c:{cw}:{ch}:nowe:{x}:{y}/rs:fill:{w}:{h}/q:{q}/plain/{src}@{ext}`
- plain (no rect): `/rs:fill:{w}:{h}/g:ce/q:{q}/plain/{src}@{ext}`
- HMAC signing path unchanged; keyless `insecure` fallback kept.

### 3. New `Classes/Imaging/Remote/ImgproxyImageProcessor.php` (replaces ExternalImageProcessor for the `imgproxy` key)
- `isOffloaded()` → `false`
- `buildUrl()` → warm-probe via `ProcessedFileRepository` (identical to local) → static URL; else
  signed `/_imaginator/` endpoint via `LocalAsyncUrlBuilder`
- `materialize()`:
  1. get `{original, instructions, cropRect}` from `ProcessingPlan`
  2. resolve quality from `Settings`
  3. `$pf = $repo->findOneByOriginalFileAndTaskTypeAndConfiguration($original, CONTEXT_IMAGECROPSCALEMASK, $instructions)`
  4. if `!$pf->isProcessed()`: build imgproxy URL → `RequestFactory::request()` GET → temp file →
     guard non-200 / 0-byte (reuse fail-loud) → `$pf->updateWithLocalFile($tmp)` →
     `$repo->add($pf, $pf->getTask())`
  5. return `ProcessedImage(getImageUri($pf), localPath, mime)`
- inject: `ProcessingPlan`, `ImgproxyUrlBuilder`, `RequestFactory`, `ProcessedFileRepository`,
  `ImageService`, `Settings`/`SettingsFactory`, `ResourceFactory` (source URL)

### 4. DI rewire — `Configuration/Services.yaml`
`imaginator.processor.imgproxy` → new processor + new factory. Delete `ExternalImageProcessor` +
`ImgproxyProcessorFactory`. Keep `ExternalConfig` / `UrlBuilderInterface`.

### 5. Docs
Update `DESIGN.md` §3 imgproxy bullet + `CLAUDE.md` architecture note: imgproxy is now a remote
materializer (no longer offloaded; crop/focus applied; imgproxy may be private).

## TDD order

1. **Unit** `ImgproxyUrlBuilderTest` (extend) — crop rect → `c:..nowe..` + `rs:fill` + quality +
   signature; plain → `g:ce`. Pure, fast. *(write failing → implement builder)*
2. **Functional** `ImgproxyImageProcessorTest` — fake `RequestFactory` returns known PNG bytes:
   - cold `buildUrl()` → signed `/_imaginator/` URL
   - `materialize()` → creates `_processed_` file, returns its static URL + correct mime
   - after materialize, warm `buildUrl()` → static `_processed_` URL (probe hit)
   - non-200 / 0-byte → throws
3. **Functional** middleware unchanged-behavior with imgproxy processor selected (cold → 302 to
   `_processed_`).
4. Refactor `LocalImageProcessor` onto `ProcessingPlan`; existing golden tests must stay green
   (checksum parity).

## Risks / watch-items

- **Checksum parity** when extracting `ProcessingPlan` — any drift in the instructions array changes the
  `_processed_` filename and breaks warm-probe. Golden tests are the guard.
- **`updateWithLocalFile` + `repo->add($pf, $pf->getTask())`** is the persistence path (mirrors core
  `FileProcessingService`). Confirm `ProcessedFile::getTask()` returns the task built by
  `findOneByOriginalFileAndTaskTypeAndConfiguration`, and that `isProcessed()` flips true after
  `updateWithLocalFile`. Functional test verifies.
- **DoS surface unchanged:** the middleware still re-checks rung width before `materialize()`. The
  imgproxy URL is server-internal — only built inside `materialize()`, never exposed to the client.
