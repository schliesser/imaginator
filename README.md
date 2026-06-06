# Imaginator

Zero-config responsive images for TYPO3. The integrator writes **no `sizes` and no per-image
breakpoints**: at render time the extension emits a real `<picture>`/`<img>` with a quantized
width-ladder `srcset` and `sizes="auto"`, so the browser's preload scanner fetches the
correctly-sized image in a single request. Candidate URLs are HMAC-signed and served as real
image bytes by a local processing endpoint. JavaScript is never required for sharpness.

> **Status.** Local (GraphicsMagick/ImageMagick) processing, the `<i:image>` ViewHelper, stacked
> **AVIF + WebP `<picture>` tiers**, and **low-quality placeholders** (ThumbHash by default,
> dominant-colour and none as options) work end-to-end. The JS enhancement layer, external CDN
> providers (Thumbor/imgproxy/imgix/Cloudflare/Cloudinary), the aspect-ratio backend element, and
> per-site overrides of the settings below are planned follow-ups (see `docs/`). Full TYPO3 reST
> documentation will follow.

## Requirements

- PHP **8.3+** (tested 8.3 / 8.4 / 8.5)
- TYPO3 **13.4 LTS** or **14.x**
- A working image processor (GraphicsMagick or ImageMagick — TYPO3's standard `GFX` settings)
- A non-empty `encryptionKey` (standard on every TYPO3 install — see *Signing* below)

## Installation

```bash
composer require schliesser/imaginator
```

Activate the extension (`vendor/bin/typo3 extension:setup`, or via the Extensions module).
Installing the extension is enough to use the `<i:image>` ViewHelper.

To expose the configuration settings in the *Sites* backend module, add the Imaginator
Site Set to your site (or to your sitepackage set's dependencies):

```yaml
# config/sites/<identifier>/config.yaml
dependencies:
  - schliesser/imaginator
```

## Usage

Declare the ViewHelper namespace once per template and call `<i:image>`:

```html
<html xmlns:i="http://typo3.org/ns/Schliesser/Imaginator/ViewHelpers"
      data-namespace-typo3-fluid="true">

    {# Simplest case: one ratio, renders an <img> with the full ladder #}
    <i:image image="{fileReference}" aspectRatio="16:9" alt="{fileReference.description}"/>

    {# By file UID or path #}
    <i:image src="{file.uid}" treatIdAsReference="0" aspectRatio="4:3" alt="A product"/>

    {# Art direction: per-breakpoint ratios render a <picture> with one <source> each.
       The map is {mediaQuery: ratio}; the entry whose media is empty (or the last entry)
       becomes the <img> fallback. #}
    <i:image image="{hero}"
             aspectRatio="{'(min-width:992px)': '16:9', '(max-width:991px)': '1:1'}"
             alt="Hero"/>

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
| `aspectRatio` | string \| map | `16:9` | `"W:H"`, or a `{mediaQuery: "W:H"}` map for art direction |
| `cropVariant` | string | `default` | FAL crop variant to use |
| `alt` | string | `''` | Alternative text |
| `class` | string | – | CSS class on the `<img>` |
| `priority` | bool | `false` | Mark as LCP image (eager + `fetchpriority="high"`) |

`width`/`height` are always emitted from the largest rung, so there is zero layout shift.

## The image endpoint & signing

Each candidate URL has the form:

```
/_imaginator/{16-hex-signature}/{storageUid}-{fileUid}/{cropVariant}/{width}x{height}.{ext}
```

A PSR-15 middleware verifies the signature, re-checks the width against the ladder, processes the
image and **302-redirects to the processed file** with `Cache-Control: public, max-age=31536000,
immutable`. A forged or tampered URL returns **403**, and only ladder-quantized widths are ever
processed — so the endpoint cannot be abused to exhaust the server with arbitrary sizes.

The signing secret is derived automatically from
`$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']`. No configuration is needed, but note that
**changing `encryptionKey` invalidates previously generated URLs** (they will re-sign on the next
render). Key rotation (keeping old secrets valid during a change) is supported in the signing core
and will be exposed as a setting in a later release.

## Configuration

The Imaginator Site Set defines these settings under the `imaginator.*` namespace:

| Setting | Default | Description |
|---|---|---|
| `imaginator.processor` | `local` | Processing backend (v1: `local` only) |
| `imaginator.maxDimension` | `2000` | Largest image dimension in px; the ladder is capped to it |
| `imaginator.ladder` | `320,420,560,740,980,1300,1720,2000` | Width-ladder rungs (comma-separated) |
| `imaginator.formats` | `avif,webp` | Negotiated formats, most-preferred first |
| `imaginator.quality.avif` | `50` | AVIF quality (AVIF's scale sits lower than JPEG/WebP for the same perceived quality) |
| `imaginator.quality.webp` | `72` | WebP quality |
| `imaginator.lqip` | `thumbhash` | Low-quality placeholder: `thumbhash`, `dominant-color` or `none` |

> **Note.** The renderer currently applies the **defaults above** (so AVIF+WebP tiers and a
> ThumbHash placeholder are emitted out of the box). Reading per-site overrides of these settings
> from the Site Set is wired in a follow-up; until then, changing the values has no effect.

## Local development & manual testing

A reproducible DDEV demo instance is included:

```bash
ddev start
ddev composer install
ddev exec bash Build/Scripts/setup-demo-instance.sh
```

- Frontend (demo page): `https://imaginator.ddev.site/`
- Backend: `https://imaginator.ddev.site/typo3/` — `admin` / `Imaginator.2026!`

### Tests

```bash
ddev exec .Build/bin/phpunit -c Build/phpunit/UnitTests.xml
ddev exec "typo3DatabaseDriver=mysqli typo3DatabaseHost=db typo3DatabaseName=db \
  typo3DatabaseUsername=root typo3DatabasePassword=root \
  .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml"
```

## License

GPL-2.0-or-later.
