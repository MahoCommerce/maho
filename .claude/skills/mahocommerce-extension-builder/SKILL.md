# MahoCommerce Extension Builder Skill

**Version:** 2.0.0
**Purpose:** Build modern Maho modules following best practices from Gift Card module
**Updated:** December 2025 with patterns from Fabrizio's Gift Card implementation

## Description

This skill helps create well-structured Maho extensions using patterns learned from the Gift Card module. It enforces modern coding standards, simplified database design, optional dependencies, and clean module organization.

## When to Invoke

**Activate when:**
- User requests "create a module" or "build an extension"
- Starting new functionality in `app/code/core/Maho/`
- Converting Magento 1/OpenMage modules
- Building payment methods, shipping methods, or product types
- Creating admin interfaces

## Module Structure Template

### 1. Basic Structure

```
app/code/core/Maho/[ModuleName]/
├── Block/
│   ├── Adminhtml/       # Admin interface blocks
│   └── Frontend/        # Frontend blocks
├── controllers/
│   ├── Adminhtml/       # Admin controllers
│   └── Frontend.php     # Frontend controllers
├── etc/
│   ├── config.xml       # Module configuration
│   ├── system.xml       # System configuration
│   └── adminhtml.xml    # Admin menu/ACL
├── Helper/
│   └── Data.php         # Helper methods
├── Model/
│   ├── [Entity].php     # Main models
│   └── Resource/        # Resource models
│       └── [Entity]/
│           └── Collection.php
├── sql/
│   └── [module]_setup/
│       └── install-1.0.0.php
└── view/                # For Vue/React apps
    └── adminhtml/
        └── web/
```

### 2. Module Declaration

**app/etc/modules/Maho_[ModuleName].xml:**
```xml
<?xml version="1.0"?>
<config>
    <modules>
        <Maho_[ModuleName]>
            <active>true</active>
            <codePool>core</codePool>
            <depends>
                <Mage_Core />
            </depends>
        </Maho_[ModuleName]>
    </modules>
</config>
```

### 3. Configuration (config.xml)

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <Maho_[ModuleName]>
            <version>1.0.0</version>
        </Maho_[ModuleName]>
    </modules>

    <global>
        <models>
            <!-- Use simple names without module prefix -->
            <[module]>
                <class>Maho_[ModuleName]_Model</class>
                <resourceModel>[module]_resource</resourceModel>
            </[module]>
            <[module]_resource>
                <class>Maho_[ModuleName]_Model_Resource</class>
                <entities>
                    <!-- Simple entity names -->
                    <[entity]>
                        <table>[entity]</table>
                    </[entity]>
                </entities>
            </[module]_resource>
        </models>

        <blocks>
            <[module]>
                <class>Maho_[ModuleName]_Block</class>
            </[module]>
        </blocks>

        <helpers>
            <[module]>
                <class>Maho_[ModuleName]_Helper</class>
            </[module]>
        </helpers>

        <resources>
            <[module]_setup>
                <setup>
                    <module>Maho_[ModuleName]</module>
                </setup>
            </[module]_setup>
        </resources>
    </global>

    <!-- Admin routing with before="Mage_Adminhtml" -->
    <admin>
        <routers>
            <adminhtml>
                <args>
                    <modules>
                        <Maho_[ModuleName] before="Mage_Adminhtml">
                            Maho_[ModuleName]_Adminhtml
                        </Maho_[ModuleName]>
                    </modules>
                </args>
            </adminhtml>
        </routers>
    </admin>

    <!-- Default configuration values -->
    <default>
        <[module]>
            <general>
                <enabled>1</enabled>
                <!-- Config-based defaults -->
                <lifetime>365</lifetime>
                <allow_message>1</allow_message>
            </general>
        </[module]>
    </default>
</config>
```

## Best Practices from Gift Card Module

### 1. Simple Entity Naming

```php
// ❌ AVOID redundant prefixes
Mage::getModel('maho_giftcard/maho_giftcard');

// ✅ USE simple names
Mage::getModel('giftcard/giftcard');
Mage::getResourceModel('giftcard/history_collection');
```

### 2. Database Design

```php
// install-1.0.0.php
$installer = $this;
$installer->startSetup();

// Merge 1:1 relationships into main table
$table = $installer->getConnection()
    ->newTable($installer->getTable('[module]/[entity]'))
    ->addColumn('[entity]_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'nullable' => false,
        'primary'  => true,
        'unsigned' => true,
    ])
    // Include related data directly (no separate 1:1 tables)
    ->addColumn('email_scheduled_at', Maho\Db\Ddl\Table::TYPE_DATETIME)
    ->addColumn('email_sent_at', Maho\Db\Ddl\Table::TYPE_DATETIME)
    // Use TYPE_TIMESTAMP (not TYPE_DATETIME) for created_at/updated_at
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ])
    ->addColumn('updated_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE,
    ]);

$installer->getConnection()->createTable($table);
$installer->endSetup();
```

### 3. Model Timestamp Pattern (_beforeSave)

**ALWAYS use `utcDate()` for setting created_at/updated_at in models:**

```php
#[\Override]
protected function _beforeSave(): self
{
    $now = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
    if (!$this->getCreatedAt()) {
        $this->setCreatedAt($now);
    }
    $this->setUpdatedAt($now);

    return parent::_beforeSave();
}
```

**⚠️ Do NOT use `Mage_Core_Model_Locale::now()` for this purpose** -- it doesn't guarantee proper UTC handling.

### 4. Optional Dependencies

**composer.json:**
```json
{
    "name": "maho/module-[name]",
    "suggest": {
        "picqer/php-barcode-generator": "Required for barcode generation",
        "bacon/bacon-qr-code": "Required for QR code generation"
    }
}
```

**Helper with optional package check:**
```php
class Maho_[ModuleName]_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function generateBarcode($data)
    {
        // Check if optional package is installed
        if (!class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
            return ''; // Gracefully handle missing package
        }

        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        return $generator->getBarcode($data, $generator::TYPE_CODE_128);
    }
}
```

### 4. Config-Based Defaults

**Product attributes with inheritance:**
```php
// Helper method to resolve value
public function getProductValue($product, $attribute, $configPath)
{
    return $product->getData($attribute)
        ?? Mage::getStoreConfig($configPath);
}
```

**Custom form element:**
```php
// Block/Adminhtml/Form/Element/TextWithDefaultFromConfig.php
class TextWithDefaultFromConfig extends Varien_Data_Form_Element_Text
{
    public function getElementHtml()
    {
        $configValue = Mage::getStoreConfig($this->getConfigPath());
        $this->setPlaceholder("Default: $configValue");
        return parent::getElementHtml();
    }
}
```

### 5. Admin Controllers

```php
declare(strict_types=1);

class Maho_[ModuleName]_Adminhtml_[Controller]Controller
    extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = '[module]/manage';

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed(self::ADMIN_RESOURCE);
    }

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'mass']);
        return parent::preDispatch();
    }

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('sales/[module]'); // Logical placement
        $this->renderLayout();
    }
}
```

### 6. Email Integration

```php
// Use core email infrastructure
public function sendEmail($recipient, $vars)
{
    $email = Mage::getModel('core/email_template');
    $email->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);

    $email->sendTransactional(
        Mage::getStoreConfig('[module]/email/template'),
        Mage::getStoreConfig('[module]/email/sender'),
        $recipient['email'],
        $recipient['name'],
        $vars
    );
}
```

### 7. System Configuration

**system.xml with optional dependency warning:**
```xml
<field id="barcode_enabled" translate="label" type="select">
    <label>Enable Barcode</label>
    <source_model>adminhtml/system_config_source_yesno</source_model>
    <frontend_model>[module]/adminhtml_system_config_form_field_barcode</frontend_model>
    <!-- Shows warning if package not installed -->
</field>
```

## Modern PHP Patterns

```php
declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_[ModuleName]
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_[ModuleName]_Model_[Entity] extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('[module]/[entity]');
    }

    // Use typed properties and returns
    public function processData(array $data): bool
    {
        // Use null coalescing
        $value = $data['key'] ?? 'default';

        // Use str_contains instead of strpos
        if (str_contains($value, 'search')) {
            return true;
        }

        return false;
    }
}
```

## Translation Files

**app/locale/en_US/Maho_[ModuleName].csv:**
```csv
"Add New","Add New"
"Delete","Delete"
"Edit","Edit"
"Save","Save"
```

**Keep translations:**
- Alphabetically sorted
- Only include actually used strings
- Run PHP-CS-Fixer before committing

## Testing Checklist

Before completing module:

- [ ] Run `vendor/bin/php-cs-fixer fix`
- [ ] Run `vendor/bin/rector -c .rector.php`
- [ ] Run `vendor/bin/phpstan analyze --level=6`
- [ ] Check all translations present in CSV
- [ ] Verify optional dependencies handled gracefully
- [ ] Test with/without optional packages
- [ ] Confirm admin ACL working
- [ ] Validate email sending (if applicable)
- [ ] Test config inheritance patterns

## Common Pitfalls to Avoid

1. ❌ Don't create separate tables for 1:1 relationships
2. ❌ Don't hardcode dependencies - make them optional
3. ❌ Don't use module prefix in entity names
4. ❌ Don't forget #[\Override] attributes
5. ❌ Don't use deprecated APIs (Zend_, Varien_)
6. ❌ Don't use getUserParam() in controllers -- use getParam()
7. ❌ Don't create custom email tables - use core
8. ❌ Don't use discriminatory language
9. ❌ Don't add unnecessary translation wrappers
10. ❌ Don't forget to run linters before commit

## Module Types Reference

### Payment Method
- Extend `Mage_Payment_Model_Method_Abstract`
- Implement `authorize()`, `capture()`, `refund()`
- Add payment form block

### Shipping Method
- Extend `Mage_Shipping_Model_Carrier_Abstract`
- Implement `collectRates()`
- Return `Mage_Shipping_Model_Rate_Result`

### Product Type
- Extend `Mage_Catalog_Model_Product_Type_Abstract`
- Override `prepareForCart()`, `getPrice()`
- Add custom options handling

### Admin Grid
- Use `Mage_Adminhtml_Block_Widget_Grid`
- Implement `_prepareCollection()`, `_prepareColumns()`
- Add mass actions and exports

### Admin Block #[\Override] Requirements

**Every overridden method in admin blocks MUST have `#[\Override]`**. PHPStan enforces this. Common methods by block type:

**Grid blocks** (`Mage_Adminhtml_Block_Widget_Grid`):
```php
#[\Override]
protected function _prepareCollection(): static { ... }
#[\Override]
protected function _prepareColumns(): static { ... }
#[\Override]
public function getRowUrl($row): string { ... }
#[\Override]
public function getGridUrl(): string { ... }
```

**Form container** (`Mage_Adminhtml_Block_Widget_Form_Container`):
```php
#[\Override]
public function getHeaderText(): string { ... }
```

**Form blocks** (`Mage_Adminhtml_Block_Widget_Form`):
```php
#[\Override]
protected function _prepareForm(): static { ... }
```

**Tab interface** (`Mage_Adminhtml_Block_Widget_Tab_Interface`):
```php
#[\Override]
public function getTabLabel(): string { ... }
#[\Override]
public function getTabTitle(): string { ... }
#[\Override]
public function canShowTab(): bool { ... }
#[\Override]
public function isHidden(): bool { ... }
```

**Tabs container** (`Mage_Adminhtml_Block_Widget_Tabs`):
```php
#[\Override]
protected function _beforeToHtml(): static { ... }
```

## Auto-Activation

This skill auto-activates when detecting:
- "create module" or "build extension" requests
- New directory in `app/code/core/Maho/`
- Module-related file creation
- Extension development questions