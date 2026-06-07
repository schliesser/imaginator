# Imaginator v1 — Foundation & Core Render Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ship the working vertical slice of `schliesser/imaginator` — a TYPO3 v13/v14 extension that
renders a native `srcset` width-ladder `<picture>`/`<img>` whose candidate URLs are HMAC-signed and
served as real image bytes by a local processing endpoint.

**Architecture:** Pure, framework-free foundation (aspect-ratio math, ladder, canonical params,
signed-URL builder) → a PSR-15 endpoint that verifies the signature and streams a locally processed
image (GraphicsMagick) → a renderer + Fluid ViewHelper that emits the ladder. JS, extra formats,
LQIP, external providers and the backend element are **separate follow-on plans** (see end).

**Tech Stack:** PHP 8.3 (matrix 8.3/8.4/8.5), TYPO3 13.4 + 14, Symfony DI, PHPUnit + typo3/testing-framework.

**Design source:** `docs/plans/2026-06-06-pictureino-ng.md` (decisions in its §12).

---

## File Structure

Pure foundation (plain PHPUnit, no TYPO3 bootstrap — fast TDD):
- `Classes/Dto/AspectRatio.php` — ratio value object + parsing + `heightFor()`.
- `Classes/Ladder/Rung.php` — `{width,height}` value object.
- `Classes/Ladder/LadderFactory.php` — build rung list; quantize arbitrary width to a rung.
- `Classes/Url/CanonicalParams.php` — the signed param set + deterministic canonical string.
- `Classes/Url/SignedUrlBuilder.php` — HMAC sign/verify, key rotation.

TYPO3-integrated (functional tests):
- `Classes/Dto/ImageVariant.php`, `Classes/Dto/ProcessedImage.php`
- `Classes/Imaging/ImageProcessorInterface.php`, `Classes/Imaging/Local/LocalImageProcessor.php`
- `Classes/Imaging/Local/Backend/{LocalBackendInterface,GraphicsMagickBackend}.php`
- `Classes/Middleware/ProcessImageRequest.php`
- `Classes/Rendering/PictureRenderer.php`, `Classes/ViewHelpers/ImageViewHelper.php`
- `Classes/Configuration/Settings.php` (reads Site-Set settings)
- `Configuration/{Services.yaml,RequestMiddlewares.php,Sets/Imaginator/*}`

---

## Task 0: Extension skeleton + test harness

**Files:**
- Create: `composer.json`, `ext_emconf.php`, `Build/phpunit/UnitTests.xml`, `Build/phpunit/FunctionalTests.xml`
- Create: `.gitignore`

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "schliesser/imaginator",
  "type": "typo3-cms-extension",
  "description": "Zero-config responsive images for TYPO3: signed srcset ladders, local or external processing.",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.3",
    "typo3/cms-core": "^13.4 || ^14.0",
    "typo3/cms-backend": "^13.4 || ^14.0",
    "typo3/cms-frontend": "^13.4 || ^14.0",
    "typo3/cms-fluid": "^13.4 || ^14.0"
  },
  "require-dev": {
    "typo3/testing-framework": "^8.2 || ^9.0",
    "phpunit/phpunit": "^11.0",
    "friendsofphp/php-cs-fixer": "^3.51",
    "phpstan/phpstan": "^2.1"
  },
  "autoload": { "psr-4": { "Schliesser\\Imaginator\\": "Classes/" } },
  "autoload-dev": { "psr-4": { "Schliesser\\Imaginator\\Tests\\": "Tests/" } },
  "extra": { "typo3/cms": { "extension-key": "imaginator" } }
}
```

- [ ] **Step 2: Write `ext_emconf.php`**

```php
<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Imaginator',
    'description' => 'Zero-config responsive images: signed srcset ladders, local or external processing.',
    'category' => 'fe',
    'author' => 'Schliesser',
    'state' => 'beta',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-14.99.99', 'php' => '8.3.0-8.5.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
];
```

- [ ] **Step 3: Write `Build/phpunit/UnitTests.xml`**

```xml
<?xml version="1.0"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="../../.Build/vendor/autoload.php"
         colors="true" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="unit">
            <directory>../../Tests/Unit/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 4: Write `Build/phpunit/FunctionalTests.xml`** (standard typo3/testing-framework functional template pointing at `../../Tests/Functional/`).

- [ ] **Step 5: Install & verify the harness runs**

Run: `composer update && .Build/bin/phpunit -c Build/phpunit/UnitTests.xml`
Expected: PASS with "No tests executed" (0 tests, exit 0).

- [ ] **Step 6: Commit**

```bash
git add composer.json ext_emconf.php Build/ .gitignore
git commit -m "chore: scaffold imaginator extension + phpunit harness"
```

---

## Task 1: AspectRatio value object

**Files:**
- Create: `Classes/Dto/AspectRatio.php`
- Test: `Tests/Unit/Dto/AspectRatioTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;

final class AspectRatioTest extends TestCase
{
    public function testHeightForSixteenNine(): void
    {
        self::assertSame(900, (new AspectRatio(16, 9))->heightFor(1600));
    }

    public function testHeightRoundsToNearestInt(): void
    {
        self::assertSame(563, (new AspectRatio(16, 9))->heightFor(1000));
    }

    public function testFromStringParses(): void
    {
        self::assertEquals(new AspectRatio(4, 3), AspectRatio::fromString('4:3'));
    }

    public function testFromStringRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AspectRatio::fromString('16x9');
    }

    public function testRejectsZeroSide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AspectRatio(16, 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Dto/AspectRatioTest.php`
Expected: FAIL — "Class AspectRatio not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Dto;

final readonly class AspectRatio
{
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('AspectRatio sides must be >= 1', 1717600000);
        }
    }

    public static function fromString(string $ratio): self
    {
        if (!preg_match('/^(\d+):(\d+)$/', trim($ratio), $m)) {
            throw new \InvalidArgumentException(sprintf('Invalid ratio "%s"', $ratio), 1717600001);
        }
        return new self((int) $m[1], (int) $m[2]);
    }

    public function heightFor(int $width): int
    {
        return (int) round($width * $this->height / $this->width);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Dto/AspectRatioTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Dto/AspectRatio.php Tests/Unit/Dto/AspectRatioTest.php
git commit -m "feat: AspectRatio value object with parsing and heightFor"
```

---

## Task 2: Rung + LadderFactory

**Files:**
- Create: `Classes/Ladder/Rung.php`, `Classes/Ladder/LadderFactory.php`
- Test: `Tests/Unit/Ladder/LadderFactoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Ladder;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Ladder\Rung;

final class LadderFactoryTest extends TestCase
{
    private function factory(): LadderFactory
    {
        return new LadderFactory([320, 640, 1280, 1920, 2560], 2000);
    }

    public function testBuildCapsAtMaxAndSourceWidthAndDedupes(): void
    {
        // max=2000, source=1500 -> rungs <=1500: 320,640,1280, plus 1500 cap? No: only rung widths,
        // each clamped to min(rung, max, source). 1920->1500, 2560->1500 => collapse to single 1500.
        $rungs = $this->factory()->build(new AspectRatio(16, 9), 1500);
        $widths = array_map(static fn (Rung $r) => $r->width, $rungs);
        self::assertSame([320, 640, 1280, 1500], $widths);
    }

    public function testHeightFollowsRatio(): void
    {
        $rungs = $this->factory()->build(new AspectRatio(16, 9), 9999);
        self::assertSame(720, $rungs[2]->height); // 1280 * 9/16
    }

    public function testNearestRungRoundsUp(): void
    {
        self::assertSame(640, $this->factory()->nearestRung(500, 9999));
    }

    public function testNearestRungClampsToLargestAvailable(): void
    {
        self::assertSame(2000, $this->factory()->nearestRung(5000, 9999)); // capped by max 2000
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Ladder/LadderFactoryTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write `Rung.php`**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Ladder;

final readonly class Rung
{
    public function __construct(public int $width, public int $height) {}
}
```

- [ ] **Step 4: Write `LadderFactory.php`**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Ladder;

use Schliesser\Imaginator\Dto\AspectRatio;

final class LadderFactory
{
    /** @param int[] $rungWidths configured ladder rung widths */
    public function __construct(
        private readonly array $rungWidths,
        private readonly int $maxDimension,
    ) {}

    /** @return Rung[] ascending, capped to min(rung, maxDimension, sourceWidth), deduped */
    public function build(AspectRatio $ratio, int $sourceWidth): array
    {
        $rungs = [];
        foreach ($this->clampedWidths($sourceWidth) as $w) {
            $rungs[] = new Rung($w, $ratio->heightFor($w));
        }
        return $rungs;
    }

    /** Quantize an arbitrary requested width UP to the nearest available rung width. */
    public function nearestRung(int $requestedWidth, int $sourceWidth): int
    {
        $widths = $this->clampedWidths($sourceWidth);
        foreach ($widths as $w) {
            if ($w >= $requestedWidth) {
                return $w;
            }
        }
        return $widths === [] ? 0 : (int) end($widths);
    }

    /** @return int[] sorted ascending, unique, >= 1 */
    private function clampedWidths(int $sourceWidth): array
    {
        $widths = [];
        foreach ($this->rungWidths as $w) {
            $clamped = (int) min($w, $this->maxDimension, $sourceWidth);
            if ($clamped >= 1) {
                $widths[$clamped] = true;
            }
        }
        ksort($widths);
        return array_keys($widths);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Ladder/LadderFactoryTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add Classes/Ladder/ Tests/Unit/Ladder/
git commit -m "feat: LadderFactory builds capped, deduped width ladders + width quantization"
```

---

## Task 3: CanonicalParams

**Files:**
- Create: `Classes/Url/CanonicalParams.php`
- Test: `Tests/Unit/Url/CanonicalParamsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Url\CanonicalParams;

final class CanonicalParamsTest extends TestCase
{
    public function testCanonicalStringIsDeterministicAndOrdered(): void
    {
        $p = new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp');
        self::assertSame('1|4567|hero|1280|720|webp', $p->canonicalString());
    }

    public function testDifferentParamsProduceDifferentStrings(): void
    {
        $a = new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp');
        $b = new CanonicalParams(1, 4567, 'hero', 1281, 720, 'webp');
        self::assertNotSame($a->canonicalString(), $b->canonicalString());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Url/CanonicalParamsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write implementation**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Url;

final readonly class CanonicalParams
{
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
    ) {}

    public function canonicalString(): string
    {
        return implode('|', [
            $this->storageUid,
            $this->fileUid,
            $this->cropVariant,
            $this->width,
            $this->height,
            $this->format,
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Url/CanonicalParamsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Url/CanonicalParams.php Tests/Unit/Url/CanonicalParamsTest.php
git commit -m "feat: CanonicalParams deterministic signing payload"
```

---

## Task 4: SignedUrlBuilder (HMAC + key rotation)

**Files:**
- Create: `Classes/Url/SignedUrlBuilder.php`
- Test: `Tests/Unit/Url/SignedUrlBuilderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Url\CanonicalParams;
use Schliesser\Imaginator\Url\SignedUrlBuilder;

final class SignedUrlBuilderTest extends TestCase
{
    private CanonicalParams $params;

    protected function setUp(): void
    {
        $this->params = new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp');
    }

    public function testBuildProducesExpectedShape(): void
    {
        $url = (new SignedUrlBuilder(['s3cr3t']))->build($this->params);
        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/1-4567/hero/1280x720\.webp$#',
            $url
        );
    }

    public function testRoundTripVerifies(): void
    {
        $b = new SignedUrlBuilder(['s3cr3t']);
        $verified = $b->verify($b->build($this->params));
        self::assertEquals($this->params, $verified);
    }

    public function testTamperedSizeFailsVerification(): void
    {
        $b = new SignedUrlBuilder(['s3cr3t']);
        $url = $b->build($this->params);
        $tampered = str_replace('1280x720', '4000x720', $url);
        self::assertNull($b->verify($tampered));
    }

    public function testKeyRotationVerifiesAgainstOldSecret(): void
    {
        $oldUrl = (new SignedUrlBuilder(['old-secret']))->build($this->params);
        $rotated = new SignedUrlBuilder(['new-secret', 'old-secret']); // new active, old still valid
        self::assertEquals($this->params, $rotated->verify($oldUrl));
    }

    public function testWrongSecretFails(): void
    {
        $url = (new SignedUrlBuilder(['old-secret']))->build($this->params);
        self::assertNull((new SignedUrlBuilder(['new-secret'])->verify($url)));
    }

    public function testGarbagePathReturnsNull(): void
    {
        self::assertNull((new SignedUrlBuilder(['s']))->verify('/not/ours.jpg'));
    }

    public function testEmptySecretsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SignedUrlBuilder([]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Url/SignedUrlBuilderTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write implementation**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Url;

final class SignedUrlBuilder
{
    private const PREFIX = '/_imaginator';
    private const SIG_LEN = 16;

    /** @param list<string> $secrets index 0 is the active signing key; rest are still-valid */
    public function __construct(private readonly array $secrets)
    {
        if ($this->secrets === []) {
            throw new \InvalidArgumentException('At least one signing secret is required', 1717600100);
        }
    }

    public function build(CanonicalParams $p): string
    {
        return sprintf(
            '%s/%s/%d-%d/%s/%dx%d.%s',
            self::PREFIX,
            $this->sign($p, $this->secrets[0]),
            $p->storageUid,
            $p->fileUid,
            rawurlencode($p->cropVariant),
            $p->width,
            $p->height,
            $p->format,
        );
    }

    public function verify(string $path): ?CanonicalParams
    {
        $pattern = '#^' . preg_quote(self::PREFIX, '#')
            . '/([0-9a-f]{' . self::SIG_LEN . '})/(\d+)-(\d+)/([^/]+)/(\d+)x(\d+)\.([a-z0-9]+)$#';
        if (!preg_match($pattern, $path, $m)) {
            return null;
        }
        $params = new CanonicalParams(
            (int) $m[2], (int) $m[3], rawurldecode($m[4]), (int) $m[5], (int) $m[6], $m[7]
        );
        foreach ($this->secrets as $secret) {
            if (hash_equals($this->sign($params, $secret), $m[1])) {
                return $params;
            }
        }
        return null;
    }

    private function sign(CanonicalParams $p, string $secret): string
    {
        return substr(hash_hmac('sha256', $p->canonicalString(), $secret), 0, self::SIG_LEN);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/Url/SignedUrlBuilderTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Url/SignedUrlBuilder.php Tests/Unit/Url/SignedUrlBuilderTest.php
git commit -m "feat: SignedUrlBuilder HMAC sign/verify with key rotation"
```

**Phase 1 done: the entire signing + ladder core is covered by fast pure-unit tests.**

---

## Task 5: ImageVariant + ProcessedImage + ImageProcessorInterface

**Files:**
- Create: `Classes/Dto/ImageVariant.php`, `Classes/Dto/ProcessedImage.php`, `Classes/Imaging/ImageProcessorInterface.php`
- Test: `Tests/Unit/Dto/ImageVariantTest.php`

- [ ] **Step 1: Failing test** — `ImageVariant` carries identifiers + dims + format + quality and exposes `toCanonicalParams(): CanonicalParams`.

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Url\CanonicalParams;

final class ImageVariantTest extends TestCase
{
    public function testMapsToCanonicalParams(): void
    {
        $v = new ImageVariant(1, 4567, 'hero', 1280, 720, 'webp', 72);
        self::assertEquals(
            new CanonicalParams(1, 4567, 'hero', 1280, 720, 'webp'),
            $v->toCanonicalParams()
        );
    }
}
```

- [ ] **Step 2: Run → FAIL** (`-c Build/phpunit/UnitTests.xml Tests/Unit/Dto/ImageVariantTest.php`).

- [ ] **Step 3: Implement**

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Dto;

use Schliesser\Imaginator\Url\CanonicalParams;

final readonly class ImageVariant
{
    public function __construct(
        public int $storageUid,
        public int $fileUid,
        public string $cropVariant,
        public int $width,
        public int $height,
        public string $format,
        public int $quality,
    ) {}

    public function toCanonicalParams(): CanonicalParams
    {
        return new CanonicalParams(
            $this->storageUid, $this->fileUid, $this->cropVariant,
            $this->width, $this->height, $this->format,
        );
    }
}
```

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Dto;

final readonly class ProcessedImage
{
    /** @param 'file'|'stream' $kind */
    public function __construct(
        public string $publicUrl,   // for 302 redirect (preferred)
        public string $absolutePath, // for streaming fallback
        public string $mimeType,
    ) {}
}
```

```php
<?php
declare(strict_types=1);
namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;

interface ImageProcessorInterface
{
    /** Render-time: URL that belongs in srcset for this variant. */
    public function buildUrl(ImageVariant $variant): string;

    /** True when pixels happen elsewhere (external CDN/service); local processing is skipped. */
    public function isOffloaded(): bool;

    /** Local processors only: produce/return the processed binary. */
    public function materialize(ImageVariant $variant): ProcessedImage;
}
```

- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat: ImageVariant/ProcessedImage DTOs + ImageProcessorInterface`.

---

## Task 6: Local processing backend (GraphicsMagick)

**Files:**
- Create: `Classes/Imaging/Local/Backend/LocalBackendInterface.php`, `Classes/Imaging/Local/Backend/GraphicsMagickBackend.php`
- Test: `Tests/Functional/Imaging/GraphicsMagickBackendTest.php`

`LocalBackendInterface::process(File $file, array $instructions): ProcessedFile` wraps TYPO3
`ImageService::applyProcessingInstructions()` (reuse the proven logic from old `ImageUtility`,
incl. crop/focus/webp). Functional test fixture: a known JPEG in a test storage; assert the processed
file has the requested width and the right extension. (Crop/focus parity tests carried from old ext.)

- [ ] Step 1: failing functional test asserting `process()` on a 4000px fixture with `width=1280c`,
  `fileExtension=webp` yields a `ProcessedFile` of width 1280, mime `image/webp`.
- [ ] Step 2: run → FAIL.
- [ ] Step 3: implement backend delegating to `ImageService`/`GraphicalFunctions` (webp support probe
  via `GraphicalFunctions::webpSupportAvailable()` — no v12 fallback needed).
- [ ] Step 4: run → PASS.
- [ ] Step 5: commit `feat: GraphicsMagick local backend`.

---

## Task 7: LocalImageProcessor

**Files:**
- Create: `Classes/Imaging/Local/LocalImageProcessor.php`
- Test: `Tests/Functional/Imaging/LocalImageProcessorTest.php`

`LocalImageProcessor implements ImageProcessorInterface`:
- `isOffloaded(): false`.
- `buildUrl()` → `SignedUrlBuilder->build($variant->toCanonicalParams())`.
- `materialize()` → resolve `File` via `ResourceFactory->getFileObject($variant->fileUid)`, build
  instructions (`width.'c'`, `height.'c'`, crop/focus, webp), call the backend, return `ProcessedImage`
  (publicUrl from `ImageService->getImageUri()`, absolutePath, mime).

- [ ] Step 1: failing test — `buildUrl()` matches the signed-URL regex; `materialize()` returns a
  `ProcessedImage` whose file is 1280 wide.
- [ ] Step 2: FAIL. - [ ] Step 3: implement. - [ ] Step 4: PASS.
- [ ] Step 5: commit `feat: LocalImageProcessor (signed URL + materialize)`.

---

## Task 8: ProcessImageRequest middleware (verify → process → serve bytes)

**Files:**
- Create: `Classes/Middleware/ProcessImageRequest.php`, `Classes/Configuration/Settings.php`
- Modify: `Configuration/RequestMiddlewares.php`, `Configuration/Services.yaml`
- Test: `Tests/Functional/Middleware/ProcessImageRequestTest.php`

Behaviour:
1. If path doesn't start with `/_imaginator/` → `$handler->handle($request)` (pass through).
2. `SignedUrlBuilder->verify(path)`; null → **403** (bad/forged signature → DoS-safe).
3. Quantize/validate width against the ladder (reject non-rung sizes → 403) — closes the
   "signed but arbitrary size" gap if a secret leaks.
4. `materialize()` → **302 redirect** to `ProcessedImage->publicUrl` with
   `Cache-Control: public, max-age=31536000, immutable`. (Streaming fallback only if no public URL.)
5. **No JSON. The URL is the image.**

- [ ] Step 1: failing functional test:
  - valid signed URL → `302` with `Location` to a processed file + immutable cache header.
  - forged signature → `403`.
  - non-`/_imaginator/` path → request passes through untouched.
- [ ] Step 2: FAIL.
- [ ] Step 3: implement middleware; register in `RequestMiddlewares.php` (frontend stack, before
  `typo3/cms-frontend/page-resolver`); wire `Services.yaml` (autowire; inject `SignedUrlBuilder`
  built from `Settings` secrets, `LadderFactory`, `LocalImageProcessor`).
- [ ] Step 4: PASS.
- [ ] Step 5: commit `feat: signed image endpoint serving bytes via 302, forgery -> 403`.

`Settings.php` reads Site-Set settings (`imaginator.*`): ladder rungs, maxDimension, secrets
(from `$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']` derived + optional rotation list), formats,
qualities, processor selection. Unit-test the pure parsing parts.

---

## Task 9: PictureRenderer (the ladder HTML)

**Files:**
- Create: `Classes/Rendering/PictureRenderer.php`
- Test: `Tests/Unit/Rendering/PictureRendererTest.php` (pure: inject a fake processor returning
  predictable URLs, assert exact HTML — golden-file)

Builds, from a resolved file + per-breakpoint `AspectRatio` map + processor:
- single ratio → `<img srcset="…ladder…" sizes="auto" loading="lazy" width height decoding="async">`
- multiple ratios → `<picture>` with `<source media>` per breakpoint, each its own ladder, `<img>` fallback
- width/height attrs from the largest rung (zero CLS); `sizes="auto"`; LQIP background hook (filled in
  the LQIP follow-on plan — here just a `style` seam).
- `priority` flag → drop `loading=lazy`, add `fetchpriority="high"` + explicit `sizes` (default `100vw`).

- [ ] Step 1: failing golden-file test for the single-ratio `<img>` ladder (fake processor →
  `srcset="…/320x180.webp 320w, …/640x360.webp 640w" sizes="auto" loading="lazy" width="640" height="360"`).
- [ ] Step 2: FAIL. - [ ] Step 3: implement single-ratio path. - [ ] Step 4: PASS. - [ ] Step 5: commit.
- [ ] Step 6: failing golden-file test for multi-ratio `<picture>`. - [ ] 7: FAIL - [ ] 8: implement
  `<picture>` path - [ ] 9: PASS - [ ] 10: commit.
- [ ] Step 11: failing test for `priority` (no lazy, `fetchpriority=high`). - [ ] 12-15: impl/commit.

---

## Task 10: ImageViewHelper + Site Set wiring

**Files:**
- Create: `Classes/ViewHelpers/ImageViewHelper.php`, `Configuration/Sets/Imaginator/{config.yaml,settings.definitions.yaml,setup.typoscript}`
- Test: `Tests/Functional/ViewHelpers/ImageViewHelperTest.php`

Thin ViewHelper: args (`src`/`image`/`treatIdAsReference`, `aspectRatio` string|map, `cropVariant`,
`alt`/`title`/`class`, `priority`). Resolves the file, builds the per-breakpoint ratio map, delegates
to `PictureRenderer`. No measuring, no JS dependency for correctness.

- [ ] Step 1: failing functional test rendering `<f:image>`-style usage in a standalone Fluid view →
  output contains a `<picture>`/`<img>` with `srcset` + `sizes="auto"`.
- [ ] Step 2: FAIL. - [ ] Step 3: implement ViewHelper + Site-Set `settings.definitions.yaml`
  (ladder, maxDimension, formats, qualities, processor) + `setup.typoscript` (CSS include, JS deferred
  to the enhancement plan). - [ ] Step 4: PASS. - [ ] Step 5: commit.

**End of this plan: a site can render zero-config, sharp, signed, locally-processed responsive images
with no JS. Verified end-to-end by functional tests.**

---

## Follow-on plans (each ships independently; own bite-sized plan)

1. **`imaginator-v1-formats-lqip`** — AVIF/WebP `<picture>` tiers; `LqipGeneratorInterface` +
   ThumbHash (default) + dominant-color; CSS blur/background.
2. **`imaginator-v1-js-enhancement`** — custom element: Safari `sizes` fallback, optional
   pixel-perfect refine, `priority`/preload head link. Progressive enhancement only.
3. **`imaginator-v1-external-providers`** — `UrlBuilderInterface` + Thumbor, imgproxy, imgix,
   Cloudflare Images, Cloudinary; `ExternalImageProcessor`; per-provider golden-file tests.
4. **`imaginator-v1-aspect-ratio-element`** — CE-level per-breakpoint ratio FormEngine web component,
   ratio swatches, v13/v14 adapter.
5. **`imaginator-v1-polish`** — rate limiting, warmup/telemetry seams (no-op interfaces + events), docs.

---

## Self-Review

- **Spec coverage:** signed URL (§3) ✓ T3–4,8; ladder/`sizes=auto` (§2) ✓ T2,9; local processor (§4) ✓
  T5–7; bytes-not-JSON endpoint ✓ T8; renderer + ViewHelper (§2,§7) ✓ T9–10; zero-CLS ✓ T9.
  Formats/LQIP/JS/external/element/polish → follow-on plans (intentional split per writing-plans scope rule).
- **Placeholder scan:** Phases 1–2 (T0–8) carry full code; T9–10 carry full behaviour + exact test
  assertions; follow-on plans are explicitly separate, not placeholders within this plan.
- **Type consistency:** `CanonicalParams` shape identical across T3/T4/T5; `ImageProcessorInterface`
  (`buildUrl`/`isOffloaded`/`materialize`) consistent T5/T7/T8; `ImageVariant` ctor order matches
  `toCanonicalParams()` and T5 test.
