# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Maho is an open-source ecommerce platform forked from OpenMage, designed for medium-to-small on-premise projects. It's based on the Magento 1 architecture but modernized with PHP 8.3+ support and contemporary development tools.

## Essential Commands

### Code Quality & Standards
```bash
vendor/bin/php-cs-fixer fix        # Fix code style (lint)
vendor/bin/phpstan analyze         # Run static analysis
vendor/bin/rector -c .rector.php
```

### Cache Management
```bash
./maho cache:flush        # Flush all caches
```

### Testing
```bash
composer test                        # Run all tests (Install → Backend → Frontend)
composer test -- --testsuite=Frontend # Run frontend tests only  
composer test -- --testsuite=Backend  # Run backend tests only
composer test -- --testsuite=Install  # Run install tests only
```

### Database & Indexing
```bash
./maho index:list         # List all indexes
./maho index:reindex      # Reindex specific index
./maho index:reindex:all  # Reindex all indexes
./maho db:query "QUERY"   # Execute a one-shot SQL query
```

## Architecture Overview

### Bootstrapping Maho
To bootstrap Maho in any PHP script, simply require the Composer autoloader:
```php
require 'vendor/autoload.php';
Mage::app();
// That's it! Maho is now bootstrapped and ready to use
```
No need for complex initialization - the autoloader handles everything.

### MVC Pattern
Maho follows a traditional MVC architecture:
- **Models** (`Model/`): Business logic and data access
- **Views** (`Block/` and templates): Presentation layer
- **Controllers** (`controllers/`): Request handling

### Module Structure
Each module follows this structure:
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

### Database Access Pattern (Doctrine DBAL)
Maho uses **Doctrine DBAL 4.3** for all database operations. This is a complete replacement of Zend_Db - no Zend Framework database components remain in the codebase.

**Basic Pattern:**
- Models extend `Mage_Core_Model_Abstract`
- Resource models handle database operations
- Collections for querying multiple records
- Database adapter: `Maho\Db\Adapter\AdapterInterface` (replaces `Zend_Db_Adapter_Abstract`)
- Query builder: `Maho\Db\Select` wraps Doctrine's QueryBuilder

**Query Building:**
```php
// Using Maho\Db\Select (wraps Doctrine QueryBuilder)
$adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
$select = $adapter->select()
    ->from(['p' => 'catalog_product'], ['entity_id', 'sku'])
    ->where('status = ?', 1)
    ->order('created_at DESC');

// Raw SQL expressions - use Maho\Db\Expr to prevent quoting
$select->columns([
    'total' => new Maho\Db\Expr('COUNT(*)'),
    'sum' => new Maho\Db\Expr('SUM(price)')
]);
```

**Direct Queries:**
```php
// Read query
$result = $adapter->fetchAll($select);

// Write operations
$adapter->insert('table_name', ['column' => 'value']);
$adapter->update('table_name', ['column' => 'new_value'], 'id = 1');
$adapter->delete('table_name', 'id = 1');
```

**Transactions:**
```php
// Nested transactions are safe (counted internally)
$adapter->beginTransaction();
try {
    // operations
    $adapter->commit();
} catch (Exception $e) {
    $adapter->rollBack();
    throw $e;
}
```

### Event System
Maho uses an event-driven architecture:
```php
Mage::dispatchEvent('event_name', ['data' => $data]);
```
Observers are configured in module's `config.xml`.

### Layout System
- XML-based layout configuration
- Block hierarchy system
- Template assignment via layout XML

### Session Management
- Customer sessions: `Mage::getSingleton('customer/session')`
- Admin sessions: `Mage::getSingleton('admin/session')`
- Checkout sessions: `Mage::getSingleton('checkout/session')`

### Translation System
- CSV-based translations in `app/locale/[locale]/`
- Helper method: `$this->__('Text to translate')`
- Admin translations: `Mage::helper('adminhtml')->__('Text')`

## Development Guidelines

### Critical Rules
- **NEVER use Zend Framework components** - They have been completely removed from Maho. Use the modern alternatives documented above.
- **NEVER use Varien_Date or Zend_Date** - Use native PHP DateTime and `Mage_Core_Model_Locale` methods.
- **NEVER use Zend_Db or Zend_Db_Select directly** - Use `Maho\Db\Select` and `Maho\Db\Adapter\AdapterInterface`.
- **NEVER use Varien_ prefixed classes in new code** - All Varien classes have been moved to the Maho namespace. Use the new `Maho\*` classes instead (see Varien Migration section below).

### General Guidelines
- When you write CSS, use the most modern features, do not care for Internet Explorer or old unsupported browsers.
- When you write Javascript, never use prototypejs or jquery, only the most modern vanillajs
- When making AJAX requests in JavaScript, always use `mahoFetch()` instead of the native `fetch()` API for consistency and proper error handling
- If you're integrating new tools/libraries, always use their latest available version
- Update headers of the PHP files, adding the current year for the copyright Maho line
- For new PHP files, only include Maho copyright with the current year - no other entities:
```php
/**
 * Maho
 *
 * @package    Mage_Module
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
```
- Before committing, ensure all translatable strings (`$this->__()` or `Mage::helper()->__()`) are present in the corresponding CSV files in `app/locale/en_US/`

### Adding New Features
- If you've to create a new module, use the `app/code/core/Maho/` namespace 
- Declare module in `app/etc/modules/`
- Follow existing module patterns for consistency
- Add strict typing to all new code and use modern PHP8.3+ features
- New Maho modules now include:
  - `Maho_AdminActivityLog`: Tracks admin actions and logins
  - `Maho_Captcha`: CAPTCHA functionality
- When overriding admin routes in Maho modules, use `before="Mage_Adminhtml"` pattern

### Modifying Existing Features
- Do not increment module's version number in module's `config.xml`
- Feel free to modify the files in the core, there's no problem with that
- Avoid creating a new module unless asked for it

### Database Changes
- Use setup scripts in `sql/maho_setup/YY.MM.[incremental number].php`

### Working with Collections
```php
$collection = Mage::getResourceModel('catalog/product_collection')
    ->addAttributeToSelect('*')
    ->addFieldToFilter('status', 1);
```

### Error Handling
- Exceptions extend module-specific exception classes
- Use `Mage::throwException()` for user-facing errors
- Log errors with `Mage::log()`

## Logging System (Monolog)

Maho uses **Monolog** for all logging operations. Zend_Log has been completely removed from the codebase.

### Log Level Constants
Use the Mage constants - Zend_Log constants no longer exist:
```php
// OLD - Don't use these anymore
Zend_Log::ERR, Zend_Log::WARN, Zend_Log::DEBUG

// NEW - Use these Mage constants
Mage::LOG_EMERGENCY  // System is unusable
Mage::LOG_ALERT      // Action must be taken immediately  
Mage::LOG_CRITICAL   // Critical conditions
Mage::LOG_ERROR      // Error conditions
Mage::LOG_WARNING    // Warning conditions
Mage::LOG_NOTICE     // Normal but significant
Mage::LOG_INFO       // Informational messages
Mage::LOG_DEBUG      // Debug-level messages

// Example usage
Mage::log('Error occurred', Mage::LOG_ERROR);
Mage::log('Debug info', Mage::LOG_DEBUG, 'custom.log');
Mage::logException($e); // Logs to exception.log at ERROR level
```

## HTTP Client (Symfony HttpClient)

Maho uses **Symfony HttpClient** for all HTTP operations. Varien_Http_Client and Zend_Http have been completely removed.

### Usage:
```php
// OLD - Don't use
new Varien_Http_Client();
new Varien_Http_Adapter_Curl();

// NEW - Use this
$client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
$response = $client->request('GET', $url);
$data = $response->getContent();
```

## JSON Handling

Maho uses **native PHP JSON functions** wrapped in Core Helper. Zend_Json has been completely removed.

### Usage:
```php
// OLD - Don't use
Zend_Json::encode($data);
Zend_Json::decode($data);

// NEW - Use this
Mage::helper('core')->jsonEncode($data);
Mage::helper('core')->jsonDecode($data);

// Exception handling changed too
try {
    $data = Mage::helper('core')->jsonDecode($json);
} catch (Mage_Core_Exception_Json $e) {
    // Handle JSON errors
}
```

## Validation (Symfony Validator)

Maho uses **Symfony Validator** wrapped in Core Helper. Zend_Validate has been completely removed.

### Usage:
```php
// OLD - Don't use
Zend_Validate::is($value, 'NotEmpty');
Zend_Validate::is($value, 'EmailAddress');
Zend_Validate::is($value, 'Regex', ['/pattern/']);

// NEW - Use these helper methods
Mage::helper('core')->isValidNotBlank($value);
Mage::helper('core')->isValidEmail($value);
Mage::helper('core')->isValidRegex($value, '/pattern/');
Mage::helper('core')->isValidLength($value, $min, $max);
Mage::helper('core')->isValidRange($value, $min, $max);
Mage::helper('core')->isValidUrl($value);
Mage::helper('core')->isValidDate($value);
```

## Date Handling (Native PHP DateTime)

Maho uses **native PHP DateTime** for all date operations. Zend_Date and Varien_Date have been completely removed. Maho has migrated to native HTML5 date inputs with proper timezone handling.

### Key Concepts
- **Database storage**: Always in UTC timezone using `'Y-m-d H:i:s'` format
- **Display/Input**: Converted to store timezone using HTML5 formats
- **Store timezone**: Configured per store (default: UTC)

### Date Format Constants
```php
Mage_Core_Model_Locale::DATETIME_FORMAT;       // 'Y-m-d H:i:s' (database)
Mage_Core_Model_Locale::DATE_FORMAT;           // 'Y-m-d'
Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT; // 'Y-m-d\TH:i' (for datetime-local inputs)
```

### Converting Database Dates to HTML5 Format (for display)
Use `storeDate()` to convert UTC dates from database to store timezone for HTML5 inputs:

```php
// Convert date (without time) to HTML5 format
$htmlDate = Mage::app()->getLocale()->storeDate(null, $dbDate, false, 'html5');
// Returns: "2025-01-15" (in store timezone)

// Convert datetime (with time) to HTML5 format
$htmlDateTime = Mage::app()->getLocale()->storeDate(null, $dbDateTime, true, 'html5');
// Returns: "2025-01-15T14:30" (in store timezone)

// Parameters:
// - $store: Store ID (null = current store)
// - $date: Date string from database, DateTime object, or timestamp
// - $includeTime: false for date-only, true for datetime
// - $format: 'html5' for HTML5 native inputs
```

### Converting HTML5 Input to UTC (for database storage)
Use `utcDate()` to convert HTML5 input values back to UTC for database storage:

```php
// Convert HTML5 date input to UTC for database
$utcDate = Mage::app()->getLocale()->utcDate(null, $inputDate, false, 'html5');
// Input: "2025-01-15" (in store timezone)
// Returns: "2025-01-15 00:00:00" (in UTC)

// Convert HTML5 datetime-local input to UTC for database
$utcDateTime = Mage::app()->getLocale()->utcDate(null, $inputDateTime, true, 'html5');
// Input: "2025-01-15T14:30" (in store timezone)
// Returns: "2025-01-15 14:30:00" (in UTC, adjusted if store timezone differs)

// Parameters:
// - $store: Store ID (null = current store)
// - $date: HTML5 input value (YYYY-MM-DD or YYYY-MM-DDTHH:mm)
// - $includeTime: false for date-only, true for datetime
// - $format: 'html5' for HTML5 native inputs
```

### Common Usage Patterns

**In Grid Filters (display):**
```php
// Convert database date to HTML5 input value
$fromValue = Mage::app()->getLocale()->storeDate(null, $fromDate, false, 'html5') ?? '';
$toValue = Mage::app()->getLocale()->storeDate(null, $toDate, false, 'html5') ?? '';
```

**In Grid Filters (processing input):**
```php
// Convert HTML5 input value to UTC for database queries
$fromDate = Mage::app()->getLocale()->utcDate(null, $value['from'], false, 'html5');
$toDate = Mage::app()->getLocale()->utcDate(null, $value['to'], false, 'html5');
```

**In Form Fields:**
```php
// Display: Convert model date to HTML5 input
<input type="date" value="<?= Mage::app()->getLocale()->storeDate(null, $model->getCreatedAt(), false, 'html5') ?>" />

// Process: Convert submitted HTML5 value to UTC before saving
$model->setCreatedAt(Mage::app()->getLocale()->utcDate(null, $this->getUserParam('date'), false, 'html5'));
```

### Legacy Methods (still available)
```php
// Get current datetime/date as string
Mage_Core_Model_Locale::now();    // Current datetime in 'Y-m-d H:i:s' format
Mage_Core_Model_Locale::today();  // Current date in 'Y-m-d' format
```

### Important Notes
- Always store dates in UTC in the database
- Always use `storeDate()` with `'html5'` format when populating HTML5 date/datetime-local inputs
- Always use `utcDate()` with `'html5'` format when processing HTML5 date/datetime-local inputs
- HTML5 inputs automatically respect the user's browser locale for display while using ISO 8601 format internally

## Filtering & Locale (Native PHP)

Maho uses Core Helpers for filtering. Zend_Filter has been completely removed.

### Usage:
```php
// OLD - Don't use
new Zend_Filter_LocalizedToNormalized();
$filter = new Zend_Filter_Email();

// NEW - Use these helper methods
Mage::app()->getLocale()->normalizeNumber($qty);
Mage::app()->getLocale()->formatCurrency($amount, $currencyCode);
Mage::helper('core')->filterEmail($email);
Mage::helper('core')->filterUrl($url);
Mage::helper('core')->filterInt($value);
Mage::helper('core')->filterFloat($value);
```

## Varien Migration (Maho Namespace)

All Varien classes have been migrated to the **Maho namespace**. Class aliases exist for backward compatibility, but **always use the new Maho classes in new code**.

### Naming Pattern
- `Varien_Class_Name` → `Maho\Class\Name`
- `Varien_Object` → `Maho\DataObject` (special case)
- `Varien_Filter_Array` → `Maho\Filter\ArrayFilter`
- `Varien_Filter_Object` → `Maho\Filter\ObjectFilter`

### Examples
```php
// OLD - Don't use in new code
new Varien_Object();
new Varien_Data_Form();
new Varien_Io_File();
new Varien_Event_Observer();

// NEW - Always use these
new Maho\DataObject();
new Maho\Data\Form();
new Maho\Io\File();
new Maho\Event\Observer();
```

## Other Modernizations

All remaining Zend Framework components have been removed:

- **Exceptions**: Use `Mage_Core_Exception` for custom exception classes. Zend_Exception has been removed.
- **PDF Generation**: Use **DomPdf** with HTML/CSS templates. Zend_Pdf coordinate-based drawing has been removed. Extend `Mage_Core_Block_Pdf` for PDF blocks.
- **Filters**: Use `Mage::helper('core')->filter*()` methods. Zend_Filter has been removed.
- **Cache**: Use native Maho cache system. Zend_Cache has been removed.

## Testing Approach
Maho uses Pest PHP as its testing framework with comprehensive test coverage:

### Test Framework (Pest PHP)
- **Pest Framework**: Modern PHP testing framework with clean syntax
- **Three Test Contexts**: Separate base classes for different Maho environments
  - `MahoFrontendTestCase` - For frontend/customer-facing functionality
  - `MahoBackendTestCase` - For admin/backend functionality (with `isSecureArea` enabled)
  - `MahoInstallTestCase` - For installation context tests
- **Proper Maho Bootstrap**: Each test context initializes Maho with correct paths and settings
- **Test Isolation**: Clean setup/teardown with `Mage::reset()` between tests

### Test Structure
```
tests/
├── Frontend/           # Frontend context tests
├── Backend/            # Backend context tests  
├── Install/            # Install context tests
├── MahoFrontendTestCase.php
├── MahoBackendTestCase.php
├── MahoInstallTestCase.php
└── Pest.php           # Configuration
```

### Writing Tests
Tests must explicitly declare their context:
```php
// Frontend test
uses(Tests\MahoFrontendTestCase::class);

it('can process customer orders', function () {
    // Test has full Maho frontend context available
});

// Backend test  
uses(Tests\MahoBackendTestCase::class);

it('can manage admin users', function () {
    // Test has full Maho backend context with isSecureArea enabled
});
```

### Additional Quality Assurance
- PHPStan static analysis (level 6)
- PHP-CS-Fixer for code standards
- GitHub Actions CI for automated checks

## Modern PHP Patterns

### Type Declarations
- Use `declare(strict_types=1);` at the top of new PHP files
- Add type hints for all new method parameters and return types
- Use PHP 8.3+ features like `#[\Override]` attribute for overridden methods

### Security Patterns
- Use `getUserParam()` instead of `getParam()` for user-supplied parameters in controllers
- Define `public const ADMIN_RESOURCE` in admin controllers for ACL permissions
- Example:
```php
declare(strict_types=1);

class Mage_Module_Adminhtml_SomeController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/module/resource';
    
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }
}
```


## Git Commit Rules
- **NEVER** include "Co-Authored-By: Claude" or any AI attribution in commits
- **NEVER** mention Claude, AI, or assistant in commit messages
- Keep commits professional and focused only on code changes
