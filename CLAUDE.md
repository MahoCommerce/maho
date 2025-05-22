# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Maho is a modern, open-source ecommerce platform created in 2024, forked from the M1 platform (Magento 1 lineage). It's designed as a PHP 8.2+ platform focusing on stability, performance, and developer experience while maintaining backward compatibility with the vast M1 ecosystem.

## Essential Development Commands

### Development Server
```bash
./maho serve [port]              # Start development server (default port 8000)
```

### Cache Management
```bash
./maho cache:flush               # Clear all caches
./maho cache:enable              # Enable all cache types  
./maho cache:disable             # Disable all cache types
```

### Code Quality
```bash
vendor/bin/php-cs-fixer fix     # Format code (PER-CS2.0 + custom rules)
vendor/bin/phpstan analyze      # Static analysis (uses mahocommerce/maho-phpstan-plugin)
```

### Database & Installation
```bash
./maho install                  # Full installation wizard
./maho db:connect               # Open MySQL CLI with project credentials
./maho health-check             # Comprehensive migration health check
```

### Index Management
```bash
./maho index:list               # Show all indexes
./maho index:reindex:all        # Reindex everything
./maho index:reindex [index]    # Reindex specific index
```

### Development Utilities
```bash
./maho phpstorm:metadata:generate    # Generate IDE metadata
./maho translations:missing          # Find missing translations
./maho legacy:rename-mysql4-classes  # Migrate legacy Mysql4 classes
```

## Core Architecture

### Module System
- **Structure**: `app/code/{core|community|local}/Namespace/Module/`
- **Declaration**: `app/etc/modules/Namespace_Module.xml`
- **Components**: Model/, Block/, Helper/, controllers/, etc/config.xml
- **Factory Pattern**: `Mage::getModel('module/model')`, `Mage::helper('module/helper')`

### Configuration System
- **XML-based**: Hierarchical config merging from global → module → local
- **Access**: `Mage::getStoreConfig('section/group/field')`
- **Store Scope**: Website → Store Group → Store View hierarchy

### Model-Resource Pattern
- **Models**: Business logic extending `Mage_Core_Model_Abstract`
- **Resources**: Database abstraction in `Mage_*_Model_Resource_*`
- **Collections**: `Mage_*_Model_Resource_*_Collection` for queries
- **Loading**: `$model->load($id)` or `$collection->addFieldToFilter()`

### Event-Observer System
- **Dispatch**: `Mage::dispatchEvent('event_name', $data)`
- **Registration**: In module's `config.xml` under `<events>` node
- **Observers**: Methods in classes extending `Mage_Core_Model_Observer`

### CLI Extension
- **Base Class**: Extend `BaseMahoCommand` (auto-initializes Mage)
- **Auto-discovery**: Commands auto-loaded from vendor packages
- **Integration**: Full access to Mage functionality in CLI context

## Development Patterns

### Creating New Modules
1. Create module directory: `app/code/local/Vendor/Module/`
2. Add declaration: `app/etc/modules/Vendor_Module.xml`
3. Create `etc/config.xml` with module configuration
4. Implement Models, Blocks, Helpers as needed
5. Use events/observers for loose coupling

### Database Interactions
```php
// Model loading
$product = Mage::getModel('catalog/product')->load($id);

// Collections
$collection = Mage::getModel('catalog/product')->getCollection()
    ->addFieldToFilter('status', 1)
    ->addAttributeToSelect('*');

// Direct queries (avoid when possible)
$resource = Mage::getSingleton('core/resource');
$connection = $resource->getConnection('read');
```

### Configuration Access
```php
// Store config
$value = Mage::getStoreConfig('section/group/field', $storeId);

// System config
$config = Mage::getConfig()->getNode('global/models/core');
```

### Event Handling
```php
// Dispatch event
Mage::dispatchEvent('catalog_product_save_after', ['product' => $product]);

// Observer method
public function catalogProductSaveAfter($observer) {
    $product = $observer->getEvent()->getProduct();
    // Custom logic here
}
```

## File Locations

- **Core Code**: `app/code/core/Mage/`
- **Local Customizations**: `app/code/local/`
- **Configuration**: `app/etc/config.xml`, `local.xml`
- **Templates**: `app/design/frontend/` and `app/design/adminhtml/`
- **Public Assets**: `public/` (document root)
- **CLI Commands**: `lib/MahoCLI/Commands/`

## Key Differences from Standard M1

- **PHP 8.2+ Only**: Modern PHP features and type declarations
- **Composer Integration**: Full composer support with autoloading
- **CLI Tools**: Comprehensive CLI via Symfony Console
- **Code Quality**: PHPStan level 6, PHP-CS-Fixer PER-CS2.0
- **Modern Dependencies**: Updated libraries (Symfony components, etc.)
- **Security Enhancements**: Modern encryption, security patches applied

## Testing & Quality Assurance

When modifying code:
1. Run `./maho health-check` after major changes
2. Use `vendor/bin/php-cs-fixer fix` for code formatting
3. Run `vendor/bin/phpstan analyze` for static analysis
4. Test cache operations with cache commands
5. Verify indexes with `./maho index:reindex:all`