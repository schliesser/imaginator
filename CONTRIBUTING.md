# Contributing to EXT:imaginator

Thanks for helping improve **`schliesser/imaginator`**. This guide covers the local
setup, the test workflow, and the conventions CI enforces.

By contributing you agree your work is licensed under the project's
**GPL-2.0-or-later**.

## Requirements

- **DDEV** ≥ 1.24 and a Docker provider (OrbStack or Docker Desktop) — the supported dev environment.
- **PHP 8.3+** and **Composer 2** (provided inside the DDEV web container; only needed on the host
  if you run tools outside DDEV).
- **Node 20** for the JavaScript build/tests (`npm` runs inside the web container too).

The supported floor is **PHP 8.3 / TYPO3 13.4**; the test matrix is PHP 8.3/8.4/8.5 against
TYPO3 13.4 and 14.x. Keep code dual-version-clean — no APIs exclusive to one major without a guard.

## Quick start

```bash
git clone …            # or work in a git worktree (see below)
ddev start             # boots web (nginx-fpm, PHP 8.3), MariaDB, imgproxy
ddev setup             # composer install into .Build/
ddev demo              # build the clickable demo at https://imaginator.ddev.site/
```

A fresh checkout has **no database and no `.Build/`** — both are generated. `ddev demo` copies the
committed source images from `Build/demo/` into `fileadmin`, seeds the demo content elements, and
indexes them in FAL. Re-run it any time for a clean instance.

Backend on every instance: **`admin` / `Password.1`**.

## DDEV commands

| Command | What it does |
|---|---|
| `ddev setup` | `composer install` into `.Build/` (run after a fresh clone) |
| `ddev demo` | (Re)build the default demo at `https://imaginator.ddev.site/` (`.Build/`, current TYPO3 version) |
| `ddev install-v13` | Clickable TYPO3 **v13** demo at `https://v13.imaginator.ddev.site/` (`--fresh` to rebuild) |
| `ddev install-v14` | Clickable TYPO3 **v14** demo at `https://v14.imaginator.ddev.site/` |
| `ddev install-all` | Both side-by-side |
| `ddev test [unit\|functional\|all]` | Run the PHPUnit suites |
| `ddev lint` | PHPStan + php-cs-fixer (dry-run) |
| `ddev cgl-fix` | Auto-fix coding-style violations |
| `ddev worktree-init [name]` | Give this git worktree a unique DDEV name so it can run alongside others |

The `v13`/`v14` instances live in their own Docker volumes (`/var/www/html/v13|v14`), each a full
TYPO3 base-distribution with EXT:imaginator wired in via a Composer **path repository** to the working
tree — edits to `Classes/` reflect live, no reinstall.

## Tests

Run everything through DDEV:

```bash
ddev test unit          # pure PHPUnit, no TYPO3 boot — fast
ddev test functional    # typo3/testing-framework: boots TYPO3 + DB; uses ImageMagick
ddev test all
ddev lint               # PHPStan (level 8) + php-cs-fixer dry-run
npm test                # JavaScript unit tests (vitest)
npm run build:js        # TypeScript build (also type-checks)
```

Single test file:

```bash
ddev exec .Build/bin/phpunit -c Build/phpunit/UnitTests.xml Tests/Unit/UrlBuilder/LocalAsyncUrlBuilderTest.php
```

### Test layering

- **`Tests/Unit/`** — pure `TestCase`, no TYPO3 bootstrap. The signing + ladder core (AspectRatio,
  Ladder, CanonicalParams, LocalAsyncUrlBuilder, ImageVariant) is deliberately framework-free so it
  runs fast here. Keep new pure logic unit-testable.
- **`Tests/Functional/`** — boots TYPO3 + DB. Anything touching FAL, the middleware, ViewHelpers, or
  `ImageService` processing. Renderer output is verified with **golden-file** tests (inject a fake
  processor returning predictable URLs, assert exact HTML).
- **JS** — `vitest` for unit, `tsc` for types.
- **E2E** — Playwright specs validate the real `<picture>` output, the `sizes="auto"` polyfill, and the
  backend element. CI runs them against both the local processor and imgproxy.

### Strict TDD

Implementation in this repo follows strict TDD, task-by-task:
**write a failing test → run (verify FAIL) → minimal implementation → run (verify PASS) → commit.**
Don't batch tasks or skip the failing-test step. The plans in `docs/` are written for this flow.

## Coding standards

- **php-cs-fixer** — config in `.php-cs-fixer.dist.php` (`@auto` rule set). Run `ddev cgl-fix` before
  committing. Note: `ext_emconf.php` must stay `strict_types`-free.
- **PHPStan** — level 8 (`Build/phpstan/phpstan.neon`), with the TYPO3 + PHPUnit extensions. Must be clean.
- Match the surrounding code's naming, comment density, and idiom.

### Key invariants

- The render layer (`PictureRenderer`/`ImageViewHelper`) is **processor-agnostic** — the same HTML is
  emitted regardless of who does the pixels. Don't leak local-vs-external assumptions into it.
- `CanonicalParams`' field set and order must stay identical everywhere it's constructed — the HMAC
  signature depends on it byte-for-byte.
- Only rung-quantized widths may be signed/served; never let an arbitrary requested `w×h` reach the
  processor (DoS surface).

See `CLAUDE.md` and the RFCs in `docs/` for the full architecture and the locked design decisions.

## Branches, commits, PRs

- Branch off `main`; open a pull request against `main`.
- **Conventional Commits** for messages: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`,
  optionally scoped (e.g. `feat(ladder): …`).
- CI (`.github/workflows/ci.yml`) must be green: lint, unit + functional across the PHP/TYPO3 matrix,
  JS (vitest + tsc), and Playwright e2e (local processor + imgproxy). Run `ddev test all && ddev lint`
  locally first.

## Working in git worktrees

Worktrees let you develop several branches in parallel, each with its own clickable DDEV stack:

```bash
git worktree add -b my-feature ../imaginator-my-feature
cd ../imaginator-my-feature
ddev worktree-init          # writes a gitignored .ddev/config.local.yaml with a unique project name
ddev restart
ddev setup && ddev demo     # (or ddev install-v13 / install-v14)
```

`worktree-init` derives a unique DDEV name (and matching `v13.`/`v14.` hostnames) from the branch, so
the worktree runs concurrently with your main checkout without host/volume collisions. The `.ddev/`
config is committed and travels with every worktree.

## The demo is contribution-only

The demo Site Set (`Configuration/Sets/ImaginatorDemo/`), demo templates (`Resources/Private/Demo/`),
and demo assets (`Build/demo/`) are `export-ignore`d in `.gitattributes` — present in the repo for
contributors, excluded from the Composer release tarball. Don't rely on them at runtime in production code.
