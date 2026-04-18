# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Maho is an open-source ecommerce platform forked from OpenMage, designed for medium-to-small on-premise projects. It's based on the Magento 1 architecture but modernized with PHP 8.3+ support and contemporary development tools.

## Essential Commands

```bash
vendor/bin/php-cs-fixer fix        # Fix code style (lint)
vendor/bin/phpstan analyze         # Run static analysis (level 6)
vendor/bin/rector -c .rector.php
./maho cache:flush                 # Flush all caches
composer test                        # Run all tests (Install → Backend → Frontend)
composer test -- --testsuite=Frontend # Run frontend tests only
composer test -- --testsuite=Backend  # Run backend tests only
composer test -- --testsuite=Install  # Run install tests only
./maho index:reindex:all           # Reindex all indexes
./maho db:query "QUERY"            # Execute a one-shot SQL query
```

## Architecture Overview

### Bootstrapping
```php
require 'vendor/autoload.php';
Mage::app();
```

### MVC Pattern
- **Models** (`Model/`): Business logic and data access
- **Views** (`Block/` and templates): Presentation layer
- **Controllers** (`controllers/`): Request handling

### Module Structure
```
app/code/core/Mage/[ModuleName]/
├── Block/          # View blocks
├── Helper/         # Helper classes
├── Model/          # Business logic
├── controllers/    # Controllers
├── etc/            # Configuration (config.xml, system.xml)
├── sql/            # Database migrations
└── data/           # Data install scripts
```

### Key Configuration Files
- `app/etc/local.xml`: Main configuration (DB, cache, etc.)
- `app/etc/config.xml`: Base configuration
- `app/etc/modules/*.xml`: Module declarations

### Theme Structure
```
app/design/
├── adminhtml/      # Admin panel themes
├── frontend/       # Frontend themes
└── install/        # Installer theme
```

### Database Access (Doctrine DBAL 4.4)
Replaces all Zend_Db components. Adapter: `Maho\Db\Adapter\AdapterInterface`. Query builder: `Maho\Db\Select` (wraps Doctrine QueryBuilder).

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

### Other Key Systems
- **Events**: `Mage::dispatchEvent('event_name', ['data' => $data])` - Observers defined via PHP attributes (see below)
- **Layout**: XML-based configuration with block hierarchy and template assignment
- **Sessions**: `Mage::getSingleton('customer/session')`, `'admin/session'`, `'checkout/session'`
- **Translations**: CSV files in `app/locale/[locale]/` - Use `$this->__('Text')` in code
- **Collections**: `Mage::getResourceModel('catalog/product_collection')->addAttributeToSelect('*')->addFieldToFilter('status', 1)`
- **Errors**: `Mage::throwException()` for user-facing errors, `Mage::log()` for logging

### Observers and Cron Jobs (PHP Attributes)
Observers and cron jobs are defined via `#[Maho\Config\Observer]` and `#[Maho\Config\CronJob]` PHP attributes on methods — **not** in XML. Run `composer dump-autoload` after any changes. See the attribute class docblocks in `lib/Maho/Config/` for all parameters.

```php
#[Maho\Config\Observer('catalog_product_save_after')]
public function handleEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\Observer('event_name', area: 'frontend')]
public function handleFrontendEvent(\Maho\Event\Observer $observer) {}

#[Maho\Config\CronJob('my_cron_job', schedule: '0 2 * * *')]
public function runJob(Mage_Cron_Model_Schedule $schedule) {}
```

- Prefer global area (default, omit `area:`) unless the observer must be restricted to a specific area
- Do **not** define observers or cron jobs in `config.xml`

## Development Guidelines

### Critical Rules — Removed Components
All Zend Framework and Varien components have been completely removed. **NEVER** use any of these in new code:
- Zend_* classes (Zend_Log, Zend_Date, Zend_Db, Zend_Json, Zend_Validate, Zend_Filter, Zend_Http, Zend_Cache, Zend_Pdf, Zend_Exception)
- Varien_* prefixed classes — use `Maho\*` namespace instead (see Modernizations)
- TinyMCE — use TipTap 3.x
- prototypejs or jquery — use modern vanilla JS

### General Guidelines
- CSS: use modern features, no IE/legacy browser support
- JS AJAX: always use `mahoFetch()` instead of native `fetch()`
- New tools/libraries: always use latest available version
- Update PHP file headers with current year for the Maho copyright line
- New PHP files: only Maho copyright with current year:
```php
/**
 * Maho
 *
 * @package    Mage_Module
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
```
- Before committing, ensure translatable strings (`$this->__()` or `Mage::helper()->__()`) are in `app/locale/en_US/`

### Adding New Features
- New modules: `app/code/core/Maho/` namespace, declared in `app/etc/modules/`
- Follow existing module patterns; use `declare(strict_types=1)` and PHP 8.3+ features
- Use `#[\Override]` attribute for overridden methods
- When overriding admin routes in Maho modules, use `before="Mage_Adminhtml"` pattern

### Modifying Existing Features
- Do not increment module version in `config.xml`
- Feel free to modify core files directly
- Avoid creating a new module unless asked for it

## Modernizations

### Logging (Monolog)
`Mage::LOG_*` constants follow standard syslog levels (EMERGENCY through DEBUG):
```php
Mage::log('Error occurred', Mage::LOG_ERROR);
Mage::log('Debug info', Mage::LOG_DEBUG, 'custom.log');
Mage::logException($e); // Logs to exception.log at ERROR level
```

### HTTP Client (Symfony HttpClient)
```php
$client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
$response = $client->request('GET', $url);
$data = $response->getContent();
```

### JSON Handling
```php
Mage::helper('core')->jsonEncode($data);
Mage::helper('core')->jsonDecode($data); // throws Mage_Core_Exception_Json on error
```

### Validation
```php
Mage::helper('core')->isValidNotBlank($value);
Mage::helper('core')->isValidEmail($value);
Mage::helper('core')->isValidRegex($value, '/pattern/');
Mage::helper('core')->isValidLength($value, $min, $max);
Mage::helper('core')->isValidRange($value, $min, $max);
Mage::helper('core')->isValidUrl($value);
Mage::helper('core')->isValidDate($value);
```

### Date Handling (Native PHP DateTime)

**Mental model:**
- DB columns always store UTC as `'Y-m-d H:i:s'`. Never store store-local strings — they're ambiguous across stores.
- Convert **on the way in** (`storeToUtc` user input → UTC for DB) and **on the way out** (`utcToStore` DB value → store TZ for display/computation).
- Separate concerns by destination: `formatDateForDb()` is the single entry point for DB-bound strings (including `'now'` for current time). `nowUtc()` / `todayUtc()` are for non-DB UTC strings (logs, CSV exports, API payloads). The output happens to match, but the call-site intent is different — keep them separate.
- Method names encode the timezone — `nowUtc()` returns UTC, `utcToStore()` returns store-local. You should be able to tell which TZ a value is in by reading the call site alone.

**API:**

```php
$locale = Mage::app()->getLocale();

// DB-bound strings — use formatDateForDb() for anything headed to a DB column
$locale->formatDateForDb('now');                               // 'Y-m-d H:i:s' (UTC) — current time for DB
$locale->formatDateForDb('now', withTime: false);              // 'Y-m-d' (UTC) — current date for DB
$locale->formatDateForDb($date);                               // normalize arbitrary input to 'Y-m-d H:i:s'
$locale->formatDateForDb($date, withTime: false);              // normalize arbitrary input to 'Y-m-d'

// Non-DB UTC strings — logs, CSV exports, API payloads, etc.
$locale->nowUtc();                                             // 'Y-m-d H:i:s' (UTC)
$locale->todayUtc();                                           // 'Y-m-d' (UTC)

// "Now" in store timezone — for computation/display. Use utcToStore() with no args.
$locale->utcToStore();                                         // DateTimeImmutable in store TZ
$locale->utcToStore()->format('Y-m-d');                        // today in store TZ

// Convert a known date between timezones — always returns DateTimeImmutable
$dt = $locale->utcToStore($store, $utcInput);                  // DateTimeImmutable in store TZ
$dt = $locale->storeToUtc($store, $storeInput);                // DateTimeImmutable in UTC

// Caller formats the result explicitly — no magic strings
$dt->format(Mage_Core_Model_Locale::DATETIME_FORMAT);          // 'Y-m-d H:i:s'
$dt->format(Mage_Core_Model_Locale::DATE_FORMAT);              // 'Y-m-d'
$dt->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);    // 'Y-m-d\TH:i'
```

**Common pitfalls:**
- Don't use `nowUtc()` for DB inserts/updates — use `formatDateForDb('now')`. Same output, but the call site announces its DB-binding intent, and keeps `formatDateForDb` as the single choke point for DB-bound date formatting.
- Don't pass `nowUtc()` to a store-local field — it's UTC, not store time. If you need store-local now, use `$locale->utcToStore()` and format from the DateTimeImmutable.
- `utcToStore()` / `storeToUtc()` return `DateTimeImmutable` — mutators like `->setTime()` and `->modify()` return a new instance, so either chain directly (`utcToStore()->setTime(0,0,0)->format(...)`) or reassign (`$d = $d->modify('-1 day')`).
- Don't rely on PHP's default timezone — Maho forces it to UTC at bootstrap, but pass DateTime objects with explicit TZ (or just strings/ints) rather than bare `new DateTime('...')` when precision matters.
- For locale-aware display formatting (e.g. "April 16, 2026" in en_US, "16 avril 2026" in fr_FR), use `Mage::helper('core')->formatDate()`, not `DateTimeImmutable::format()`.

**Why there's no `nowInStoreTimezone()` / `nowStore()`:** deliberate. A store-local *string* has no TZ tag, so storing one in the DB breaks the "DB is always UTC" invariant and produces different instants in multi-store setups. For computation or display you want a `DateTimeImmutable` anyway — `utcToStore()` with no args returns exactly that. If you catch yourself wanting a store-local now-as-string, step back: you probably want either `formatDateForDb('now')` (for the DB), `nowUtc()` (for logs/CSV/API), or `utcToStore()->format(...)` (for display).

### Filtering & Locale
```php
Mage::app()->getLocale()->normalizeNumber($qty);
Mage::app()->getLocale()->formatCurrency($amount, $currencyCode);
Mage::helper('core')->filterEmail($email);
Mage::helper('core')->filterUrl($url);
Mage::helper('core')->filterInt($value);
Mage::helper('core')->filterFloat($value);
```

### Varien → Maho Namespace
`Varien_X_Y` → `Maho\X\Y`. Exceptions: `Varien_Object` → `Maho\DataObject`, `Varien_Filter_Array` → `Maho\Filter\ArrayFilter`, `Varien_Filter_Object` → `Maho\Filter\ObjectFilter`.

### WYSIWYG Editor (TipTap 3.x)
Files: `public/js/mage/adminhtml/wysiwyg/tiptap/{extensions,setup}.js` and `tiptap.css`

### Other Components

- **Exceptions**: Use `Mage_Core_Exception` (Zend_Exception removed)
- **PDF Generation**: Use DomPdf with HTML/CSS templates. Extend `Mage_Core_Block_Pdf` (Zend_Pdf removed)
- **Cache**: Use native Maho cache system (Zend_Cache removed)

## Testing (Pest PHP)

Test contexts: `MahoFrontendTestCase`, `MahoBackendTestCase`, `MahoInstallTestCase`

```php
uses(Tests\MahoFrontendTestCase::class);

it('can process customer orders', function () {
    // Test code
});
```

## Security Patterns

- **ALWAYS use `getParam()`** for request parameters in controllers — `getUserParam()` only checks route params and breaks query strings
- Define `public const ADMIN_RESOURCE` in admin controllers for ACL
- Use `_setForcedFormKeyActions()` for state-changing actions (delete, save, etc.)
- Validate/sanitize user input at the model layer
- Doctrine DBAL parameterized queries are automatic

## Git Commit Rules
- **NEVER** include "Co-Authored-By: Claude" or any AI attribution in commits
- **NEVER** mention Claude, AI, or assistant in commit messages
- Keep commits professional and focused only on code changes
