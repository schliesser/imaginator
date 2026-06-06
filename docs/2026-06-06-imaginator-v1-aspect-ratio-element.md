# Imaginator v1 — Aspect-Ratio Backend Element Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A FormEngine element where the editor sets **per-breakpoint aspect ratios at the
content-element level** (applies to all media in the CE). Stored as JSON, fed into `PictureRenderer`.

**Architecture:** TCA `type=user` renderType backed by a PHP node that renders a TS web component.
The component shows one row per Site-Set breakpoint with a ratio control (presets + free `w:h` +
`auto`) and a proportioned **ratio swatch** (shape only — no image load, since ratios apply to all
media). Value persisted as JSON in a tt_content column; a resolver maps it to the per-breakpoint
`AspectRatio` map the renderer consumes.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14 FormEngine, TypeScript + Vitest, Web Components.

**Depends on:** foundation plan (`AspectRatio`, `PictureRenderer`, ViewHelper).

---

## File Structure
- `Configuration/TCA/Overrides/tt_content.php` — add `imaginator_ratios` column + palette.
- `ext_tables.sql` — `imaginator_ratios text`.
- `Classes/Backend/Form/Element/AspectRatioElement.php` — PHP node.
- `Classes/Backend/Form/AspectRatioElementRegistration.php` — v13/v14 node-registry adapter.
- `Classes/Dto/RatioBreakpoint.php`, `Classes/Service/RatioMapResolver.php` — JSON ⇄ AspectRatio map.
- `Resources/Private/JavaScript/backend/aspect-ratio/element.ts` — web component.
- `Resources/Public/Css/backend/aspect-ratio.css`.
- Tests: Unit (resolver, component), Functional (node render).

---

## Task 1: TCA column + DB field + renderType registration

**Files:** `Configuration/TCA/Overrides/tt_content.php`, `ext_tables.sql`,
`Classes/Backend/Form/AspectRatioElementRegistration.php`, `ext_localconf.php`
Test: `Tests/Functional/Backend/TcaRegistrationTest.php`

- [ ] **Step 1: Failing functional test** — after bootstrap, `$GLOBALS['TCA']['tt_content']['columns']
  ['imaginator_ratios']['config']['renderType']` equals `'imaginatorAspectRatio'`, and the node registry
  resolves that renderType to `AspectRatioElement::class`.
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — TCA `type=user, renderType=imaginatorAspectRatio`; SQL `imaginator_ratios
  text`; registration adapter that handles **both** v13 and v14 node-registry APIs (v14 changed the
  FormEngine node registration surface) behind one `register()` called from `ext_localconf.php`.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: TCA + DB + renderType for aspect-ratio element`.

---

## Task 2: RatioBreakpoint DTO + RatioMapResolver

**Files:** `Classes/Dto/RatioBreakpoint.php`, `Classes/Service/RatioMapResolver.php`
Test: `Tests/Unit/Service/RatioMapResolverTest.php` (pure)

- [ ] **Step 1: Failing test**

```php
public function testResolvesJsonToBreakpointKeyedRatioMap(): void
{
    $json = '[{"breakpoint":"lg","minWidth":992,"ratio":"16:9"},{"breakpoint":"xs","minWidth":0,"ratio":"1:1"}]';
    $map = (new RatioMapResolver())->fromJson($json);
    // sorted by minWidth ascending; AspectRatio objects
    self::assertSame([0, 992], array_keys($map));
    self::assertEquals(new AspectRatio(1, 1), $map[0]);
    self::assertEquals(new AspectRatio(16, 9), $map[992]);
}

public function testAutoRatioYieldsNullEntry(): void
{
    $map = (new RatioMapResolver())->fromJson('[{"breakpoint":"xs","minWidth":0,"ratio":"auto"}]');
    self::assertArrayHasKey(0, $map);
    self::assertNull($map[0]); // null = keep source ratio at this breakpoint
}

public function testEmptyOrInvalidJsonYieldsEmptyMap(): void
{
    self::assertSame([], (new RatioMapResolver())->fromJson(''));
    self::assertSame([], (new RatioMapResolver())->fromJson('not json'));
}
```

- [ ] **Step 2: FAIL** (`-c Build/phpunit/UnitTests.xml Tests/Unit/Service/RatioMapResolverTest.php`).
- [ ] **Step 3: Implement** — `RatioBreakpoint` readonly (`breakpoint`, `minWidth`, `ratio`);
  `RatioMapResolver::fromJson()` decodes, validates, maps `auto`→`null` else `AspectRatio::fromString()`,
  keys by `minWidth`, ksort. Malformed → `[]`.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: RatioMapResolver (CE JSON -> per-breakpoint ratio map)`.

---

## Task 3: AspectRatioElement PHP node

**Files:** `Classes/Backend/Form/Element/AspectRatioElement.php`
Test: `Tests/Functional/Backend/AspectRatioElementTest.php`

- [ ] **Step 1: Failing functional test** — `render()` returns a result whose `html` contains
  `<imaginator-aspect-ratio>` with a `data-breakpoints` attribute carrying the Site-Set breakpoints JSON
  and a `data-value` attribute carrying the current field value, plus the hidden input named for the
  field. Asserts the JS/CSS asset is registered via `result['javaScriptModules']`.
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — node reads breakpoints from the resolved site settings
  (`imaginator.breakpoints`) + current value; renders the custom element host + hidden input; registers
  the backend JS module + CSS. (Reuse the `JavaScriptModuleInstruction` pattern from the old
  `AspectRatioElement`.)
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: AspectRatioElement renders web component host`.

---

## Task 4: Web component (rows, presets, free, auto)

**Files:** `Resources/Private/JavaScript/backend/aspect-ratio/element.ts`
Test: `Tests/JavaScript/aspect-ratio.test.ts` (Vitest + jsdom)

- [ ] **Step 1: Failing test** — given `data-breakpoints` of `[{key:'xs',min:0},{key:'lg',min:992}]`
  and empty value, the component renders 2 rows; selecting preset `16:9` on the `lg` row and writing
  serializes the hidden input to JSON containing `{"breakpoint":"lg","minWidth":992,"ratio":"16:9"}`;
  choosing `auto` serializes `"ratio":"auto"`; free input `21:9` validates and serializes.
- [ ] **Step 2: FAIL** (`npx vitest run aspect-ratio`).
- [ ] **Step 3: Implement** — `class AspectRatioElement extends HTMLElement`: parse `data-breakpoints`
  + `data-value`, render rows (preset `<select>` 1:1/4:3/3:2/16:9/21:9 + `auto` + free `w:h` input),
  on change rebuild the JSON array and write the hidden input; reject malformed free input.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: aspect-ratio web component (per-breakpoint rows)`.

---

## Task 5: Ratio swatch + CSS

**Files:** Modify `element.ts`; `Resources/Public/Css/backend/aspect-ratio.css`
Test: `Tests/JavaScript/aspect-ratio-swatch.test.ts`

- [ ] **Step 1: Failing test** — each row renders a swatch element whose inline style sets
  `aspect-ratio: 16 / 9` for a `16:9` selection (and updates live on change); `auto` shows a neutral
  swatch with no fixed `aspect-ratio`.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement** swatch (CSS `aspect-ratio` box, no image) + live
  update. - [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: live ratio swatch (shape preview, no image load)`.

---

## Task 6: Renderer integration (CE ratios drive output)

**Files:** Modify `Classes/ViewHelpers/ImageViewHelper.php` (or the content-element data processor)
Test: `Tests/Functional/Rendering/CeRatioIntegrationTest.php`

- [ ] **Step 1: Failing functional test** — rendering a CE whose `imaginator_ratios` holds
  `xs→1:1, lg→16:9` produces a `<picture>` with the `lg` `<source media="(min-width:992px)">` ladder
  at 16:9 heights and the `<img>` fallback at 1:1 — for **every** media file in the CE.
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — resolve `imaginator_ratios` via `RatioMapResolver`, pass the map to
  `PictureRenderer` for each media file in the element. (If a `aspectRatio` is set directly on the
  ViewHelper it overrides the CE map; otherwise CE map wins; else source ratio.)
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: CE-level ratios applied to all media via renderer`.

---

## v13/v14 note
FormEngine node registration and a few `AbstractFormElement` internals differ between v13 and v14.
Task 1's `AspectRatioElementRegistration` and Task 3's node isolate those differences; the web
component (Tasks 4–5) is version-agnostic. Run the functional suite on both v13 and v14 matrices.

## Self-Review
- **Spec coverage:** CE-level per-breakpoint ratio (design §8) ✓ T1–6; no live image preview — swatch
  only ✓ T5; v13/v14 adapter ✓ T1/T3 note; data feeds renderer for all media ✓ T6; crop stays native
  FAL (not in element) ✓ (by omission, per §8).
- **Placeholder scan:** resolver + component carry full code/assertions; node + integration carry exact
  behaviour + assertions (DB/FormEngine output isn't byte-stable — fixture assertions are correct).
- **Type consistency:** JSON shape `{breakpoint,minWidth,ratio}` identical across T2/T4/T6;
  `RatioMapResolver::fromJson(): array<int,?AspectRatio>` consistent T2→T6; `auto`→`null` convention
  uniform.
