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
Maho uses **Doctrine DBAL 4.4** for all database operations. This is a complete replacement of Zend_Db - no Zend Framework database components remain in the codebase.

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

### Other Key Systems
- **Events**: `Mage::dispatchEvent('event_name', ['data' => $data])` - Observers in `config.xml`
- **Layout**: XML-based configuration with block hierarchy and template assignment
- **Sessions**: `Mage::getSingleton('customer/session')`, `'admin/session'`, `'checkout/session'`
- **Translations**: CSV files in `app/locale/[locale]/` - Use `$this->__('Text')` in code

## Development Guidelines

### Critical Rules
- **NEVER use Zend Framework components** - They have been completely removed from Maho. Use the modern alternatives documented above.
- **NEVER use Varien_Date or Zend_Date** - Use native PHP DateTime and `Mage_Core_Model_Locale` methods.
- **NEVER use Zend_Db or Zend_Db_Select directly** - Use `Maho\Db\Select` and `Maho\Db\Adapter\AdapterInterface`.
- **NEVER use Varien_ prefixed classes in new code** - All Varien classes have been moved to the Maho namespace. Use the new `Maho\*` classes instead (see Varien Migration section below).
- **NEVER use TinyMCE** - It has been completely removed from Maho. Use TipTap 3.x (see WYSIWYG Editor section below).

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
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
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

## Modernizations

Maho has replaced legacy Zend Framework and Varien components with modern alternatives:

### Logging (Monolog)

Zend_Log has been completely removed:
```php
// OLD - Don't use
Zend_Log::ERR, Zend_Log::WARN, Zend_Log::DEBUG

// NEW - Use Mage constants
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

### HTTP Client (Symfony HttpClient)

Varien_Http_Client and Zend_Http have been completely removed:
```php
// OLD - Don't use
new Varien_Http_Client();
new Varien_Http_Adapter_Curl();

// NEW - Use this
$client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
$response = $client->request('GET', $url);
$data = $response->getContent();
```

### JSON Handling

Zend_Json has been completely removed:
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

### Validation (Symfony Validator)

Zend_Validate has been completely removed:
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

### Date Handling (Native PHP DateTime)

Zend_Date and Varien_Date have been completely removed.

**Key Concepts:**
- **Database storage**: Always UTC in `'Y-m-d H:i:s'` format
- **Display/Input**: Use `storeDate()` to convert UTC → HTML5 format
- **Processing**: Use `utcDate()` to convert HTML5 → UTC for database

```php
// Display: Database (UTC) → HTML5 input
$html = Mage::app()->getLocale()->storeDate(null, $dbDate, false, 'html5');
// "2025-01-15" for type="date"

$html = Mage::app()->getLocale()->storeDate(null, $dbDateTime, true, 'html5');
// "2025-01-15T14:30" for type="datetime-local"

// Process: HTML5 input → Database (UTC)
$utc = Mage::app()->getLocale()->utcDate(null, $inputDate, false, 'html5');
$utc = Mage::app()->getLocale()->utcDate(null, $inputDateTime, true, 'html5');

// Current date/time
Mage_Core_Model_Locale::now();    // 'Y-m-d H:i:s'
Mage_Core_Model_Locale::today();  // 'Y-m-d'
```

### Filtering & Locale

Zend_Filter has been completely removed:
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

### Varien Classes (Maho Namespace)

All Varien classes have been migrated to the Maho namespace. Class aliases exist for backward compatibility, but **always use the new Maho classes in new code**.

**Naming Pattern:**
- `Varien_Class_Name` → `Maho\Class\Name`
- `Varien_Object` → `Maho\DataObject` (special case)
- `Varien_Filter_Array` → `Maho\Filter\ArrayFilter`
- `Varien_Filter_Object` → `Maho\Filter\ObjectFilter`

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

### WYSIWYG Editor (TipTap 3.x)

**Configuration Location:**
- Extensions: `public/js/mage/adminhtml/wysiwyg/tiptap/extensions.js`
- Setup: `public/js/mage/adminhtml/wysiwyg/tiptap/setup.js`
- Styles: `public/js/mage/adminhtml/wysiwyg/tiptap/tiptap.css`

**Features:**
- All nodes support `class` and `style` attributes
- Custom Maho extensions for widgets, images, and slideshows
- Directive support for `{{widget}}` and `{{config}}` syntax
- HTML5 content conversion to/from plain text
- Table editing with bubble menu
- Fullscreen mode

### Other Components

- **Exceptions**: Use `Mage_Core_Exception` (Zend_Exception removed)
- **PDF Generation**: Use DomPdf with HTML/CSS templates. Extend `Mage_Core_Block_Pdf` (Zend_Pdf removed)
- **Cache**: Use native Maho cache system (Zend_Cache removed)

## Testing

Maho uses **Pest PHP** with three test contexts:

### Test Contexts
- `MahoFrontendTestCase` - Frontend/customer-facing tests
- `MahoBackendTestCase` - Admin/backend tests (with `isSecureArea` enabled)
- `MahoInstallTestCase` - Installation context tests

```php
uses(Tests\MahoFrontendTestCase::class);

it('can process customer orders', function () {
    // Test code
});
```

### Quality Tools
- **Pest PHP**: Test framework
- **PHPStan**: Static analysis (level 6)
- **PHP-CS-Fixer**: Code standards
- **GitHub Actions**: CI automation

## Modern PHP Patterns

### Type Declarations
- Use `declare(strict_types=1);` at the top of new PHP files
- Add type hints for all new method parameters and return types
- Use PHP 8.3+ features like `#[\Override]` attribute for overridden methods

### Security Patterns

**Parameter Handling:**
- **ALWAYS use `getParam()`** for retrieving request parameters in controllers (both frontend and admin)
- **NEVER use `getUserParam()`** in controllers - it only checks route parameters and will break query string URLs
- `getUserParam()` is designed for URL building (in `Mage_Core_Model_Url`), NOT for parameter retrieval
- Security comes from validation, ACL, and CSRF tokens, not from which getter method you use

```php
// ✅ CORRECT - Works with both /edit/id/123 and /edit?id=123
$id = $this->getRequest()->getParam('id');

// ❌ WRONG - Only works with /edit/id/123, breaks /edit?id=123
$id = $this->getRequest()->getUserParam('id');
```

**Security Best Practices:**
- Define `public const ADMIN_RESOURCE` in admin controllers for ACL permissions
- Use `_setForcedFormKeyActions()` for state-changing actions (delete, save, etc.)
- Validate and sanitize all user input at the model/business logic layer
- Use Doctrine DBAL's parameterized queries (automatic in Maho)

Example:
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

    public function editAction(): void
    {
        // ✅ Use getParam() - standard Maho pattern
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('module/entity')->load($id);

        // Validate at model layer
        if (!$model->getId()) {
            $this->_getSession()->addError($this->__('Entity not found.'));
            $this->_redirect('*/*/');
            return;
        }

        // ... rest of action
    }
}
```


## Git Commit Rules
- **NEVER** include "Co-Authored-By: Claude" or any AI attribution in commits
- **NEVER** mention Claude, AI, or assistant in commit messages
- Keep commits professional and focused only on code changes
