# AGENTS.md

Maho is an open-source ecommerce platform forked from OpenMage. It keeps the Magento 1
MVC/module/layout architecture but has replaced the entire Zend/Varien legacy with PHP 8.3+,
Symfony components, Doctrine DBAL, and Monolog.

## Essential Commands

```bash
composer lint                      # All linters (cs-fixer, rector, phpstan) in dry-run
composer lint:cs-fixer             # Code style only
composer lint:rector               # Rector only
composer lint:phpstan              # PHPStan only (level 6)
vendor/bin/php-cs-fixer fix        # Apply code style fixes (writes changes)
vendor/bin/rector -c .rector.php   # Apply rector fixes (writes changes)

composer test                      # Full suite. SLOW and battery-hungry; see Testing before running
composer test -- --testsuite=Backend   # One suite: Install|Backend|Frontend|Api|Browser
composer test:pgsql                # Same, against PostgreSQL (also: test:sqlite)

./maho cache:flush                 # Flush all caches
./maho index:reindex:all           # Reindex all indexes
./maho db:query "QUERY"            # One-shot SQL query
composer dump-autoload             # REQUIRED after changing any Maho\Config attribute
```

## Architecture

### Bootstrapping

```php
require 'vendor/autoload.php';
Mage::app();
```

### Module structure

```
app/code/core/Mage/[ModuleName]/     # legacy core modules (Magento/OpenMage lineage)
app/code/core/Maho/[ModuleName]/     # Maho-namespace modules (preferred for new modules)
├── Block/          # View blocks
├── Helper/         # Helper classes
├── Model/          # Business logic and data access
├── controllers/    # Request handling
├── etc/            # config.xml, system.xml
├── sql/            # Schema migrations
└── data/           # Data install scripts
```

Other key paths:

- `app/etc/local.xml`: main install config (DB, cache); `app/etc/config.xml`: base config
- `app/etc/modules/*.xml`: module declarations
- `app/design/{adminhtml,frontend,install}/`: themes
- `app/locale/[locale]/`: CSV translations
- `lib/Maho/`: `Maho\*` library code (DBAL adapter, config attributes)
- `lib/MahoCLI/Commands/`: `./maho` CLI commands

### Database access (Doctrine DBAL)

Replaces all Zend_Db components. Adapter: `Maho\Db\Adapter\AdapterInterface`.
Query builder: `Maho\Db\Select` (wraps Doctrine QueryBuilder).

```php
$adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
$select = $adapter->select()
    ->from(['p' => 'catalog_product'], ['entity_id', 'sku'])
    ->where('status = ?', 1)
    ->order('created_at DESC');

// Raw SQL expressions
$select->columns(['total' => new Maho\Db\Expr('COUNT(*)')]);

// Direct queries
$result = $adapter->fetchAll($select);
$adapter->insert('table_name', ['column' => 'value']);
$adapter->update('table_name', ['column' => 'new_value'], 'id = 1');
$adapter->delete('table_name', 'id = 1');
```

**Portability matters**: the test suite runs against MySQL, PostgreSQL, and SQLite. Prefer the
query builder and adapter helpers over raw SQL strings; avoid MySQL-only syntax and functions.

### Schema changes

**Never modify historical install or upgrade scripts**: they are immutable snapshots of the
schema at a given version. To change the schema, bump the module version in `etc/config.xml`
and add a new `upgrade-X.Y.Z-A.B.C.php` (or `maho-X.Y.Z.php`) script. Fresh installs run
install plus every upgrade in sequence, so the new script repairs both fresh and existing
installs. This applies even to "obvious cleanups" (e.g. adding a missing explicit `default`).

### Configuration via PHP attributes

Observers, cron jobs, routes, and API resources are declared with PHP attributes in
`lib/Maho/Config/`, **not** in XML. They are compiled into `vendor/composer/maho_*.php`,
so **run `composer dump-autoload` after any change**. See each attribute class's docblock
for the full parameter list.

```php
#[Maho\Config\Observer('catalog_product_save_after')]
public function handleEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\Observer('event_name', area: 'frontend')]
public function handleFrontendEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\CronJob('my_cron_job', schedule: '0 2 * * *')]
public function runJob(Mage_Cron_Model_Schedule $schedule) {}
```

- Prefer the global area (default, omit `area:`) unless the observer must be area-restricted
- REST/GraphQL resources use `#[Maho\Config\ApiResource]`, a drop-in subclass of API Platform's
  `ApiResource` that adds Maho permission metadata (`mahoLabel`, `mahoSection`, `mahoOperations`,
  `mahoCustomerScoped`). Most `maho*` fields are auto-derived; set them only when the default is
  wrong. See `app/code/core/Mage/Core/Api/Store.php` for a worked example.

### Routing

Routes are declared with `#[Maho\Config\Route]` on controller action methods. The attribute is
repeatable: stack multiple attributes for multiple paths or method lists.

```php
#[Maho\Config\Route('/catalog/product/view/{id}', name: 'catalog.product.view', methods: ['GET'], requirements: ['id' => '\d+'])]
public function viewAction() { ... }
```

Parameters: `path` (required), `name` (auto-derived from `class::method` if omitted), `methods`
(HTTP allow-list; empty = any), `defaults`, `requirements` (per-param regex), `area`
(`frontend`|`adminhtml`|`install`).

`area` is auto-detected from the controller base class: descendants of
`Mage_Adminhtml_Controller_Action` / `Maho\Controller\AdminAction` → `adminhtml`;
`Mage_Install_Controller_Action` / `Maho\Controller\InstallAction` → `install`; everything
else → `frontend`. Override only when needed.

**Admin routes**: the compiler resolves the admin frontName at runtime (`use_custom_admin_path`),
so never hard-code it. Both forms compile to the same route: a bare path
(`#[Route('/catalog/product/edit/{id}')]`, compiler prepends `{_adminFrontName}/`) and an
`/admin`-prefixed path (compiler substitutes the leading `/admin`). Core admin controllers use
the `/admin`-prefixed form for visual continuity with the URL.

**Back-compat**: modules still declaring `<frontend><routers>` in `config.xml` keep working via a
legacy-XML match path that runs *before* the Symfony matcher, preserving M1's "first declared
wins" precedence. A single `LOG_NOTICE` per process lists legacy frontNames to encourage migration.

### Overriding controllers

Preferred: **subclass the controller you want to override.** The compiler detects any controller
extending a route-owning controller that declares no `#[Route]` of its own, and repoints the
route at the subclass. Works in every area, with no XML and no attribute.

```php
class My_Module_Checkout_CartController extends Mage_Checkout_CartController { /* override actions */ }
```

- **Precedence is structural.** When several modules override the same controller they should
  form a single inheritance chain (B extends A extends Core); the most-derived class wins,
  deterministically and regardless of module load order. Two *sibling* subclasses extending the
  same base independently are a conflict: the compiler logs an error and falls back to module
  load order (local/community over core). Resolve it by having one override extend the other.
- A subclass that adds **new** actions needs its own `#[Route]` for those actions (inheritance
  only carries over the base's existing routes).
- The legacy XML chain (`<{area}><routers><{routerCode}><args><modules><MyMod before|after="Mage_X"/>`)
  is still honored and wins over the compiled override. Migrate existing chains with
  `./maho legacy:migrate-routes`. Use the inheritance approach for new code.

### Other key systems

- **Events**: `Mage::dispatchEvent('event_name', ['data' => $data])`
- **Layout**: XML-based block hierarchy and template assignment
- **Sessions**: `Mage::getSingleton('customer/session')`, `'admin/session'`, `'checkout/session'`
- **Translations**: `$this->__('Text')`, CSVs in `app/locale/[locale]/`
- **Collections**: `Mage::getResourceModel('catalog/product_collection')->addAttributeToSelect('*')->addFieldToFilter('status', 1)`
- **Errors**: `Mage::throwException()` for user-facing errors (`Mage_Core_Exception`),
  `Mage::log()` / `Mage::logException()` for logging

## Development Guidelines

### Removed components (never use in new code)

All Zend Framework and Varien components have been deleted:

- **Zend_\*** (Zend_Log, Zend_Date, Zend_Db, Zend_Json, Zend_Validate, Zend_Filter, Zend_Http,
  Zend_Cache, Zend_Pdf, Zend_Exception); see Modernized APIs below for replacements
- **Varien_\*** → `Maho\*`. Mechanical rename `Varien_X_Y` → `Maho\X\Y`, except
  `Varien_Object` → `Maho\DataObject`, `Varien_Filter_Array` → `Maho\Filter\ArrayFilter`,
  `Varien_Filter_Object` → `Maho\Filter\ObjectFilter`
- **TinyMCE** → TipTap 3.x (`public/js/mage/adminhtml/wysiwyg/tiptap/`)
- **prototypejs / jQuery** → modern vanilla JS

### General

- Use `declare(strict_types=1)` (placed *after* the file-level docblock), PHP 8.3+ features,
  and the `#[\Override]` attribute on overridden methods
- Type everything that can be typed: parameter, return, and property types (including `void`,
  `never`, nullable, union, and intersection types). Reserve docblock `@param`/`@return` for what
  the type system can't express (array shapes, generics, `@throws`); don't restate a native type
- Default to **no comments**. Add one only when the code can't carry the information itself: a
  non-obvious *why*, a workaround, a subtle invariant. Keep it to one line where possible. Never
  narrate what the code already says, and don't leave section banners, changelog notes, or
  commentary about the edit itself
- **Never use em dashes** (`—`) in anything you write, rephrase, or use a comma, colon, or parentheses
- CSS: modern features, no IE/legacy browser support
- JS AJAX: always use `mahoFetch()` instead of native `fetch()`
- New tools/libraries: always use the latest available version
- Feel free to modify core files directly; avoid creating a new module unless asked. When you do
  need one, declare it in `app/etc/modules/`
- Before committing, ensure new translatable strings (`$this->__()`,
  `Mage::helper()->__()`) exist in `app/locale/en_US/`

### File headers (SPDX)

Dual-licensed: source code (PHP, JS, CSS) under `OSL-3.0`; templates, config, and assets
(PHTML, XML, HTML) under `AFL-3.0`.

New PHP files get a single `SPDX-FileCopyrightText` line with the current year and Maho as
holder. Add a short class description on the first line ending with a period (it becomes the
phpDocumentor summary); omit it if the class name is self-explanatory rather than writing filler:

```php
/**
 * Short class description ending with a period.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Module
 */
```

- The SPDX block is tight (no blank lines inside); `@package` follows with no blank line above.
  The phpDocumentor CI workflow strips ` * SPDX-` lines before generating docs.
- Non-PHP files: XML/HTML use `<!-- ... -->`, JS uses `//` line comments, CSS uses `/* ... */`
  (not `//`), each with `SPDX-FileCopyrightText:` and `SPDX-License-Identifier:` lines.
- **Existing files**: preserve inherited Magento/OpenMage copyright lines verbatim; don't add
  yourself (git history is the attribution log). Update the Maho year range only on files you're
  already modifying. Translate an existing `@license` URL to its SPDX identifier
  (`osl-3.0` → `OSL-3.0`, `afl-3.0` → `AFL-3.0`) rather than reassigning by extension.
- With multiple holders, order newest-maintainer-first: Maho, then OpenMage, then Magento, then
  other third parties (by copyright year, newest first). Keep the priority among those present.
- Use the `spdx-headers` skill to migrate a file or directory from the legacy
  `@copyright`/`@license` format.

## Modernized APIs

### Logging (Monolog)

`Mage::LOG_*` constants follow standard syslog levels (EMERGENCY through DEBUG):

```php
Mage::log('Error occurred', Mage::LOG_ERROR);
Mage::log('Debug info', Mage::LOG_DEBUG, 'custom.log');
Mage::logException($e); // Logs to exception.log at ERROR level
```

### HTTP client (Symfony HttpClient)

```php
$client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
$response = $client->request('GET', $url);
$data = $response->getContent();
```

### JSON, validation, filtering

```php
Mage::helper('core')->jsonEncode($data);
Mage::helper('core')->jsonDecode($data); // both throw \JsonException on error

Mage::helper('core')->isValidNotBlank($value);
Mage::helper('core')->isValidEmail($value);
Mage::helper('core')->isValidRegex($value, '/pattern/');
Mage::helper('core')->isValidLength($value, $min, $max);
Mage::helper('core')->isValidRange($value, $min, $max);
Mage::helper('core')->isValidUrl($value);
Mage::helper('core')->isValidDate($value);      // also isValidDateTime(), isValidIp()

Mage::helper('core')->filterEmail($email);
Mage::helper('core')->filterUrl($url);
Mage::helper('core')->filterInt($value);
Mage::helper('core')->filterFloat($value);

Mage::app()->getLocale()->normalizeNumber($qty);
Mage::app()->getLocale()->formatCurrency($amount, $currencyCode);
```

### Dates (native PHP DateTime)

**Mental model:** DB columns always store UTC as `'Y-m-d H:i:s'`. Never store store-local
strings, they're ambiguous across stores. Convert on the way in (`storeToUtc`) and on the way out
(`utcToStore`). Pick the helper by destination, not by output: `formatDateForDb()` for DB-bound
strings, `nowUtc()`/`todayUtc()` for non-DB UTC strings (logs, CSV, API payloads),
`utcToStore()->format(...)` for display. The first two produce identical output; the call site
announces which one it means, so keep them separate.

```php
$locale = Mage::app()->getLocale();

// DB-bound strings: the only entry point for anything headed to a DB column
$locale->formatDateForDb('now');                               // 'Y-m-d H:i:s' (UTC), current time
$locale->formatDateForDb($date, withTime: false);              // normalize arbitrary input to 'Y-m-d'

// Non-DB UTC strings (static methods)
Mage_Core_Model_Locale::nowUtc();                              // 'Y-m-d H:i:s' (UTC)
Mage_Core_Model_Locale::todayUtc();                            // 'Y-m-d' (UTC)

// Conversions: always return DateTimeImmutable; caller formats explicitly
$locale->utcToStore();                                         // "now" in store TZ
$locale->utcToStore($store, $utcInput);                        // store TZ
$locale->storeToUtc($store, $storeInput);                      // UTC

$dt->format(Mage_Core_Model_Locale::DATETIME_FORMAT);          // 'Y-m-d H:i:s'
$dt->format(Mage_Core_Model_Locale::DATE_FORMAT);              // 'Y-m-d'
$dt->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);    // 'Y-m-d\TH:i'
```

**Pitfalls:**

- Don't pass `nowUtc()` to a store-local field; it's UTC. For store-local now, use
  `$locale->utcToStore()` and format from the `DateTimeImmutable`.
- `utcToStore()` / `storeToUtc()` return `DateTimeImmutable`: `->setTime()` / `->modify()` return
  new instances, so chain directly or reassign (`$d = $d->modify('-1 day')`).
- Maho forces PHP's default timezone to UTC at bootstrap, but pass DateTime objects with explicit
  TZ (or plain strings/ints) rather than bare `new DateTime('...')` when precision matters.
- For locale-aware display ("April 16, 2026" vs "16 avril 2026"), use
  `Mage::helper('core')->formatDate()`, not `DateTimeImmutable::format()`.
- There is deliberately no `nowInStoreTimezone()`: a store-local *string* has no TZ tag, so storing
  one breaks the "DB is always UTC" invariant.

### Other replacements

- **PDF generation**: DomPdf with HTML/CSS templates; extend `Mage_Core_Block_Pdf` (Zend_Pdf removed)
- **Cache**: native Maho cache system (Zend_Cache removed)

## Testing (Pest PHP)

**Do not run `composer test` by default. Leave it to CI**, which runs every suite on every PR
across seven DB backends (`.github/workflows/pest.yml`). Each local invocation rebuilds the test
database from scratch (reinstall with sample data, full reindex, API server), a multi-minute cost
paid even with `--filter`, so narrowing saves nothing. Run it locally only when asked, when
changing the test harness or install/upgrade scripts, or to reproduce a CI failure, and say so
first. `composer lint` is cheap; run it freely.

**Write tests regardless**, and prefer TDD for features and bugfixes alike: the failing test comes
first, so it encodes the requirement rather than the finished code. A bugfix test must fail against
the unfixed code. Ordering is free; the red/green loop isn't, so verify once at the end instead of
re-running after each edit.

Suites live in `tests/{Install,Backend,Frontend,Api,Browser}/` with base test cases
`Tests\Maho{Install,Backend,Frontend,Api}TestCase`. The `Browser` suite needs Playwright; when it
isn't installed, a plain `composer test` silently runs only `Install,Backend,Frontend`.

```php
uses(Tests\MahoFrontendTestCase::class);

it('can process customer orders', function () {
    // Test code
});
```

## Security Patterns

- **ALWAYS use `getParam()`** for request parameters in controllers; `getUserParam()` only checks
  route params and breaks query strings
- Define `public const ADMIN_RESOURCE` in admin controllers for ACL
- Use `_setForcedFormKeyActions()` for state-changing actions (delete, save, etc.)
- Validate/sanitize user input at the model layer
- Doctrine DBAL parameterized queries are automatic

### Rate limiting & honeypot (shared `core` helper)

Throttle public endpoints and trap bots with the shared `Mage_Core_Helper_Data` factories; do not
roll a per-feature limiter. They return a `\Maho\Security\RateLimiter` (sliding window of
`$maxAttempts` hits per `$windowSeconds`). **Core owns request identity**: callers never read the
client IP or session id themselves, they name a scope and core resolves it. A non-positive
`$maxAttempts` disables a limiter, so no call-site `if ($limit <= 0)` guard is needed.

```php
use Maho\Security\RateLimitScope;

// Default scope is Client = IP, falling back to session id when the IP is unknown.
// Other scopes: RateLimitScope::Ip, ::Session.
$limiter = Mage::helper('core')->rateLimiter('myfeature', 5, 3600);   // namespace, max, window
if (!$limiter->attempt()) {
    // blocked: surface your own message (AJAX/API stay silent)
}

// Scope by a value you already hold (email, store id, order ref), not request identity.
if (!Mage::helper('core')->rateLimiterBy('myfeature_email', $email, 1, 86400)->attempt()) {
    // blocked
}

// Check up front, record only on failure (see Mage_Sales_Helper_Guest). ipRateLimiter() is the
// store-config-governed IP limiter (system/rate_limit/*); null when disabled or IP unknown.
$limiter = Mage::helper('core')->ipRateLimiter();
if ($limiter?->tooManyAttempts()) { /* blocked: present "Too Soon" */ }
// ...later, on a failed attempt only:
$limiter?->hit();
```

`attempt()` is check-and-record; `tooManyAttempts()` is a pure read; `hit()` records explicitly;
`remaining()` and `clear()` round out the object. Counters are cache-backed (tag
`\Maho\Security\RateLimiter::CACHE_TAG`), so a full cache flush resets every window. Keep
must-persist security counters (e.g. forgot-password) on durable storage instead.

```php
// Honeypot: render a visually-hidden trap field, then check it server-side. The field name is
// install-specific. The on/off toggle is the caller's concern: gate both the render and the
// check behind your module's own default-on `*/honeypot_enabled` flag.
echo Mage::helper('core')->getHoneypotFieldHtml();               // in the template
if (Mage::getStoreConfigFlag('mymodule/abuse/honeypot_enabled')
    && Mage::helper('core')->isHoneypotTriggered($request->getPost())) {
    // silently drop (works for $request->getPost() and decoded API bodies alike)
}
```

### Sanitizing rich content (template directives)

**Never call `filter()` on content whose `{{...}}` directives are still unresolved**: a directive
isn't valid HTML, so the filter mangles it into a broken `%7B%7B…` URL.

```php
// Persisted content → sanitize on save, in the resource model's _beforeSave().
// 2nd arg forces links to a new tab: true for article-style content (blog), off for CMS/catalog.
// 3rd arg is REQUIRED in practice: the processor that will actually render this content.
$object->setData('content', Mage::getSingleton('core/input_filter_maliciousCode')
    ->filterPreservingDirectives($object->getData('content'), false,
        Mage::helper('cms')->getPageTemplateProcessor()));

// Non-persisted preview → resolve first, then filter the resolved markup.
Mage::getSingleton('core/input_filter_maliciousCode')->filter($template->getProcessedTemplate());
```

The masking pattern is a security boundary: whatever it matches is restored **unsanitized**. Don't
loosen it. Three rules, none sufficient alone:

- **Only mask what the renderer resolves, and only if it runs at all.** A directive with no
  handler is emitted verbatim, so masking one hands the payload to the browser. This is
  per-renderer, not global (the catalog filter resolves 5 keywords, the CMS one 13), so always pass
  the real processor. No renderer means no preservation: if you can't name the processor that will
  resolve these directives, there isn't one. A render path that *can't* resolve them must call
  `Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives()` rather than emit them (see
  `Mage_Catalog_Helper_Output`).
- **The body must be well-formed `name="value"` params.** Excluding `<`/`>` isn't enough: an
  attribute is closed by a quote, so `" onerror="alert(1)` breaks out without an angle bracket.
- **No param may be named like an event handler** (`on` + letters). A well-formed param is itself a
  well-formed HTML attribute, so `{{media url="a" onerror="alert(1)"}}` satisfies both rules above
  (the keyword resolves, the body parses), yet emitted verbatim inside `alt="…"` the parser ends the
  attribute at the directive's first quote and reads `onerror` as the next tag attribute. Only
  `on` + letters is rejected, so a widget param such as `on_sale` still masks.

`var`/`depend`/`if` are never masked: they render verbatim when no template vars are assigned.

## Git Conventions

**Commits**

- **NEVER** include "Co-Authored-By: Claude" or any AI attribution
- **NEVER** mention Claude, AI, or assistants in commit messages
- Keep commits professional and focused only on code changes

**Pull request titles**

- Plain, descriptive, with **no** conventional-commit prefix (`feat(...)`, `fix(...)`, etc.)
- Past tense, describing what was done (e.g. "Added schema.org structured data for products
  and blog posts")
- Spell out what the change delivers rather than using a vague summary

## Be Brief

Applies to issue and PR bodies, review comments, replies on GitHub, and answers in chat.

- Say what changed and why, then stop. A few sentences or bullets beat a structured report
- No test-plan checklists, no "Summary/Changes/Impact" headings, no restating the diff
- Skip preamble, recap, and self-congratulation; don't pad with caveats already understood
- Answer the question that was asked, not the adjacent ones
