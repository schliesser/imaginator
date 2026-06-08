# Imaginator v1 — Polish (Seams, Docs)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Production-harden v1: lay the **no-op seams** for v2 warmup and telemetry, and write user docs.

**Architecture:** Event + interface seams that do nothing in v1 but let v2 add a Messenger worker and
metrics without touching render/processing code; docs.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14, PHPUnit.

**Depends on:** foundation plan (`ProcessImageRequest`, `PictureRenderer`, `Settings`).

---

## File Structure
- `Classes/Event/AfterImageVariantBuiltEvent.php` — dispatched per variant in the renderer.
- `Classes/Warmup/WarmupInterface.php` + `Classes/Warmup/NullWarmup.php` — v1 no-op.
- `Classes/Telemetry/MetricsCollectorInterface.php` + `Classes/Telemetry/NullMetricsCollector.php`.
- `Documentation/` (reST), `README.md`.

---

## Task 1: Warmup + telemetry seams (no-op + events)

**Files:** Create the four seam classes + event; Modify `Classes/Rendering/PictureRenderer.php`,
`Configuration/Services.yaml`
Test: `Tests/Unit/Event/AfterImageVariantBuiltEventTest.php`, `Tests/Functional/SeamDispatchTest.php`

- [ ] **Step 1: Failing test (unit)** — `AfterImageVariantBuiltEvent` exposes the `ImageVariant` and the
  built URL immutably.
- [ ] **Step 2: FAIL** → **Step 3: implement** event + `WarmupInterface { queue(ImageVariant): void }`
  with `NullWarmup` (empty) + `MetricsCollectorInterface { record(ImageVariant): void }` with
  `NullMetricsCollector` (empty); wire defaults in DI.
- [ ] **Step 4: PASS** → **Step 5: commit** `feat: warmup/telemetry seams (no-op) + variant-built event`.
- [ ] **Step 6: Failing test (functional)** — registering a test listener on
  `AfterImageVariantBuiltEvent` receives one event per rendered variant; `NullWarmup::queue()` /
  `NullMetricsCollector::record()` are invoked and do nothing (no error, no I/O).
- [ ] **Steps 7–10:** FAIL → dispatch the event + call the seams from `PictureRenderer` → PASS → commit
  `feat: dispatch variant-built event and invoke no-op seams from renderer`.

> v2 (separate plan) swaps `NullWarmup`→Messenger worker and `NullMetricsCollector`→a DB collector via
> DI override — zero changes to renderer/middleware.

---

## Task 2: Documentation

**Files:** Create `Documentation/Index.rst`, `Documentation/Editor/Index.rst`,
`Documentation/Integrator/Index.rst`, `README.md`

- [ ] **Step 1:** Write Integrator docs: install, Site-Set settings (ladder, formats, qualities,
  processor selection local vs each external provider incl. signing keys), `<imaginator:image>`
  ViewHelper reference, `priority` usage, security notes (HMAC, key rotation).
- [ ] **Step 2:** Write Editor docs: the aspect-ratio element, per-breakpoint ratios, `auto`.
- [ ] **Step 3:** Write `README.md`: what it is, the "sharp first paint, zero `sizes`" pitch, the
  ladder rationale.
- [ ] **Step 4:** Commit `docs: integrator + editor documentation and README`.

(No TDD steps — prose. Verify rendered reST builds clean if a docs render is available.)

---

## Self-Review
- **Spec coverage:** warmup/telemetry seams → v2 (§9.2/9.7) ✓ T1; docs ✓ T2.
- **Placeholder scan:** code task (T1) carries exact behaviour + assertions; T2 is intentionally prose.
- **Type consistency:** `WarmupInterface::queue(ImageVariant)`, `MetricsCollectorInterface::record(
  ImageVariant)`, `AfterImageVariantBuiltEvent` carry the same `ImageVariant` from the foundation plan;
  setting key (`imaginator.maxDimension`) matches those defined elsewhere.
