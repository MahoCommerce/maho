---
description: "Map URLs to controller actions in Maho with the #[Maho\\Config\\Route] PHP attribute, compiled to a fast Symfony matcher at composer dump-autoload time."
---

# Routing <span class="version-badge">v26.5+</span>

Maho's routing layer maps incoming URLs to controller action methods. Under the hood it uses [Symfony Routing](https://symfony.com/doc/current/routing.html) with a compiled matcher, so URL resolution is opcached and fast. The matching layer benchmarks at **7.4× faster** than the previous router chain.

Routes are declared with the `#[Maho\Config\Route]` PHP attribute directly on controller action methods and compiled into static PHP arrays at `composer dump-autoload` time. There is no `RouteCollection` rebuilt per request.

## Defining routes

!!! info "v26.5+"
    Since v26.5, controller routes are defined using PHP attributes directly on the action method. The legacy XML `<frontend><routers>` declaration still works as a back-compatibility shim; see [Migrating from XML](#migrating-from-xml) if you're upgrading an existing module.

Place the `#[Maho\Config\Route]` attribute on a public action method to register a URL pattern:

```php
class Mage_Catalog_ProductController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/catalog/product/view/{id}', name: 'catalog.product.view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function viewAction()
    {
        // ...
    }
}
```

After adding, modifying, or removing any route attribute, run:

```bash
composer dump-autoload
```

This compiles all route attributes into three artifacts under `vendor/composer/`:

- `maho_url_matcher.php`: compiled Symfony matcher (request URL → route)
- `maho_url_generator.php`: compiled Symfony generator (route name → URL)
- `maho_attributes.php`: raw attribute data plus reverse-lookup maps

A fourth file, `maho_api_permissions.php`, is written by a separate compiler from the API resource attributes; see [Recompiling PHP attributes](#recompiling-php-attributes).

The runtime reads these files directly. Without the dump step, every URL 404s.

### Parameters

| Parameter      | Type       | Default      | Description |
|----------------|------------|--------------|-------------|
| `path`         | `string`   | *(required)* | URL path pattern (e.g. `'/catalog/product/view/{id}'`) |
| `name`         | `?string`  | auto         | Route name for URL generation. Auto-generated from `class::method` if omitted |
| `methods`      | `string[]` | `[]`         | Allowed HTTP methods (e.g. `['GET', 'POST']`). Empty = any method |
| `defaults`     | `array`    | `[]`         | Default values for path parameters |
| `requirements` | `array`    | `[]`         | Regex constraints per parameter (e.g. `['id' => '\d+']`) |
| `area`         | `?string`  | auto         | `'frontend'`, `'adminhtml'`, or `'install'`. Auto-detected from the controller class; only set explicitly when overriding |

### Path parameters and constraints

Use `{name}` placeholders in the path and constrain them via regex with `requirements`:

```php
#[Maho\Config\Route('/catalog/product/view/{id}', requirements: ['id' => '\d+'])]
public function viewAction() { }
```

Without a regex constraint a `{name}` placeholder accepts any non-`/` segment. `requirements` is the standard Symfony syntax; see the [Symfony route requirements docs](https://symfony.com/doc/current/routing.html#parameters-validation) for the full grammar.

### HTTP method restrictions

```php
// GET only
#[Maho\Config\Route('/catalog/product/view/{id}', methods: ['GET'])]
public function viewAction() { }

// POST only
#[Maho\Config\Route('/oauth/token', name: 'oauth.token', methods: ['POST'])]
public function indexAction() { }

// Multiple methods
#[Maho\Config\Route('/checkout/cart/update', methods: ['POST', 'PUT'])]
public function updateAction() { }
```

A method mismatch returns 405 Method Not Allowed; an empty `methods` array (the default) accepts any verb.

### Multiple routes on one method

The attribute is repeatable; apply it multiple times to expose the same action on different paths or with different method allow-lists:

```php
#[Maho\Config\Route('/checkout/cart', methods: ['GET'])]
#[Maho\Config\Route('/checkout/cart/index', methods: ['GET'])]
public function indexAction() { }
```

### Area auto-detection

Maho infers the area by walking the controller's class hierarchy:

| Base class                                                       | Area         |
|------------------------------------------------------------------|--------------|
| `Mage_Adminhtml_Controller_Action` or `Maho\Controller\AdminAction`   | `adminhtml`  |
| `Mage_Install_Controller_Action` or `Maho\Controller\InstallAction`   | `install`    |
| Anything else (typically `Mage_Core_Controller_Front_Action`)    | `frontend`   |

You only need to pass `area:` explicitly when the auto-detection produces the wrong result, which is rare.

### Admin routes & the `{_adminFrontName}` placeholder

Admin URLs include a configurable front name segment that defaults to `admin` (e.g. `/admin/...`) and can be changed to something less guessable (e.g. `/backoffice/...`). The compiler resolves this at runtime, so you don't hard-code it.

Two equivalent forms work for admin routes:

```php
// Form 1: bare path. The compiler prepends '{_adminFrontName}/' automatically.
#[Maho\Config\Route('/catalog/product/edit/{id}')]
public function editAction() { }

// Form 2: '/admin'-prefixed. The compiler substitutes the leading '/admin'
// with '{_adminFrontName}'. Visually consistent with the URL the user sees.
#[Maho\Config\Route('/admin/catalog/product/edit/{id}')]
public function editAction() { }
```

Both compile to the same route. Existing core admin controllers use the `/admin`-prefixed form for visual continuity. The runtime validates the matched front name against the configured admin path, so forged admin URLs (e.g. someone hitting `/admin/...` when you've configured `/backoffice`) fall through to noroute.

#### Configuring the admin frontname

There are two supported ways to set it:

**1. `app/etc/local.xml`** (file-tracked, deploy-time):

```xml
<config>
    <admin>
        <base_path>backoffice</base_path>
    </admin>
</config>
```

This is the value the installer writes via `--admin_frontname` and is the right place for fixed, environment-pinned configuration. Run `./maho cache:flush` after editing.

**2. System Configuration UI** (DB-backed, runtime-toggled):

*System → Configuration → Advanced → Admin → Admin Base URL*:

- **Use Custom Admin Path**: `Yes`
- **Custom Admin Path**: `backoffice`

This stores `admin/url/use_custom_path` and `admin/url/custom_path` in `core_config_data`. Use it when the admin can change their own admin path without touching files. The DB value takes precedence over `<admin><base_path>` when `Use Custom Admin Path` is `Yes`.

Either way, the runtime compares the configured value against the URL segment, so `/admin/...` stops working as soon as you set a custom path, regardless of which mechanism you used.

#### Migrating from the legacy `<frontName>` declaration

Pre-26.5 Maho (and M1/OpenMage) stored this at `<admin><routers><adminhtml><args><frontName>`. The new runtime reads `<admin><base_path>` only, so the old node is silently ignored and a custom frontname declared there leaves the admin reachable only at `/admin/...`.

Existing installations carry the legacy declaration in `app/etc/local.xml`. To migrate:

```bash
./maho legacy:migrate-routes        # rewrites local.xml in place
./maho cache:flush
```

`./maho health-check` (and *System → Tools → Healthcheck* in the backend) reports the legacy declaration if it's still present.

## Overriding controllers

!!! info "v26.7+"
    Since v26.7, the preferred way to replace a core controller is to **subclass it** - no XML, no attribute. The legacy `<routers><args><modules>` chain still works as a back-compatibility shim (see [Legacy XML chain](#legacy-xml-chain-back-compatibility)).

At `composer dump-autoload` the compiler detects any controller that extends a route-owning controller and declares no `#[Route]` of its own, and points that route at your subclass. This works in **every area** - frontend, admin, and install:

```php
// Just works - no XML, no attribute. Run `composer dump-autoload` after adding it.
class Vendor_MyModule_Checkout_CartController extends Mage_Checkout_CartController
{
    #[\Override]
    public function indexAction()
    {
        // your override
    }
}
```

- **Precedence is structural.** When several modules override the same controller they should form a single inheritance chain (`C extends B extends Core`); the most-derived class wins, deterministically and independent of module load order.
- Two *sibling* subclasses extending the same base independently are a **conflict**: the compiler logs an error naming both and falls back to module load order (local/community over core). Resolve it by having one override extend the other.
- A subclass that adds **new** actions still needs its own `#[Route]` for those actions - inheritance only carries over the base's existing routes.

### Legacy XML chain (back-compatibility)

Magento 1's "module chain" override declared in `config.xml` still works, and **wins over the compiled inheritance override**, so existing modules behave identically until you choose to migrate.

**Admin** - register your module under the admin chain and ship a subclass with the same controller name:

```xml
<config>
    <admin>
        <routers>
            <adminhtml>
                <args>
                    <modules>
                        <Vendor_MyModule before="Mage_Adminhtml">Vendor_MyModule_Adminhtml</Vendor_MyModule>
                    </modules>
                </args>
            </adminhtml>
        </routers>
    </admin>
</config>
```

```php
class Vendor_MyModule_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Catalog_ProductController
{
    #[\Override]
    public function editAction()
    {
        // your override
    }
}
```

**Frontend** - same pattern via `<frontend>`, with the router code matching the front name you're overriding (here `customer`):

```xml
<config>
    <frontend>
        <routers>
            <customer>
                <args>
                    <modules>
                        <Vendor_MyModule before="Mage_Customer"/>
                    </modules>
                </args>
            </customer>
        </routers>
    </frontend>
</config>
```

A `Vendor_MyModule_AccountController extends Mage_Customer_AccountController` then takes precedence over the core controller.

The runtime walks the chain at dispatch time, ordered by the `before`/`after` attributes, *before* falling back to the compiled lookup - so an XML override always wins over an inheritance-based one.

### Migrating an override chain

`legacy:migrate-routes` migrates these `<modules>` override chains too: because override controllers already extend the core controller, the inheritance compiler auto-registers them, so migrating just means deleting the XML.

```bash
./maho legacy:migrate-routes --dry-run   # preview
./maho legacy:migrate-routes             # apply, then run composer dump-autoload
```

A `<modules>` node is removed **only** when every declared override is a clean subclass of a routed controller that re-implements inherited actions. Anything needing a human is reported and the XML left untouched, so re-running after a manual fix is safe:

- a controller that isn't a subclass of a routed controller;
- one that adds un-routed actions (add a `#[Route]` for them first);
- sibling modules overriding the same controller with no shared inheritance chain (detected even when the conflicting overrides live in *separate* module `config.xml` files) - make one extend the other.

## Generating URLs

URL generation continues to use the standard Maho APIs:

```php
Mage::getUrl('catalog/product/view', ['id' => 5]);
// → http://example.com/catalog/product/view/5/
// `id` is a path variable of the route, so it is substituted into the path.
// Params that are NOT path variables become query-string args on the frontend
// (e.g. ?foo=bar); on admin URLs they are appended as /key/value segments.

$this->getUrl('*/*/edit', ['id' => $product->getId()]);
```

Internally these resolve through the compiled `maho_url_generator.php` and the reverse-lookup maps in `maho_attributes.php` (controller → frontName/path). You do not need to know the route `name` to generate URLs from controller code; the legacy `module/controller/action` form keeps working.

When you need a URL for a **named route** specifically (useful when you've declared `name:` on the attribute), use the route name with the helper:

```php
Mage::getUrl('catalog.product.view', ['id' => 5]);
```

## Recompiling PHP attributes

Compiled artifacts are produced by `composer dump-autoload`, but in two situations you may want to recompile without dropping to the CLI:

- During module development, when you've added a `#[Route]`, `#[Observer]`, or `#[CronJob]` to an existing class and want to test the change without leaving the browser.
- On a hosted environment where you can't easily run `composer dump-autoload` interactively.

For these cases Maho ships a **"Recompile PHP Attributes"** button in the admin panel:

> *System → Cache Management → Additional Cache Management → Recompile PHP Attributes*

The button calls `Maho::recompilePhpAttributes()`, which invokes the compiler runtime (`AttributeCompiler::compileRuntime()`) directly. No composer binary, no subprocess, no dependency on PATH or shell access. It rewrites the three attribute artifacts atomically (temp file + rename, so a request mid-read never sees a torn file):

- `vendor/composer/maho_attributes.php`
- `vendor/composer/maho_url_matcher.php`
- `vendor/composer/maho_url_generator.php`

A separate compiler additionally rewrites `vendor/composer/maho_api_permissions.php`.

After writing, the action calls `opcache_reset()` so the next request picks up the new files.

!!! note
    The admin button covers the same ground as `composer dump-autoload` for attribute compilation, but does not refresh Composer's class autoloader. If you've **added a brand-new PHP file** (not just edited an existing one), you still need `composer dump-autoload` so Composer indexes the new class.

## How it works under the hood

- **Matching** uses Symfony's `CompiledUrlMatcher` reading the static array dumped to `vendor/composer/maho_url_matcher.php`. The compiled form is opcached, so route lookup is effectively a hash table read.
- **Generation** uses Symfony's `CompiledUrlGenerator` reading `maho_url_generator.php`, also opcached.
- **Controller overrides** are resolved by `Maho\Routing\ControllerDispatcher` in three tiers: the XML `<args><modules>` chain (M1 BC) is walked first, then the compiled `controllerLookup` - which already points at the most-derived inheritance-based override of a route-owning base - and finally the route's own controller. So an XML-declared override wins over an inheritance-based one, which wins over the base, preserving Magento 1's "first declared wins" semantics.
- **Legacy XML routes** (`<frontend><routers><MyMod><use>standard</use>...`) are matched by `ControllerDispatcher::dispatchLegacyPath()` *after* the Symfony matcher misses, so attribute-defined routes always take priority. A single `LOG_NOTICE` is emitted once per process listing legacy front names that are still in use.
- **Performance**: 6.3 μs mean / 8 μs p99 per match in the new system, vs 46 μs / 93 μs in the legacy router chain (40k matches, opcache on).

## Detecting legacy XML in your modules

`./maho health-check` scans `app/code/local` and `app/code/community` for legacy XML declarations that have PHP-attribute equivalents and reports them as warnings. Three checks cover the v26.5 migration:

- **Legacy XML Routing**: `<frontend|admin|install><routers>...<use>standard|admin|install</use>` declarations. Migrate to `#[Maho\Config\Route]`.
- **Legacy XML Observers**: `<events>` blocks under any scope (`<global>`, `<frontend>`, `<adminhtml>`, `<crontab>`). Migrate to `#[Maho\Config\Observer]`.
- **Legacy XML Cron Jobs**: `<crontab><jobs>...<run>` declarations. Migrate to `#[Maho\Config\CronJob]`.

Each finding lists the module name, the source file, and (for routes) the front name and area, so you know exactly where to migrate. The same warnings appear on *System → Tools → Healthcheck* in the admin panel. Findings are warnings, not errors: the BC shim keeps existing modules working, so migration is encouraged but not required.

## Automated migration

Three commands automate the bulk of the XML → PHP-attribute migration. Each scans `app/code/local` and `app/code/community`, inserts the equivalent attribute on the target method, and removes the migrated XML block. All three accept `--dry-run` to preview changes without writing.

```bash
./maho legacy:migrate-observers --dry-run
./maho legacy:migrate-cron --dry-run
./maho legacy:migrate-routes --dry-run
```

| Command | Migrates | Notes |
|---------|----------|-------|
| `legacy:migrate-observers` | `<events>` blocks under any scope | Resolves the `<class>` alias via `Mage::getConfig()`. The XML `<observer_name>` is preserved as an explicit `id:` argument so any `replaces:` references in third-party modules keep working. |
| `legacy:migrate-cron` | `<crontab><jobs>` declarations with `<run><model>alias::method</model>` | Reads `<schedule><cron_expr>` or `<schedule><config_path>` and emits `schedule:` / `configPath:` accordingly. |
| `legacy:migrate-routes` | `<frontend\|admin\|install><routers>` declarations with `<use>standard\|admin\|install</use>`, plus `<args><modules>` controller-override chains | Walks every `*Controller.php` file in the module and emits one `#[Route]` per `*Action` method. Default-action paths are expanded into the M1-equivalent shorter forms (e.g. `IndexController::indexAction` gets routes for `/front`, `/front/index`, and `/front/index/index`). Override `<modules>` chains are dropped once their controllers are clean subclasses (see [Migrating an override chain](#migrating-an-override-chain)). |

After running any of them, run `composer dump-autoload` (or click *Recompile PHP Attributes* on the cache page) so the new attributes get compiled.

!!! warning "Review the route migration"
    `legacy:migrate-routes` is best-effort: it generates one route per action method using the M1 URL-derivation rules. Modules that relied on subtler routing behavior (custom request parameters, layered overrides, non-standard frontName resolution) may need manual cleanup. Always review the generated attributes and run your test suite before committing.

## Migrating from XML

Legacy XML route declarations still work via a back-compatibility shim. Migration is recommended but not forced. The [`legacy:migrate-routes`](#automated-migration) command automates most of this.

**Before (config.xml):**

```xml
<config>
    <frontend>
        <routers>
            <helloworld>
                <use>standard</use>
                <args>
                    <module>Mahotutorial_Helloworld</module>
                    <frontName>helloworld</frontName>
                </args>
            </helloworld>
        </routers>
    </frontend>
</config>
```

**After (PHP attributes):**

```php
class Mahotutorial_Helloworld_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/helloworld', name: 'helloworld.index')]
    #[Maho\Config\Route('/helloworld/index', name: 'helloworld.index.index')]
    public function indexAction()
    {
        // ...
    }
}
```

Remove the `<frontend><routers><helloworld>` block from `config.xml` after migrating, then run `composer dump-autoload`. The `LOG_NOTICE` for the front name disappears.

### Removed APIs

The following components were removed in v26.5 (no shim is provided; replace direct calls):

- `Mage_Core_Controller_Varien_Router_Standard`
- `Mage_Core_Controller_Varien_Router_Admin`
- `Mage_Install_Controller_Router_Install`
- `Mage_Core_Model_Url_Rewrite_Request` and the `core/url_rewrite_request` alias
- `Mage_Core_Controller_Varien_Front::getRouterByRoute()`
- `Mage_Core_Controller_Varien_Front::getRouterByFrontName()`
- The `<request_rewrite>` config node and `web/routers` entries for `admin`/`standard`/`install`

For URL lookups previously handled by `getRouterByRoute()` / `getRouterByFrontName()`, use `Maho\Routing\RouteCollectionBuilder` or the standard URL helpers (`Mage::getUrl()`).

### What was kept

`Mage_Core_Controller_Varien_Router_Abstract` is still the base for CMS, Blog, and Default routers, and any third-party router registered via `web/routers` config. Custom routers participating in URL rewriting (CMS pages, blog posts) continue to work unchanged.
