# Imaginator v1 — External Processors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Offload pixel processing to an external service/CDN. `srcset` URLs point directly at the
provider — the webserver never touches pixels. Ship five `UrlBuilder`s: Thumbor, imgproxy, imgix,
Cloudflare Images, Cloudinary.

**Architecture:** `ExternalImageProcessor implements ImageProcessorInterface` with
`isOffloaded() === true`; `buildUrl()` delegates to the active `UrlBuilderInterface`, passing the
file's public origin URL. Each builder maps an `ImageVariant` (+ origin URL) → that provider's URL
grammar and is **pure** → exact-string golden-file tests.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14, PHPUnit. (Builders are framework-free.)

**Depends on:** foundation plan (`ImageVariant`, `ImageProcessorInterface`, `Settings`).

> **Verify-against-docs note:** the signing details below follow each provider's documented scheme,
> but signing is security-sensitive — each builder task includes a step to confirm the exact HMAC
> input/encoding against current provider docs before marking done. This is a correctness check, not a
> placeholder; the algorithms encoded here are the documented ones.

---

## File Structure
- `Classes/Imaging/External/UrlBuilder/UrlBuilderInterface.php`
- `Classes/Imaging/External/UrlBuilder/{Thumbor,Imgproxy,Imgix,Cloudflare,Cloudinary}UrlBuilder.php`
- `Classes/Imaging/External/ExternalImageProcessor.php`
- `Classes/Imaging/ImageProcessorFactory.php` — selects local vs external by `imaginator.processor`.
- `Classes/Dto/ExternalConfig.php` — baseUrl, signKey, account/cloud, etc.

---

## Task 1: UrlBuilderInterface + ExternalConfig

**Files:** Create interface + config DTO; Test `Tests/Unit/Imaging/External/ExternalConfigTest.php`

```php
interface UrlBuilderInterface
{
    /** @param string $sourceUrl absolute, publicly reachable origin image URL */
    public function build(ImageVariant $variant, string $sourceUrl): string;
}
```

`ExternalConfig` (readonly): `baseUrl`, `signKey`, `account` (Cloudinary cloud name / generic), each
nullable. A trivial unit test asserts immutability/accessors.

- [ ] Step 1 failing test → Step 2 FAIL → Step 3 implement → Step 4 PASS → Step 5 commit
  `feat: UrlBuilderInterface + ExternalConfig`.

---

## Task 2: ThumborUrlBuilder

Grammar: `{base}/{hmac}/{w}x{h}/smart/{source}` where
`hmac = urlsafe_base64( HMAC_SHA1(signKey, "{w}x{h}/smart/{source}") )`. Unsafe (unsigned) mode uses
the literal `unsafe` instead of the hmac when `signKey` is empty.

**Files:** Create `ThumborUrlBuilder.php`; Test `Tests/Unit/Imaging/External/ThumborUrlBuilderTest.php`

- [ ] **Step 1: Failing test**

```php
public function testSignedThumborUrl(): void
{
    $b = new ThumborUrlBuilder(new ExternalConfig('https://thumbor.example', 'sec', null));
    $url = $b->build(new ImageVariant(1, 5, 'hero', 640, 360, 'webp', 72), 'https://o/img.jpg');
    // path that gets signed:
    $path = '640x360/smart/https://o/img.jpg';
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha1', $path, 'sec', true)), '+/', '-_'), '=');
    self::assertSame("https://thumbor.example/{$sig}/640x360/smart/https://o/img.jpg", $url);
}

public function testUnsafeWhenNoKey(): void
{
    $b = new ThumborUrlBuilder(new ExternalConfig('https://t', '', null));
    self::assertStringContainsString('/unsafe/640x360/', $b->build($this->variant(), 'https://o/i.jpg'));
}
```

- [ ] **Step 2: FAIL** → **Step 3: implement** (build `$path`, sign or `unsafe`, prepend base) →
  **Step 4: PASS** → **Step 5: confirm scheme vs Thumbor security docs** → **Step 6: commit**
  `feat: Thumbor URL builder`.

---

## Task 3: ImgproxyUrlBuilder

Grammar (signed): `{base}/{signature}/rs:fill:{w}:{h}/g:sm/plain/{source}@{ext}` where
`signature = urlsafe_base64( HMAC_SHA256(binKey, binSalt . "/rs:fill:{w}:{h}/g:sm/plain/{source}@{ext}") )`,
`binKey`/`binSalt` = hex-decoded `signKey`/`salt`. Unsigned dev mode uses `insecure`.

**Files:** Create `ImgproxyUrlBuilder.php`; Test `Tests/Unit/.../ImgproxyUrlBuilderTest.php`

- [ ] **Step 1:** failing test asserting the exact signed string for a known key/salt (compute the same
  way in the test) and the `insecure` path when key is empty.
- [ ] **Step 2:** FAIL → **Step 3:** implement (hex-decode key+salt, build processing path, HMAC-SHA256,
  urlsafe-base64, no padding) → **Step 4:** PASS → **Step 5:** confirm vs imgproxy signing docs →
  **Step 6:** commit `feat: imgproxy URL builder`.

(Config gains a `salt` field — extend `ExternalConfig` here with a test for the new field.)

---

## Task 4: ImgixUrlBuilder

Grammar: `{base}/{path}?w={w}&h={h}&fit=crop&auto=format,compress&q={quality}` plus, when signed,
`&s={md5(signKey . "/{path}" . "?{query}")}`. `{path}` = the source path on the imgix source;
for a web-proxy source the full origin URL is URL-encoded into the path.

**Files:** Create `ImgixUrlBuilder.php`; Test

- [ ] **Step 1:** failing test: unsigned URL has the exact query (params in canonical order); signed URL
  appends correct `s=` md5. - [ ] **Step 2:** FAIL - [ ] **Step 3:** implement (ksort-stable query
  build, md5 signature over path+query) - [ ] **Step 4:** PASS - [ ] **Step 5:** confirm vs imgix
  securing-images docs - [ ] **Step 6:** commit `feat: imgix URL builder`.

---

## Task 5: CloudflareUrlBuilder

Grammar: `{base}/cdn-cgi/image/width={w},height={h},fit=cover,format=auto,quality={q}/{source}`.
No signing (Cloudflare Images via URL options is origin-gated, not HMAC).

**Files:** Create `CloudflareUrlBuilder.php`; Test

- [ ] **Step 1:** failing test asserting exact `/cdn-cgi/image/...` string. - [ ] **2:** FAIL -
  [ ] **3:** implement - [ ] **4:** PASS - [ ] **5:** commit `feat: Cloudflare Images URL builder`.

---

## Task 6: CloudinaryUrlBuilder (fetch mode)

Grammar: `https://res.cloudinary.com/{cloud}/image/fetch/w_{w},h_{h},c_fill,f_auto,q_auto:good/{source}`.
Optional signed delivery: `…/image/fetch/s--{sig}--/{transforms}/{source}` where
`sig = urlsafe_base64( sha1(transforms . "/" . source . apiSecret) )[0..8]`.

**Files:** Create `CloudinaryUrlBuilder.php`; Test

- [ ] **Step 1:** failing test for unsigned fetch URL (exact) + signed variant. - [ ] **2:** FAIL -
  [ ] **3:** implement (`cloud` from `ExternalConfig.account`) - [ ] **4:** PASS - [ ] **5:** confirm
  vs Cloudinary fetch+signature docs - [ ] **6:** commit `feat: Cloudinary fetch URL builder`.

---

## Task 7: ExternalImageProcessor

**Files:** Create `ExternalImageProcessor.php`; Test `Tests/Functional/Imaging/ExternalImageProcessorTest.php`

`implements ImageProcessorInterface`:
- `isOffloaded(): true`.
- `buildUrl(ImageVariant)`: resolve the `File` (`ResourceFactory->getFileObject($variant->fileUid)`),
  get its absolute public URL (origin), delegate to the injected `UrlBuilderInterface`.
- `materialize()`: throw `\LogicException('offloaded processor does not materialize')` — the endpoint
  middleware is never hit in external mode.

- [ ] **Step 1:** failing functional test — with a Thumbor builder + a fixture file, `buildUrl()`
  contains the file's public URL and the `640x360` segment; `materialize()` throws. - [ ] **2:** FAIL -
  [ ] **3:** implement - [ ] **4:** PASS - [ ] **5:** commit `feat: ExternalImageProcessor (offloaded)`.

---

## Task 8: ImageProcessorFactory + DI selection

**Files:** Create `ImageProcessorFactory.php`; Modify `Services.yaml`, `settings.definitions.yaml`
Test `Tests/Unit/Imaging/ImageProcessorFactoryTest.php`

- [ ] **Step 1:** failing unit test — `'local'` → `LocalImageProcessor`; each provider key → an
  `ExternalImageProcessor` carrying the matching builder; unknown → `\InvalidArgumentException`.
- [ ] **Step 2:** FAIL - [ ] **3:** implement (factory maps `imaginator.processor` setting + builds the
  right `UrlBuilder` from `ExternalConfig`). Register `ImageProcessorInterface` as a DI alias resolving
  through the factory so `PictureRenderer`/middleware are provider-agnostic. Add settings:
  `imaginator.processor`, `.processor.baseUrl`, `.processor.signKey`, `.processor.salt`,
  `.processor.account`. - [ ] **4:** PASS - [ ] **5:** commit
  `feat: processor factory selects local/external from settings`.

---

## Self-Review
- **Spec coverage:** five providers (design §4B) ✓ T2–6; offloaded processor + agnostic render path ✓
  T7–8; self-hosted (Thumbor/imgproxy) + SaaS (imgix/Cloudflare/Cloudinary) ✓.
- **Placeholder scan:** every builder has exact-string assertions computed the documented way; the
  "confirm vs docs" steps are security verification, not deferred work.
- **Type consistency:** `UrlBuilderInterface::build(ImageVariant, string): string` identical T2–7;
  `ExternalConfig` field additions (`salt` T3) reflected in the factory T8; `ImageProcessorInterface`
  matches the foundation definition (`buildUrl`/`isOffloaded`/`materialize`).
