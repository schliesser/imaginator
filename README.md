# Imaginator

Zero-config responsive images for TYPO3. The integrator writes **no `sizes` and no per-image
breakpoints**: at render time the extension emits a real `<picture>`/`<img>` with a quantized
width-ladder `srcset` and `sizes="auto"`, so the browser's preload scanner fetches the
correctly-sized image in a single request.

**For editors.** A backend **aspect-ratio field** per content element lets editors pick the framing
once — **per breakpoint** — and get **uniform, consistent images** across every element of that type,
no more ragged grids from mismatched upload dimensions. Crop and focus area are honored, so the
chosen subject stays in frame at every size.

![Aspect-ratio field in the backend](Documentation/Images/AspectRatioField.png)

**For developers.** Drop one `<i:image>` and get sharp, perfectly-sized images on every device — no
hand-tuned `sizes`, no breakpoint lists, no layout shift (CLS). Stop shipping oversized images:
the width ladder bounds processing to a fixed set of sizes, so you serve fewer, smaller bytes and
score better Core Web Vitals (LCP/CLS) out of the box.

**Features.** Quantized width-ladder `srcset` + `sizes="auto"` · a single uniform output format
(**AVIF** by default, or **WebP**) · per-breakpoint **art direction** (crop variants + focus area honored) · **low-quality
placeholders** (ThumbHash, dominant-color, or none) · `priority` images get `fetchpriority="high"`
and skip lazy-loading · pluggable processing — classic **sync** on first request, **async** via a
signed middleware endpoint (default), or an **external processor** (e.g. imgproxy).

JavaScript is never required for sharpness — it's only a polyfill for Safari < 27.

## Requirements

- PHP **8.3+** (tested 8.3 / 8.4 / 8.5)
- TYPO3 **13.4 LTS** or **14.3 LTS**
- A working image processor (GraphicsMagick or ImageMagick — TYPO3's standard `GFX` settings) for
  local processing; alternatively an external processor (e.g. imgproxy)
- A non-empty `encryptionKey` (standard on every TYPO3 install — see *Signing* below)

## Installation

```bash
composer require schliesser/imaginator
```

A Composer install activates the extension automatically. Installing it is enough to use the
`<i:image>` ViewHelper — no Site Set or TypoScript is required. Configure it under
*Settings → Extension Configuration* (see below).

## Usage

Declare the ViewHelper namespace once per template and call `<i:image>`:

```html
<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"
      data-namespace-typo3-fluid="true">

    {# Simplest case: one ratio, renders an <img> with the full ladder #}
    <i:image image="{fileReference}" aspectRatio="16:9" alt="{fileReference.description}"/>

    {# By file UID or path #}
    <i:image src="{file.uid}" treatIdAsReference="0" aspectRatio="4:3" alt="A product"/>

    {# Art direction: per-breakpoint ratios render a <picture> with one <source media> each.
       The map is {breakpoint: ratio}; keys are preconfigured breakpoint aliases (see extension
       config) or a px min-width, with 0 / xs as the base. Tiers are mobile-first (min-width
       only); the base (min-width 0) tier becomes the <img> fallback, larger tiers become
       <source>s. #}
    <i:image image="{image}"
             aspectRatio="{0: '1:1', 'lg': '16:9'}"
             alt="This is an image"/>

    {# LCP / above-the-fold image: drops lazy-loading, adds fetchpriority="high"
       and an explicit sizes="100vw" #}
    <i:image image="{hero}" aspectRatio="16:9" alt="Hero" priority="1"/>
</html>
```

### Arguments

| Argument | Type | Default | Description |
|---|---|---|---|
| `image` | `File`/`FileReference` | – | FAL object (use this *or* `src`) |
| `src` | string | `''` | File **UID** or path (e.g. `fileadmin/img/x.jpg`) |
| `treatIdAsReference` | bool | `false` | Treat `src` as a `sys_file_reference` UID |
| `aspectRatio` | string \| map | `16:9` | `"W:H"`, or a `{breakpoint: "W:H"}` map for art direction |
| `cropVariant` | string | `default` | FAL crop variant to use |
| `alt` | string | `''` | Alternative text |
| `class` | string | – | CSS class on the `<img>` |
| `priority` | bool | `false` | Mark as the LCP image (see below) |

`width`/`height` are always emitted from the largest rung, so there is zero layout shift.

### Priority / LCP images

Set `priority="1"` on the above-the-fold (LCP) image. Imaginator then:

- drops `loading="lazy"` and adds `fetchpriority="high"` on the `<img>`;
- renders an explicit `sizes="100vw"` instead of `sizes="auto"`;
- adds a `<link rel="preload" as="image" imagesrcset="…" imagesizes="100vw" type="image/…"
  fetchpriority="high">` to the `<head>`, so the request is discoverable in the initial document
  before the body is parsed.

Only the most-preferred format is preloaded (gated by `type`, so other browsers don't
double-download), and its `imagesrcset`/`imagesizes` mirror the rendered tier exactly so the
browser reuses the preload. This satisfies Lighthouse's "LCP request is discoverable",
"not lazily loaded" and "fetchpriority should be applied" audits. Use it on **one** image per page.

### `sizes="auto"` and the Safari polyfill

Zero-config sizing relies on `sizes="auto"`, which lets the browser pick the right `srcset`
candidate from the laid-out width — no hand-written `sizes`, no per-image breakpoints. Chrome/Edge
(126+) and Firefox (150+) support it natively; **Safari and iOS Safari only from version 27**
(WebKit landed it in March 2026; Safari 27 ships in the 2026 cycle). On older Safari/iOS an
unsupported `sizes="auto"` falls back to `100vw`, so they fetch an oversized (but still sharp)
candidate. (`sizes="auto"` is spec-valid only on `loading="lazy"` images, which is exactly where
Imaginator emits it — priority/eager images get an explicit `sizes="100vw"` instead.)

To close that gap, whenever a processed `<i:image>` renders, Imaginator queues a tiny
progressive-enhancement script via the `AssetCollector` (head, `async`, registered once per page):
the vendored [Shopify/autosizes](https://github.com/Shopify/autosizes) polyfill (MIT,
`Resources/Public/JavaScript/frontend/autosizes.js`). It self-detects native support and backs off;
on Safari it measures the rendered width and writes a concrete pixel `sizes`, so the browser picks a
right-sized candidate. It is **never load-bearing** — sharpness comes from the server-rendered
ladder, so the page works fully with JavaScript disabled — and it only touches
`<img loading="lazy" sizes="auto">`, so **priority/LCP images are untouched** (they carry an explicit
`sizes="100vw"` and `loading="eager"`). Pages without any `<i:image>` ship no extra JS.

### Crop & focus areas

When an `image` (a FAL `FileReference`) is rendered, the editor's **crop variant** is resolved server-side:
it reads the reference's crop JSON and fits the requested ratio inside the **crop area**, centered on the
**focus area** — so the framing the editor chose is honored and **no crop geometry is exposed in the URL**.
The ladder is bounded by the crop area, so a tightly-cropped region is never upscaled. A `src` given as
a path or plain file uid (`f…`) has no crop data and is center-cropped to the ratio.

## Configuration

Configuration is **instance-wide Extension Configuration**. Edit it under *Settings → Extension
Configuration → imaginator* in the backend, or set it in `config/system/settings.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = [
    'lqip' => 'dominant-color',
    'ladder' => '320,640,960,1280,1920',
];
```

| Setting | Default | Description |
|---|---|---|
| `processor` | `local:async` | Image processor: `local:async` (signed endpoint + 302), `local:sync` (static `srcset`, no middleware) or `imgproxy` (offloaded) |
| `maxDimension` | `2000` | Largest image dimension in px; the ladder is capped to it |
| `ladder` | `320,420,560,740,980,1300,1720,2000` | Width-ladder rungs (comma-separated) |
| `format` | `avif` | Single uniform output format: `avif` or `webp` |
| `quality.avif` | `50` | AVIF quality (AVIF's scale sits lower than JPEG/WebP for the same perceived quality) |
| `quality.webp` | `72` | WebP quality |
| `lqip` | `thumbhash` | Low-quality placeholder: `thumbhash`, `dominant-color` or `none` |
| `secretsRotation` | – | Additional valid signing secrets (comma-separated) for key rotation |

The active signing secret is always derived from the global `encryptionKey`; `secretsRotation`
lets old secrets keep verifying while you rotate. Settings are global by design, so the render path
and the signed-endpoint verify path always agree (a per-site model would risk mismatched ladders →
spurious 403s).

### Content Security Policy

The placeholder is **not** rendered as an inline `style=""` attribute (those cannot carry a CSP
nonce). The `<img>` only gets a CSS class; the actual rule is registered through TYPO3's
`AssetCollector` and emitted as a `<style>` element, which TYPO3 nonces automatically when frontend
CSP is enabled. Identical placeholders are deduplicated to a single rule.

CSP directives you may need:

- **`thumbhash`** (default): the blurred preview is a `data:` background-image, so allow
  `img-src data:` (TYPO3's default frontend CSP already does).
- **`dominant-color`**: a plain background color — needs nothing beyond the nonced `<style>`.
- **`none`**: no placeholder, no extra directive.

## The local:async image endpoint

Each async candidate URL has one of these forms:

```
reference: /_imaginator/{16-hex-signature}/r{referenceUid}/{cropVariant}/{width}x{height}.{ext}
file:      /_imaginator/{16-hex-signature}/f{fileUid}/{cropVariant}/{width}x{height}.{ext}
```

The uid is site-unique, so no storage segment is needed. A PSR-15 middleware verifies the signature,
re-checks the width against the ladder, processes the image and **302-redirects to the processed
file** with `Cache-Control: public, max-age=31536000, immutable`. A forged or tampered URL returns
**403**, and only ladder-quantized widths are ever processed — so the endpoint cannot be abused to
exhaust the server with arbitrary sizes.

If the derivative already exists at render time, the static processed-file URL is written straight
into the markup — so the web server serves it directly, with no middleware/PHP roundtrip.

The signing secret is derived automatically from
`$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']`. No configuration is needed, but note that
**changing `encryptionKey` invalidates previously generated URLs** (they will re-sign on the next
render).

## Contributing

A reproducible DDEV demo instance, the full test workflow, and the coding/PR conventions are
documented in [CONTRIBUTING.md](CONTRIBUTING.md). In short:

```bash
ddev start && ddev setup && ddev demo   # demo at https://imaginator.ddev.site/ (admin / Password.1)
ddev test all && ddev lint
```

## License

GPL-2.0-or-later.
