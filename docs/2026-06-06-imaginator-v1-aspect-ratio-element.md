# Imaginator — Aspect-Ratio Backend Element Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.
>
> **Revised 2026-06-07** with the locked decisions below; supersedes the earlier draft.

**Goal:** A custom FormEngine element where the editor sets **per-breakpoint aspect ratios at the
content-element level** (applies to all media in the CE). Stored as JSON, resolved to the
`{media: ratio}` map the `ImageViewHelper`/`PictureRenderer` already consume.

## Locked decisions (2026-06-07)

- **UI = custom FormEngine web-component element** (not plain select fields). Breakpoint rows + ratio
  swatches, pictureino-style.
- **Breakpoints (set + min-widths) = Extension Configuration** (instance-wide). One design-system
  constant. `xs` has min-width `0` and is the **base** ratio that bubbles up to larger viewports until
  a larger breakpoint overrides it.
- **Selectable ratios = TCA, per element.** The element's `allowedRatios` config lists the offered
  ratios; restrict per `CType` via `types[...].columnsOverrides`. (Hero → `16:9,21:9`; default → add
  portraits.)
- **Storage = one JSON column** on `tt_content`, value `{ "<breakpoint>": "<ratio>" }`, e.g.
  `{"xs":"1:1","md":"4:3","lg":"16:9"}`. A breakpoint absent (or `auto`) inherits the next-smaller.
- **Replaces** the interim single `tt_content.tx_imaginator_aspect_ratio` field shipped earlier.

**Architecture:** TCA `type=user, renderType=imaginatorAspectRatios` → PHP node `AspectRatiosElement`
renders a web-component host (`<imaginator-aspect-ratios>`) carrying `data-breakpoints` (from ext
config), `data-allowed` (from TCA), `data-value` (current JSON) + a hidden input. The TS web component
renders one row per breakpoint with an allowed-ratio chooser + live swatch and serializes the JSON. At
render time a `RatioMapResolver` turns the JSON + ext-config breakpoint widths into the
`{media: ratio}` map; a content-element DataProcessor feeds it to `<i:image aspectRatio="{map}">`.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14 FormEngine, TypeScript + Vitest (jsdom), Web Components,
importmap ES modules.

**Depends on:** foundation (`AspectRatio`, `PictureRenderer`, `ImageViewHelper` — already accepts a
`{media: ratio}` map), formats-lqip, crop/focus (`CropResolver`).

---

## File Structure
- `ext_conf_template.txt` — add `breakpoints` (e.g. `xs:0,sm:576,md:768,lg:992,xl:1200`).
- `Classes/Configuration/Settings.php` — parse `breakpoints` to an ordered `[key => minWidth]` list.
- `Classes/Dto/Breakpoint.php` — `{key, minWidth}` value object.
- `Classes/Service/RatioMapResolver.php` — `(json, Breakpoint[]) -> array{media:?string, ratio:?AspectRatio}` list.
- `Configuration/TCA/Overrides/tt_content.php` — replace the single field with `tx_imaginator_aspect_ratios`
  (`type=user`, `renderType=imaginatorAspectRatios`, `allowedRatios`), palette/showitem, a Hero-style
  `columnsOverrides` example.
- `ext_tables.sql` — `tx_imaginator_aspect_ratios text`; drop the old `tx_imaginator_aspect_ratio`.
- `Classes/Backend/Form/Element/AspectRatiosElement.php` — PHP node.
- `Classes/Backend/Form/AspectRatiosElementRegistration.php` + `ext_localconf.php` — v13/v14 node-registry adapter.
- `Configuration/JavaScriptModules.php` — register the backend ES module (importmap).
- `Resources/Public/JavaScript/backend/aspect-ratios.js` (+ `.ts` source) — web component.
- `Resources/Public/Css/backend/aspect-ratios.css` — rows + swatch styling.
- `Build/jsconfig` (vitest + tsc devDeps) — JS toolchain.
- Tests: Unit (Settings breakpoints, RatioMapResolver), Functional (TCA registration, node render,
  CE-ratio render integration), JS (Vitest component).

---

## Task 1: Breakpoints in Extension Configuration

**Files:** `ext_conf_template.txt`, `Classes/Configuration/Settings.php`, `Classes/Dto/Breakpoint.php`
Test: extend `Tests/Unit/Configuration/SettingsTest.php`

- [ ] **Step 1: Failing unit test** — `Settings::fromArray(['breakpoints' => 'xs:0,sm:576,lg:992'], 'k')`
  exposes `->breakpoints` as an ordered `Breakpoint[]` sorted by minWidth: `[xs=>0, sm=>576, lg=>992]`;
  empty/invalid → a sensible default set.
- [ ] **Step 2: FAIL** → **Step 3: Implement** `Breakpoint` readonly (`key`, `minWidth`); parse
  `key:px` comma list in `Settings`; add `breakpoints` to `ext_conf_template.txt`
  (`# cat=imaginator/breakpoints; type=string; default=xs:0,sm:576,md:768,lg:992,xl:1200`); map it in
  `SettingsFactory::rawConfiguration()`.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: breakpoints in extension configuration`.

---

## Task 2: RatioMapResolver (JSON + breakpoints -> media/ratio map)

**Files:** `Classes/Service/RatioMapResolver.php`
Test: `Tests/Unit/Service/RatioMapResolverTest.php` (pure)

- [ ] **Step 1: Failing test**
  - `fromJson('{"xs":"1:1","lg":"16:9"}', $breakpoints)` returns, ordered largest-first for `<picture>`:
    `[{media:'(min-width:992px)', ratio:16:9}, {media:null, ratio:1:1}]` (xs → `media:null` = the `<img>` base).
  - A breakpoint not in the JSON is omitted (inherits the next-smaller via native `<picture>`).
  - `"auto"` or unknown ratio at a breakpoint → omit that breakpoint (inherit).
  - Empty/invalid JSON → `[]` (caller falls back to the ViewHelper's native-ratio behaviour).
- [ ] **Step 2: FAIL** → **Step 3: Implement** — decode JSON object, intersect with the configured
  breakpoints, map ratio via `AspectRatio::fromString()`, build `BreakpointRatio`-shaped entries
  (`media` = `null` for the `xs`/min-0 breakpoint else `(min-width:{px}px)`), sort largest-first.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: RatioMapResolver (CE ratios -> media/ratio map)`.

> Output is the same `BreakpointRatio[]` the ViewHelper builds from an `aspectRatio` map, so the
> renderer needs no change.

---

## Task 3: TCA field + DB + renderType registration (v13/v14)

**Files:** `Configuration/TCA/Overrides/tt_content.php`, `ext_tables.sql`,
`Classes/Backend/Form/AspectRatiosElementRegistration.php`, `ext_localconf.php`
Test: `Tests/Functional/Backend/TcaRegistrationTest.php`

- [ ] **Step 1: Failing functional test** — `$GLOBALS['TCA']['tt_content']['columns']
  ['tx_imaginator_aspect_ratios']['config']['renderType']` equals `imaginatorAspectRatios`; the node
  registry resolves that renderType to `AspectRatiosElement::class`; the old `tx_imaginator_aspect_ratio`
  column is gone.
- [ ] **Step 2: FAIL** → **Step 3: Implement** — replace the interim column with
  `tx_imaginator_aspect_ratios` (`type=user`, `renderType=imaginatorAspectRatios`,
  `allowedRatios='1:1,4:3,3:2,16:9,21:9,9:16,2:3,3:4'`); `ext_tables.sql` add `text` column + drop old;
  a `register()` adapter handling **both** v13 and v14 FormEngine node-registry APIs, called from
  `ext_localconf.php`.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: TCA + DB + renderType for aspect-ratios element`.

---

## Task 4: AspectRatiosElement PHP node

**Files:** `Classes/Backend/Form/Element/AspectRatiosElement.php`
Test: `Tests/Functional/Backend/AspectRatiosElementTest.php`

- [ ] **Step 1: Failing functional test** — `render()` html contains `<imaginator-aspect-ratios>` with
  `data-breakpoints` (ext-config breakpoints JSON), `data-allowed` (the field's `allowedRatios`),
  `data-value` (current field value), and the hidden input named for the field; `result['javaScriptModules']`
  registers the backend module.
- [ ] **Step 2: FAIL** → **Step 3: Implement** — node reads breakpoints from `SettingsFactory`,
  `allowedRatios` from `fieldConf.config`, current value from `parameterArray.itemFormElValue`; renders
  the host + hidden input; registers the JS module (`JavaScriptModuleInstruction`) + CSS.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: AspectRatiosElement renders web-component host`.

---

## Task 5: Web component (rows, allowed ratios, swatch)

**Files:** `Resources/Public/JavaScript/backend/aspect-ratios.ts` (build to `.js`),
`Resources/Public/Css/backend/aspect-ratios.css`, `Configuration/JavaScriptModules.php`,
`composer.json`/`package.json` (vitest, typescript devDeps)
Test: `Tests/JavaScript/aspect-ratios.test.ts` (Vitest + jsdom)

- [ ] **Step 1: Failing test** — given `data-breakpoints=[{key:'xs',minWidth:0},{key:'lg',minWidth:992}]`,
  `data-allowed='1:1,16:9'`, empty value: renders 2 rows; each row offers only `1:1`, `16:9` + an
  "inherit" option and a live **swatch** (`aspect-ratio: 16/9`); selecting `16:9` on `lg` serializes the
  hidden input to `{"lg":"16:9"}`; "inherit" removes the key.
- [ ] **Step 2: FAIL** (`npx vitest run aspect-ratios`) → **Step 3: Implement**
  `class AspectRatiosElement extends HTMLElement`: parse data attrs, render one row per breakpoint with
  an allowed-ratio chooser (buttons/select) + inherit + swatch box; on change rebuild the `{bp:ratio}`
  object and write the hidden input; dispatch FormEngine change. Build TS→JS.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: aspect-ratios web component (per-breakpoint rows + swatch)`.

---

## Task 6: Render integration (CE ratios drive all media)

**Files:** `Classes/DataProcessing/AspectRatioProcessor.php` (or extend the demo path),
`Classes/ViewHelpers/ImageViewHelper.php` (precedence)
Test: `Tests/Functional/Rendering/CeRatioIntegrationTest.php`

- [ ] **Step 1: Failing functional test** — a CE whose `tx_imaginator_aspect_ratios` holds
  `{"xs":"1:1","lg":"16:9"}` renders, for **every** media reference, a `<picture>` with a
  `lg` `<source media="(min-width:992px)">` ladder at 16:9 and the `<img>` base at 1:1.
- [ ] **Step 2: FAIL** → **Step 3: Implement** — a DataProcessor resolves the CE JSON via
  `RatioMapResolver` (+ ext-config breakpoints) and exposes the `BreakpointRatio[]` to the template;
  `<i:image>` accepts it (new `breakpoints`/`aspectRatio` map arg). **Precedence:** explicit
  `aspectRatio` on the tag > CE ratios > native/crop ratio.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: CE per-breakpoint ratios applied to all media`.

---

## Task 7: TCA per-element example + demo

- [ ] Add a `columnsOverrides` example (e.g. a Hero `CType`/content type → `allowedRatios='16:9,21:9'`).
- [ ] Update the demo: give the content elements per-breakpoint ratios (e.g. `xs:1:1, md:4:3, lg:16:9`)
  via the setup script; confirm the live `<picture>` switches ratio per breakpoint.
- [ ] Commit `docs: demo + Hero columnsOverrides for per-breakpoint ratios`.

---

## v13/v14 notes
- FormEngine node registration differs (v14 changed the registry surface). Task 3's
  `AspectRatiosElementRegistration` isolates it; the web component is version-agnostic.
- Backend ES modules via importmap (`Configuration/JavaScriptModules.php`) in both v13/v14.
- Run the functional suite on both v13.4 and v14.3 matrices.

## Self-Review
- **Spec coverage:** CE-level per-breakpoint ratio (design §8) ✓ T2–6; breakpoints in ext config (locked)
  ✓ T1; allowed ratios per TCA element (locked) ✓ T3; xs base bubbling ✓ T2; web-component UI + swatch,
  no image preview ✓ T4–5; data feeds renderer for all media ✓ T6; crop/focus stays native FAL (not in
  this element) ✓ by omission.
- **Type consistency:** storage `{bp:ratio}` JSON identical across T3/T5/T6; `RatioMapResolver` output =
  `BreakpointRatio[]` consumed unchanged by `PictureRenderer`; breakpoint set sourced once from
  `Settings::$breakpoints` everywhere.
- **Migration:** replaces interim `tx_imaginator_aspect_ratio`; note the field rename + drop in the
  upgrade path (pre-release 0.0.x, so a hard replace is acceptable).
