# AGENTS.md

Maho is an open-source ecommerce platform forked from OpenMage. It keeps the Magento 1
MVC/module/layout architecture but has replaced the entire Zend/Varien legacy with PHP 8.5+,
Symfony components, Doctrine DBAL, and Monolog.

## Essential Commands

```bash
composer lint                      # All linters (cs-fixer, rector, phpstan) in dry-run; lint:* runs one
vendor/bin/php-cs-fixer fix        # Apply code style fixes to .php (writes changes)
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.phtml.php   # Same, for .phtml
vendor/bin/rector                  # Apply rector fixes (writes changes)

composer test                      # Full suite. SLOW and battery-hungry; see Testing before running
composer test -- --testsuite=Backend   # One suite: Install|Backend|Frontend|Api|Browser
composer test:pgsql                # Same, against PostgreSQL (also: test:sqlite)

./maho cache:flush                 # Flush all caches
./maho index:reindex:all           # Reindex all indexes
./maho db:query "QUERY"            # One-shot SQL query

./maho dev:frontend:theme:build    # Compile the Tailwind skins (--theme, --watch)
./maho dev:frontend:theme:create   # Scaffold a new theme
./maho dev:frontend:theme:export   # Write the admin theme settings out as a theme.css
npm install                        # Build toolchain, also copies the pinned JS libs into public/js

./maho import:sample-data          # Install a whole sample data package
./maho list import                 # One importer per entity (products, customers, cms, ...)
```

## Architecture

### Key paths

- `app/code/core/Mage/`: legacy core modules. `app/code/core/Maho/`: new modules go here
- `lib/Maho/`: `Maho\*` library code (DBAL adapter, config attributes)
- `lib/Maho/Import/`: CSV importers behind the `import:*` commands and the sample data installer
- `lib/MahoCLI/Commands/`: `./maho` CLI commands
- `public/skin/frontend/`: Tailwind theme sources under `base/default/src/`, compiled bundles
  committed next to them; see the README in that folder

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

**Renames** are invisible to a structural diff, so declare them in `sql/schema.php` on the
table the rename produced. Rename the object as usual, then record what it used to be called:

```php
$t = $schema->createTable('sales_flat_order');
Renamer::renamed($t, from: 'sales_order', columns: ['customer_email' => 'customer_mail']);
```

`from:` and each `columns:` value take one name or a newest-first list: a column renamed `a` to
`b` to `c` declares `['b', 'a']`. The `Renamer` class docblock describes when a rename runs.
Drop entries once upgrades from that release are no longer supported.

**Never use a vendor prefix in an identifier.** A table, a store-config section, a cron id and an
observer id take the name of the module or the domain, not `maho` or `mage`: `blog_post_entity`,
`feedmanager_feed`, `paypal_webhook_event`, section `feedmanager`. A former name recorded through
`Renamer` is the one exception, since it must stay verbatim.
`tests/Backend/Unit/Maho/NamingConventionTest.php` enforces this for core modules.

### Typed accessors

Accessors are real typed methods, not `@method` lines; `#1283` tracks the conversion, wave by wave.
Raw data lives in `_data`, and the accessor is where the type applies:

```php
public function getStoreId(): ?int
{
    $value = $this->getData('store_id');
    return $value === null ? null : (int) $value;
}

public function setStoreId(?int $value): static
{
    return $this->setData('store_id', $value);
}

public function setIsActive(?bool $value = true): static
{
    return $this->setData('is_active', $value);
}
```

- The type comes from the column in `sql/schema.php`. Without a table, it comes from what the
  callers write, and the old annotation is checked against them, not trusted.
- A SMALLINT column that holds only 0 and 1 is `?bool` in the accessor: the getter casts `(bool)`,
  the setter takes `?bool`, and the adapter stores 0/1. A column with more states stays `?int`.
- A setter whose only parameter is `?bool` defaults it to `true`, so `setIsActive()` reads as the
  sentence it is and only the negative case spells its argument. A union that mixes a flag with a
  value, such as `bool|float`, takes no default. A parent and its overrides carry the same default,
  or PHP rejects the signature.
- Getters are nullable, since a new model holds no data. Bodies call `getData()`, never
  `_getData()`: some models decrypt or override in `getData()`.
- Setters take the narrow type (a union for polymorphic values, never `mixed`) and return `static`.
- `has` and `uns` stay on `__call`. A session getter keeps `bool $clear = false` and forwards it
  to `getData()`.
- Read every call site: a `getFoo(true)` that `__call` forwarded becomes an `arguments.count`
  error. Fix the method; CI fails a pull request whose PHPStan baseline grows.
- A parent and its subclasses convert in one commit when a subclass overrides the accessor.

### Configuration via PHP attributes

Observers, cron jobs, routes, message handlers, and API resources are declared with PHP
attributes in `lib/Maho/Config/`, **not** in XML. They are compiled into
`vendor/composer/maho_*.php`, so **run `composer dump-autoload` after any change**. See each
attribute class's docblock for the full parameter list.

```php
#[Maho\Config\Observer('catalog_product_save_after')]
public function handleEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\Observer('event_name', area: 'frontend')]
public function handleFrontendEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\CronJob('my_cron_job', schedule: '0 2 * * *')]
public function runJob(Mage_Cron_Model_Schedule $schedule) {}

#[Maho\Config\MessageHandler]
public function __invoke(My_Module_Model_SomeMessage $message): void {}
```

- Prefer the global area (default, omit `area:`) unless the observer must be area-restricted
- REST/GraphQL resources use `#[Maho\Config\ApiResource]`, a drop-in subclass of API Platform's
  `ApiResource` that adds Maho permission metadata (`mahoLabel`, `mahoSection`, `mahoOperations`,
  `mahoCustomerScoped`). Most `maho*` fields are auto-derived; set them only when the default is
  wrong. See `app/code/core/Mage/Core/Api/Store.php` for a worked example.
- An HTTP QUERY collection operation (`ApiPlatform\Metadata\Query`, RFC 10008) receives its body as
  `$context['filters']` through `Maho\ApiPlatform\State\QueryBodyFiltersProvider`, so a provider
  serves GET and QUERY with one code path. Import it as `HttpQuery` next to the GraphQL `Query`.

### Routing

Routes are declared with `#[Maho\Config\Route]` on controller action methods. The attribute is
repeatable: stack multiple attributes for multiple paths or method lists.

```php
#[Maho\Config\Route('/catalog/product/view/{id}', name: 'catalog.product.view', methods: ['GET'], requirements: ['id' => '\d+'])]
public function viewAction() { ... }
```

`area` is auto-detected from the controller base class: descendants of
`Mage_Adminhtml_Controller_Action` / `Maho\Controller\AdminAction` → `adminhtml`;
`Mage_Install_Controller_Action` / `Maho\Controller\InstallAction` → `install`; everything
else → `frontend`. Override only when needed.

**Admin routes**: the compiler resolves the admin frontName at runtime (`use_custom_admin_path`),
so never hard-code it. Both forms compile to the same route: a bare path
(`#[Route('/catalog/product/edit/{id}')]`, compiler prepends `{_adminFrontName}/`) and an
`/admin`-prefixed path (compiler substitutes the leading `/admin`). Core admin controllers use
the `/admin`-prefixed form for visual continuity with the URL.

### Overriding controllers

**Subclass the controller you want to override.** The compiler repoints the routes of the base
at any subclass that declares no `#[Route]` of its own. This works in every area, with no XML.

```php
class My_Module_Checkout_CartController extends Mage_Checkout_CartController { /* override actions */ }
```

- Several overrides of one controller form a single chain (B extends A extends Core), and the
  most-derived class wins. Two sibling subclasses of one base are a conflict: make one extend
  the other.
- A subclass that adds **new** actions needs its own `#[Route]` for them. In the admin area, keep
  the controller segment of the path equal to the base's: the admin secret key is keyed on it.
- A legacy XML `<routers>` chain still wins over the compiled override. Migrate it with
  `./maho legacy:migrate-routes`.

### Other key systems

- **CLI commands**: one class per command under `lib/MahoCLI/Commands/`, extending `BaseMahoCommand`. Declare
  the input on `__invoke()` with `#[Argument]` and `#[Option]` parameters; there is no `configure()` and no
  `execute()`. A parameter named `$jobCode` maps to `--job-code` unless `name:` is set. Inject
  `OutputInterface`, `InputInterface` or `SymfonyStyle` as plain parameters when needed:

  ```php
  public function __invoke(
      OutputInterface $output,
      #[Argument(description: 'Job code', name: 'job_code')] ?string $jobCode = null,
      #[Option(description: 'Unlock every schedule', name: 'all')] bool $unlockAll = false,
  ): int {
  ```
- **Async queue**: `\Maho\Queue\QueueManager::dispatch($messageDto)` queues a flat DTO for a
  `#[Maho\Config\MessageHandler]` method (message class inferred from the first parameter type);
  cron keeps a detached `queue:work` worker alive per pool, with retries/backoff and an admin
  grid under System > Message Queue. Worker pools split latency classes: `fast` is resident,
  `slow` is the on-demand catch-all. Pass `queue:` to `dispatch()`, then route that queue with
  `<global><queue><routing><yourqueue>fast</yourqueue></routing></queue></global>`; anything
  unrouted lands in the catch-all, so a long-running handler never blocks short ones. Pool
  resourcing (count, memory/time limits, idle timeout) lives under `global/queue/pools`. A crash
  parks a claimed message for an operator instead of redelivering it: retry or discard it in the
  grid. A handler may run as long as it needs: the worker refreshes its claim on Symfony's
  keepalive alarm, so only a worker that actually died is reported as abandoned (the refresh
  needs pcntl and cannot land while the handler holds an open transaction on the shared
  connection, so such a worker can be misreported after 5 minutes). Worker startup
  failures land in `var/log/queue-worker.log`; production installs should prefer supervisord or
  systemd over the cron watchdog
- **Errors**: `Mage::throwException()` for user-facing errors (`Mage_Core_Exception`),
  `Mage::log()` / `Mage::logException()` for logging

## Development Guidelines

### Removed components (never use in new code)

All Zend Framework and Varien components have been deleted:

- **Zend_\*** (Zend_Log, Zend_Date, Zend_Db, Zend_Json, Zend_Validate, Zend_Filter, Zend_Http,
  Zend_Cache, Zend_Pdf, Zend_Exception); see Modernized APIs below for replacements.
  `Zend_Http` → Symfony HttpClient
- **Varien_\*** → `Maho\*`. Mechanical rename `Varien_X_Y` → `Maho\X\Y`, except
  `Varien_Object` → `Maho\DataObject`, `Varien_Filter_Array` → `Maho\Filter\ArrayFilter`,
  `Varien_Filter_Object` → `Maho\Filter\ObjectFilter`
- **TinyMCE** → TipTap 3.x (`public/js/mage/adminhtml/wysiwyg/tiptap/`)
- **prototypejs / jQuery** → modern vanilla JS

### General

- Use `declare(strict_types=1)` (placed *after* the file-level docblock), PHP 8.5+ features,
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
- A `.phtml` template declares its block type in its own docblock below the SPDX block, never
  inside it. PHPStan analyses templates, so an untyped `$this` hides every error in the file:

  ```php
  /**
   * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
   * SPDX-License-Identifier: AFL-3.0
   * @package base_default
   */

  /** @var Mage_Catalog_Block_Product_View $this */
  ```
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

### JSON, validation, filtering

`Mage::helper('core')` holds the replacements: `jsonEncode()` and `jsonDecode()` (both throw
`\JsonException`), the `isValid*()` validators (`isValidEmail()`, `isValidUrl()`, ...) and the
`filter*()` filters (`filterEmail()`, `filterInt()`, ...). For numbers and money, use
`Mage::app()->getLocale()->normalizeNumber()` and `formatCurrency()`.

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

**A red test is a disagreement, not a verdict.** Name what settles it before touching either side:
a spec, an RFC, a documented invariant. Fix the wrong side, and say which one it was.

Suites live in `tests/{Install,Backend,Frontend,Api,Browser}/` with base test cases
`Tests\Maho{Install,Backend,Frontend,Api}TestCase`. The `Browser` suite needs Playwright; when it
isn't installed, a plain `composer test` silently runs only `Install,Backend,Frontend`. The
comments in `.github/workflows/pest.yml` explain the CI shards and how to refresh them.

## Security Patterns

- **ALWAYS use `getParam()`** for request parameters in controllers; `getUserParam()` only checks
  route params and breaks query strings
- Define `public const ADMIN_RESOURCE` in admin controllers for ACL
- Storefront CSRF is automatic: `Mage_Core_Controller_Front_Action::preDispatch()` validates the
  form key on every request that is not GET, HEAD or OPTIONS, so a storefront action never calls
  `_validateFormKey()`. A refused request gets a 403 JSON body when it is AJAX, and a redirect to
  the referer with an error message otherwise. An action that changes state accepts POST only.
  A POST form renders `getBlockHtml('formkey')`; `js.js` adds the key only as a fallback.
  Put an action in `$_publicActions` only when a third party must reach it with its own
  credential (a payment webhook, an OAuth endpoint, an unsubscribe link)
- Admin CSRF is automatic only for a logged-in admin: POST validates the form key, GET validates
  the per-action secret key every admin url carries. An action that runs before login (see
  `Mage_Adminhtml_IndexController`) gets no automatic check, so it calls `_validateFormKey()`
  itself. Admin `$_publicActions` skips only the GET secret key check, never the POST form key
  check: use it only for read-only endpoints
- Validate/sanitize user input at the model layer
- **Never pass user input as template text to a template filter** (`filter($userString)`). Pass it as a
  variable instead: `{{var}}` emits a value verbatim and never rescans it, so a directive inside a
  customer name stays inert text. Template text is code (`{{var obj.anyMethod()}}` calls it), so only
  admin-owned content may be filtered. `tests/Backend/Integration/Core/Model/EmailTemplateVariableInertTest.php`
  locks the invariant
- Doctrine DBAL parameterized queries are automatic

### Rate limiting & honeypot (shared `core` helper)

Throttle public endpoints with `Mage::helper('core')->rateLimiter()` (scoped to the client) or
`rateLimiterBy()` (scoped to a value you hold, such as an email). Do not write a per-feature
limiter, and do not read the client IP or session id: name a `RateLimitScope` and core resolves
it. `ipRateLimiter()` is deprecated.

- A public endpoint reads its budget from `system/rate_limit/<key>`. Ship a non-zero default in
  `config.xml` and a field under "Per-endpoint Limits" in `system.xml`, or the limit is off.
- To record only failures, check with `tooManyAttempts()` and call `hit()` on a failed attempt
  (see `Mage_Sales_Helper_Guest`).
- Counters live in the cache, so a cache flush resets them. Keep a counter that must persist
  (for example forgot-password) in durable storage.
- Honeypot: echo `getHoneypotFieldHtml()` in the form and check `isHoneypotTriggered()` on the
  server. Gate both behind a default-on `*/honeypot_enabled` flag of your module.

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

A render path that cannot resolve directives must call
`Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives()` instead of emitting them (see
`Mage_Catalog_Helper_Output`). The masking pattern is a security boundary: whatever it matches is
restored **unsanitized**. Read the `MaliciousCode` docblocks before you change it.

## Git Conventions

**Commits** describe only the code change. **NEVER** add "Co-Authored-By: Claude", any other AI
attribution, or a mention of Claude, AI, or assistants.

**Pull request titles**

- Plain, descriptive, with **no** conventional-commit prefix (`feat(...)`, `fix(...)`, etc.)
- Past tense, describing what was done (e.g. "Added schema.org structured data for products
  and blog posts")
- Spell out what the change delivers rather than using a vague summary

## Write Simple Technical English

Applies to comments, docblocks, class and method names, messages, and commit messages. A reader
who is not a native English speaker must understand them on the first read.

- Short sentences, one fact each, active voice, present tense. No idioms, no clever phrasing
- One word for one thing. Do not rotate synonyms
- A name says what the thing is or the one action it does: `jsEscape()`, `deleteMessage()`
- Bad: "Backslash-escape one quote character in a value that a template places inside a quoted
  JavaScript string, so the value cannot close that string."
  Good: "Put a backslash before each $quote in $data. Use the result inside a JavaScript
  string that the template quotes with the same $quote."

## Long tasks

- When a step does not need a maintainer decision, keep going. Put status notes in the
  same message as the next action.
- Stop and ask only when you cannot continue without an answer, or before a destructive
  action: a force-push, a push to `main`, a delete of data in a real database, or a change
  outside this repository.
- Do not end a run with an offer to continue, or with a list of choices that do not block
  the work.

## Be Brief

Applies to issue and PR bodies, review comments, replies on GitHub, and answers in chat.

- Start with what you need from the maintainer: an open decision, or a change to approve
- Mark anything that you could not confirm, and say where you looked
- Say what changed and why, then stop. A few sentences or bullets beat a structured report
- No test-plan checklists, no "Summary/Changes/Impact" headings, no restating the diff
- Skip preamble, recap, and self-congratulation; don't pad with caveats already understood
- Answer the question that was asked, not the adjacent ones
