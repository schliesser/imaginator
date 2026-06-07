# Imaginator v1 — Polish (Rate Limit, Seams, Docs)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (recommended)
> or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Production-harden v1: rate-limit the local endpoint, lay the **no-op seams** for v2 warmup
and telemetry, and write user docs.

**Architecture:** A rate-limiter guard inside the local endpoint (defense in depth on top of HMAC
signing); event + interface seams that do nothing in v1 but let v2 add a Messenger worker and metrics
without touching render/processing code; docs.

**Tech Stack:** PHP 8.3, TYPO3 13.4/14, symfony/rate-limiter, PHPUnit.

**Depends on:** foundation plan (`ProcessImageRequest`, `PictureRenderer`, `Settings`).

---

## File Structure
- `Classes/Security/EndpointRateLimiter.php` — wraps symfony/rate-limiter; used by the middleware.
- `Classes/Event/AfterImageVariantBuiltEvent.php` — dispatched per variant in the renderer.
- `Classes/Warmup/WarmupInterface.php` + `Classes/Warmup/NullWarmup.php` — v1 no-op.
- `Classes/Telemetry/MetricsCollectorInterface.php` + `Classes/Telemetry/NullMetricsCollector.php`.
- `Documentation/` (reST), `README.md`.

---

## Task 1: Endpoint rate limiter

**Files:** Create `Classes/Security/EndpointRateLimiter.php`; Modify `Classes/Middleware/ProcessImageRequest.php`,
`composer.json` (+`symfony/rate-limiter`), `settings.definitions.yaml`
Test: `Tests/Functional/Security/EndpointRateLimiterTest.php`

- [ ] **Step 1: Failing functional test** — with `imaginator.rateLimit.enabled=1`, limit `2`, a third
  signed request from the same client IP within the window returns **429**; below the limit returns 302;
  disabled setting never limits.
- [ ] **Step 2: FAIL.**
- [ ] **Step 3: Implement** — `EndpointRateLimiter::consume(string $clientKey): bool` over a
  `RateLimiterFactory` backed by the caching-framework storage (carry the storage approach from old
  `RateLimiterUtility`); middleware calls it after signature verify, before `materialize()`; returns
  429 + `Retry-After` when blocked. Signing already stops arbitrary sizes — this stops flooding signed
  ones. Gated by setting (default off, or on with a generous limit — **[DECIDE default]**).
- [ ] **Step 4: PASS.** - [ ] **Step 5: Commit** `feat: optional rate limiting on image endpoint (429)`.

---

## Task 2: Warmup + telemetry seams (no-op + events)

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

## Task 3: Documentation

**Files:** Create `Documentation/Index.rst`, `Documentation/Editor/Index.rst`,
`Documentation/Integrator/Index.rst`, `README.md`

- [ ] **Step 1:** Write Integrator docs: install, Site-Set settings (ladder, formats, qualities,
  processor selection local vs each external provider incl. signing keys), `<imaginator:image>`
  ViewHelper reference, `priority` usage, security notes (HMAC, key rotation, rate limit).
- [ ] **Step 2:** Write Editor docs: the aspect-ratio element, per-breakpoint ratios, `auto`.
- [ ] **Step 3:** Write `README.md`: what it is, the "sharp first paint, zero `sizes`" pitch, the
  ladder rationale.
- [ ] **Step 4:** Commit `docs: integrator + editor documentation and README`.

(No TDD steps — prose. Verify rendered reST builds clean if a docs render is available.)

---

## Self-Review
- **Spec coverage:** rate limiting (design §9.8) ✓ T1; warmup/telemetry seams → v2 (§9.2/9.7) ✓ T2;
  docs ✓ T3.
- **Placeholder scan:** code tasks (T1–2) carry exact behaviour + assertions; T3 is intentionally prose.
- **Type consistency:** `WarmupInterface::queue(ImageVariant)`, `MetricsCollectorInterface::record(
  ImageVariant)`, `AfterImageVariantBuiltEvent` carry the same `ImageVariant` from the foundation plan;
  setting keys (`imaginator.rateLimit.*`, `imaginator.maxDimension`) match those defined elsewhere.

## One open decision
- [ ] **Rate-limit default:** off, or on with a generous default (e.g. 120/min/IP)? (T1 Step 3.)
