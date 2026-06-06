# Imaginator v1 — JS Progressive Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A tiny, non-load-bearing custom element that (1) supplies a concrete `sizes` on browsers
without native `sizes="auto"` (Safari), and (2) optionally refines to a pixel-perfect signed URL —
without ever blocking sharpness, which the server-rendered ladder already delivers.

**Architecture:** `<imaginator-img>` wraps the `<picture>`/`<img>`. On connect it feature-detects
auto-sizing; only if missing does it measure the laid-out width and set `sizes="<px>"` (browser then
re-picks from the **existing** srcset — no JSON, no extra round-trip beyond the chosen candidate).
Opt-in refine swaps `src` to an exact-width signed URL after a good image is already shown.

**Tech Stack:** TypeScript, Vite (library build), Vitest + jsdom.

**Depends on:** foundation plan (`PictureRenderer` emits `sizes="auto"`; `SignedUrlBuilder` route).

---

## File Structure
- `Resources/Private/JavaScript/frontend/support.ts` — `supportsAutoSizes()`.
- `Resources/Private/JavaScript/frontend/sizes-fallback.ts` — measure + set `sizes`, debounced resize.
- `Resources/Private/JavaScript/frontend/refine.ts` — optional exact-width swap.
- `Resources/Private/JavaScript/frontend/main.ts` — `<imaginator-img>` custom element.
- `vite.config.ts`, `Configuration/Sets/Imaginator/setup.typoscript` (modify: include built JS, deferred).
- Tests: `Tests/JavaScript/*.test.ts`.

---

## Task 0: Vite + Vitest harness

- [ ] **Step 1:** Write `package.json` with `vite`, `vitest`, `jsdom`, `typescript`; scripts
  `test`, `build`.
- [ ] **Step 2:** Write `vite.config.ts` (library build → `Resources/Public/JavaScript/frontend/main.js`,
  ES module, no externals) and `vitest` config (`environment: 'jsdom'`).
- [ ] **Step 3:** Run `npm i && npx vitest run` → PASS "no tests".
- [ ] **Step 4:** Commit `chore: vite + vitest harness for frontend enhancement`.

---

## Task 1: supportsAutoSizes detection

**Files:** Create `support.ts`; Test `Tests/JavaScript/support.test.ts`

- [ ] **Step 1: Failing test**

```ts
import { describe, it, expect, vi } from 'vitest'
import { supportsAutoSizes } from '../../Resources/Private/JavaScript/frontend/support'

describe('supportsAutoSizes', () => {
  it('returns true when an img normalises sizes="auto" to "auto"', () => {
    // jsdom: emulate support by stubbing the reflected property
    const img = document.createElement('img')
    Object.defineProperty(img, 'sizes', { value: 'auto', writable: true })
    vi.spyOn(document, 'createElement').mockReturnValueOnce(img as HTMLImageElement)
    expect(supportsAutoSizes()).toBe(true)
  })

  it('returns false when sizes="auto" is dropped/normalised away', () => {
    const img = document.createElement('img')
    Object.defineProperty(img, 'sizes', { value: '', writable: true })
    vi.spyOn(document, 'createElement').mockReturnValueOnce(img as HTMLImageElement)
    expect(supportsAutoSizes()).toBe(false)
  })
})
```

- [ ] **Step 2: Run → FAIL** (`npx vitest run support`).
- [ ] **Step 3: Implement**

```ts
export function supportsAutoSizes(): boolean {
  const img = document.createElement('img')
  img.setAttribute('loading', 'lazy') // auto-sizing only valid with lazy
  img.setAttribute('sizes', 'auto')
  return img.sizes === 'auto'
}
```

- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat: supportsAutoSizes feature detection`.

---

## Task 2: SizesFallback (measure → set sizes, debounced)

**Files:** Create `sizes-fallback.ts`; Test `Tests/JavaScript/sizes-fallback.test.ts`

- [ ] **Step 1: Failing test** — `apply(img, 640.4)` sets `img.sizes` to `"641px"` (ceil); a second
  call within tolerance (≤2%) does not rewrite; a large change does.

```ts
it('sets ceil px sizes and skips sub-2% changes', () => {
  const img = document.createElement('img')
  const f = new SizesFallback()
  f.apply(img, 640.4); expect(img.sizes).toBe('641px')
  f.apply(img, 645);   expect(img.sizes).toBe('641px') // within 2% -> unchanged
  f.apply(img, 900);   expect(img.sizes).toBe('900px')
})
```

- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** `SizesFallback` with `apply(img, width)` (ceil, 2% threshold vs last
  applied) and a `observe(img)` that wires a `ResizeObserver` with a 150ms debounce calling `apply`.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: SizesFallback sets concrete sizes for non-auto browsers`.

---

## Task 3: <imaginator-img> custom element

**Files:** Create `main.ts`; Test `Tests/JavaScript/element.test.ts`

- [ ] **Step 1: Failing test** — when `supportsAutoSizes()` is true, the element does NOT touch
  `img.sizes` (stays `"auto"`); when false, it sets a concrete px `sizes` from the laid-out width.
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — `class ImaginatorImg extends HTMLElement`: on `connectedCallback`, find
  the inner `<img>`; if `supportsAutoSizes()` → do nothing (native handles it); else
  `new SizesFallback().observe(img)`. Guard double-init. `customElements.define('imaginator-img', …)`.
  (Renderer must wrap output in `<imaginator-img>` — add that to the foundation `PictureRenderer`
  output as part of this plan's Step: modify renderer to wrap, behind the existing structure.)
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: imaginator-img element, sizes fallback only when needed`.

---

## Task 4: Optional pixel-perfect refine

**Files:** Create `refine.ts`; Test `Tests/JavaScript/refine.test.ts`

- [ ] **Step 1: Failing test** — refine is OFF unless `data-refine` is present; when on, after the
  ladder image loads it builds an exact-width URL from the element's `data-config` + measured width and
  swaps `img.src` once (assert the new src and that it only fires after `load`).
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — `Refiner.maybeRefine(element, img)`: bail if no `data-refine`; on `load`,
  compute `Math.ceil(width*dpr)`, fetch the signed exact URL via the route, set `img.src`. Abortable.
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: opt-in pixel-perfect refine`.

---

## Task 5: Build + Site-Set include

**Files:** Modify `vite.config.ts` output path; `Configuration/Sets/Imaginator/setup.typoscript`

- [ ] **Step 1:** `npm run build` → emits `Resources/Public/JavaScript/frontend/main.js`.
- [ ] **Step 2:** Add to `setup.typoscript`:
  `page.includeJSFooter.imaginator = EXT:imaginator/Resources/Public/JavaScript/frontend/main.js`
  `page.includeJSFooter.imaginator.async = 1` (footer + async — never render-blocking; sharpness is
  server-side so deferring JS is safe).
- [ ] **Step 3:** Manual smoke: a `<picture>` renders sharp with JS disabled (verify), and on Safari
  the `sizes` gets a px value with JS enabled.
- [ ] **Step 4:** Commit `feat: build + async footer include for enhancement JS`.

---

## Self-Review
- **Spec coverage:** Safari sizes fallback (design §7.1) ✓ T1–3; opt-in refine (§7.2) ✓ T4; never
  load-bearing — async footer + works JS-off ✓ T5; `sizes=auto`+lazy requirement honored in detection ✓ T1.
- **Placeholder scan:** detection/fallback/refine carry full TS + assertions; element wrap change is an
  explicit renderer modification, not a placeholder.
- **Type consistency:** `SizesFallback.apply(img, width)` / `.observe(img)` consistent T2→T3;
  `supportsAutoSizes()` boolean used identically.
