# Attribute auto-registration: `#[AsImaginatorProcessor]`

## Context

Today a processor registers via manual `Services.yaml` tagging (`imaginator.image_processor` + `key`),
and each external provider additionally needs a named service + dedicated factory class
(`ImgproxyProcessorFactory`, `ImagorProcessorFactory`). Goal: attribute-based auto-registration —
**one attribute** covering both shapes, via a compiler pass. Design goal: the integrator story fits
in one sentence — *"put `#[AsImaginatorProcessor('my-key')]` on your class, pick the interface that
fits, set `processor = my-key` — no yaml."*

**One attribute, one tag** (`imaginator.image_processor` → same registry, same locator, same
`processor` setting namespace). The **implemented interface decides the registration shape** — the
interface already states intent, so a second attribute name would only duplicate it and add a
mismatch error case:

- Class implements **`ImageProcessorInterface`** → pass tags the service directly. For full-control
  processors (built-in locals, exotic integrator processors).
- Class implements **`UrlBuilderInterface`** → pass synthesizes a configured `ExternalImageProcessor`
  named service wrapping that builder, tagged under `key`. A new CDN provider (Cloudflare, Thumbor, …)
  becomes **one URL-builder class, zero YAML**. Rationale: the non-grammar work (source-URL resolution,
  editor-crop replay, offloaded semantics) stays written once in `final` `ExternalImageProcessor`;
  builders remain pure grammar (golden-file testable, no I/O).
- Neither interface → throw (message names both options); both interfaces → throw (ambiguous).
  (`LocalAsyncUrlBuilder` does not implement `UrlBuilderInterface`, so no false positives among
  existing classes.)

Both provider builders already share the constructor convention `__construct(ExternalConfig $config)`,
and `ImagorUrlBuilder` documents that `salt` is ignored — so one generic factory can construct any
builder from the unified settings. The two per-provider factory classes become obsolete.

**Why the pass does its own reflection (not `registerAttributeForAutoconfiguration`):** ordering is
*not* the problem — autoconfigured attribute tags materialize in `beforeOptimization` at priority 100
(`AttributeAutoconfigurationPass` → `ResolveInstanceofConditionalsPass`, see Symfony `PassConfig`),
well before the locator's `ServiceLocatorTagPass` (optimization), so they would be visible to
`!tagged_locator`. Own reflection is needed because (a) the URL-builder shape synthesizes a *new*
definition, which autoconfiguration cannot do, and (b) duplicate-key validation needs a
full-container view anyway. Attributed classes register through their own extension's standard
`Services.yaml` discovery. The pass runs
`TYPE_BEFORE_OPTIMIZATION` at default priority 0 — after yaml load, class resolution, and
instanceof-tag materialization (all priority ≥ 100), before the locator resolves.

## New files

1. **`Classes/Attribute/AsImaginatorProcessor.php`**
   `#[Attribute(Attribute::TARGET_CLASS)]` (deliberately *not* `IS_REPEATABLE` — one key per
   class; multi-key aliasing stays a manual-yaml case), `final readonly`, single `public string $key`.

2. **`Classes/DependencyInjection/ProcessorRegistrationPass.php`** (pure Symfony, unit-testable)
   Iterates `$container->getDefinitions()`; skips abstract/synthetic/classless; resolves class via
   `$container->getReflectionClass($class, false)` (null-safe skip for unloadable classes; note
   TYPO3's container cache ignores Symfony resource tracking — adding an attribute needs a cache
   flush, same as editing a yaml tag). Compile-time cost: reflection autoloads every service class
   once during container compile — one-time, cached, acceptable. For each `AsImaginatorProcessor`
   occurrence, dispatch on the implemented interface:
   - `ImageProcessorInterface` →
     `$definition->addTag('imaginator.image_processor', ['key' => $key])`
   - `UrlBuilderInterface` → register synthetic definition `imaginator.processor.{key}`: class
     `ExternalImageProcessor`, factory `[ExternalProcessorFactory, 'create']`, argument = builder
     FQCN (string, not reference — builder service stays unreferenced and is dropped by the
     container, avoiding its unautowirable `ExternalConfig` constructor), tag with `key`.
   - neither interface → throw (exception message names both interfaces); both → throw (ambiguous)
   - duplicate key → throw (locator `index_by` would silently last-win otherwise). Seed the seen-set
     from pre-existing `findTaggedServiceIds('imaginator.image_processor')` so attribute-vs-manual-yaml
     collisions are caught too (all yaml + instanceof tags are visible at priority 0). Empty key → throw.

3. **`Classes/Imaging/External/ExternalProcessorFactory.php`** (generic, replaces both per-provider factories)
   Deps like the old factories (`SettingsFactory`, `ResourceFactory`, `CropResolver`, `CropCalculator`).
   `create(string $builderClass): ExternalImageProcessor` — guards `is_a($builderClass, UrlBuilderInterface)`,
   builds `new $builderClass(new ExternalConfig(baseUrl, signKey, salt, options))` + `processorSourceBaseUrl`.
   Convention documented on `UrlBuilderInterface`: attribute-registered builders take `ExternalConfig`
   as sole constructor arg. (Imagor now receives `salt` too — ignored by documented contract; behavior unchanged.)

4. **Provider-specific extra config — `processorOptions` pass-through** (so "generic" survives first
   real provider, e.g. Cloudflare accountHash/variant):
   - `Settings`: new `processorOptions: array<string,string>` from the raw `processorOptions.`
     subtree of the **Extension Configuration** (settings come from `ext_conf_template.txt` via
     `SettingsFactory` — not Site Sets). Keys absent from the template get no backend UI; integrators
     set them in `settings.php` `EXTENSIONS['imaginator']['processorOptions']` — document this.
     `Settings::fromArray` coerces values to string and drops non-scalar entries (ext_conf nesting
     is mixed) rather than passing through blind.
   - `ExternalConfig`: new `options` map + `option(name, default)` / `requireOption(name)` helpers;
     `requireOption` throws descriptive exception → misconfig fails at selection time, not as broken URLs.
   - `ExternalProcessorFactory` feeds `$settings->processorOptions` into every `ExternalConfig`;
     builders consume what they need, ignore the rest (same contract as imagor ignoring `salt`).
   - Escalation ladder documented: URL builder (zero config code) → builder + options → manual yaml
     named service + own factory (pass never forbids manual tags) → full `ImageProcessorInterface`
     class (same attribute).

5. **`Configuration/Services.php`** — TYPO3 loads it alongside `Services.yaml`; dual-arg closure
   `static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder)` →
   `$containerBuilder->addCompilerPass(new ProcessorRegistrationPass());`

## Changed files

- **`Configuration/Services.yaml`**: add `../Classes/Attribute/` to excludes; delete the two local
  processors' tag entries, the two named external services (`imaginator.processor.imgproxy|imagor`).
  Registry `!tagged_locator` + `ImageProcessorInterface` factory wiring stay unchanged.
- **Attribute on built-ins (dogfood):** `#[AsImaginatorProcessor]` on all four —
  `LocalImageProcessor` (`local:async`) + `LocalSyncImageProcessor` (`local:sync`) via the
  processor-interface shape; `ImgproxyUrlBuilder` (`imgproxy`) + `ImagorUrlBuilder` (`imagor`) via
  the builder-interface shape.
- **Delete** `Classes/Imaging/External/ImgproxyProcessorFactory.php` + `ImagorProcessorFactory.php`
  (only referenced from Services.yaml + docs; pre-1.0, no BC concern).

## Tests (TDD order)

1. **Unit (failing first)** `Tests/Unit/DependencyInjection/ProcessorRegistrationPassTest.php` —
   pure `Symfony\Component\DependencyInjection\ContainerBuilder` (available in `.Build`, TYPO3 dep) +
   tiny fixture classes declared in the test namespace:
   - attribute on an `ImageProcessorInterface` class → tag with key on its definition
   - attribute on a `UrlBuilderInterface` class → synthetic `imaginator.processor.{key}` definition
     (class/factory/arg/tag asserted)
   - attribute on a class implementing neither interface → exception naming both options
   - attribute on a class implementing both interfaces → exception (ambiguous)
   - duplicate key (across both registration shapes) → exception
   - attribute key colliding with a pre-existing manual `imaginator.image_processor` tag → exception
   - empty key → exception; abstract/synthetic/classless definitions skipped
   Plus unit coverage for `Settings::fromArray` `processorOptions` pass-through and
   `ExternalConfig::requireOption` throw path (both pure, `Tests/Unit/`).
2. Implement attribute + pass → green.
3. **Functional (failing first)** `Tests/Functional/Imaging/AsImaginatorProcessorTest.php` + fixture
   extension `Tests/Functional/Fixtures/Extensions/attribute_processor/` (composer.json,
   `Classes/AttributeTaggedProcessor.php` implementing `ImageProcessorInterface`,
   `Classes/DummyCdnUrlBuilder.php` implementing `UrlBuilderInterface`, Services.yaml with **no tags**).
   Follow `ImageProcessorFactoryTest` pattern (`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator']`
   → `$this->get(ImageProcessorFactory::class)->create()`): assert both keys resolve; for the builder
   shape assert `ExternalImageProcessor` instance + `buildUrl()` uses the dummy grammar.
4. Wire `Configuration/Services.php` + `ExternalProcessorFactory` → green.
5. Migrate built-ins (attribute on 4 classes, strip yaml, delete 2 factories). Existing
   `Tests/Functional/Imaging/ImageProcessorFactoryTest.php` (all 4 keys + default + unknown) is the
   regression net — must stay green.
6. Full suites + lint (`phpstan`, `php-cs-fixer` dry-run).

Commit at each red→green boundary per repo TDD rule.

## Docs

- `Documentation/Extend/CustomProcessor.rst`: the attribute as primary registration — one attribute,
  interface picks the shape (URL-builder-only for a new CDN vs full processor), `processorOptions`
  escalation ladder; yaml tag kept as documented alternative.
- `Documentation/Configuration/Processors.rst:40`, `docs/DESIGN.md:28+43`: mention the attribute; DESIGN
  provider list now "plug in as attribute-registered URL builders".
- Docblocks: `ImageProcessorRegistry`, `ImageProcessorFactory`, `UrlBuilderInterface` (constructor convention).

## Verification

```bash
.Build/bin/phpunit -c Build/phpunit/UnitTests.xml
typo3DatabaseDriver=pdo_sqlite php -d memory_limit=1G .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml
ddev lint   # or .Build/bin/phpstan + php-cs-fixer directly
```

Baseline already confirmed: functional `ImageProcessorFactoryTest` green locally with sqlite.
End-to-end: `ddev demo` render still emits identical srcset URLs (processor selection unchanged).
