---
description: Import data directly from PHP arrays into Maho's ImportExport pipeline with the Array Import Adapter, no temporary CSV or XML files for products or customers.
---

# Array Import Adapter <span class="version-badge">v25.11+</span>

The Array Import Adapter extends Maho's native ImportExport functionality by enabling direct imports from PHP arrays, eliminating the need for intermediate files. This feature provides developers with programmatic import capabilities while maintaining full compatibility with Maho's existing ImportExport pipeline.

## Key Features

- **Direct Array Import**: Import data directly from PHP arrays without creating temporary files
- **Flexible Data Sources**: Convert data from APIs, databases, or any other source into arrays for import
- **Seamless Integration**: Works with all existing entity importers (products, customers, categories)
- **Performance Optimized**: Reduces I/O operations by eliminating file-based intermediate steps
- **No Temporary Files**: Skips writing and re-reading a CSV/XML file; the adapter iterates the array you provide directly (the array itself still lives in memory, so batch large datasets)
- **Full Compatibility**: Maintains backward compatibility with existing file-based imports
- **Multistore Support**: Full support for store-scoped data imports

## Architecture

### Core Components

1. **Array Adapter** (`Mage_ImportExport_Model_Import_Adapter_Array`)
   - Extends the abstract adapter base class
   - Implements `SeekableIterator` for data traversal
   - Handles both indexed and associative array formats

2. **Enhanced Factory** (`Mage_ImportExport_Model_Import_Adapter`)
   - Added `createArrayAdapter()` method for convenient array adapter creation
   - Extended `factory()` method to support 'array' type

3. **Import Model Extensions** (`Mage_ImportExport_Model_Import`)
   - Enhanced `_getSourceAdapter()` to handle array sources
   - Updated `validateSource()` to accept arrays
   - Added public `getEntityAdapter()` method

## Usage

### Basic Product Import

```php
<?php
require 'vendor/autoload.php';
Mage::app();

// Prepare your data as an array
$productData = [
    [
        'sku' => 'PRODUCT-001',
        '_attribute_set' => 'Default',
        '_type' => 'simple',
        'name' => 'Sample Product 1',
        'description' => 'Product description here',
        'short_description' => 'Short description',
        'price' => '29.99',
        'status' => '1',
        'visibility' => '4',
        'tax_class_id' => '2',
        'weight' => '1.5000',
        'qty' => '100',
        'is_in_stock' => '1',
    ],
    [
        'sku' => 'PRODUCT-002',
        '_attribute_set' => 'Default',
        '_type' => 'simple',
        'name' => 'Sample Product 2',
        'description' => 'Another product description',
        'short_description' => 'Another short description',
        'price' => '49.99',
        'status' => '1',
        'visibility' => '4',
        'tax_class_id' => '2',
        'weight' => '2.0000',
        'qty' => '50',
        'is_in_stock' => '1',
    ]
];

// Create import model and configure
$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'catalog_product',
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
]);

// Validate and import
if ($import->validateSource($productData)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($productData);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $result = $entityAdapter->importData();
        if ($result) {
            echo "Import successful!\n";
            echo "Processed: " . $entityAdapter->getProcessedEntitiesCount() . " products\n";
        } else {
            echo "Import failed during processing.\n";
        }
    } else {
        echo "Data validation failed.\n";
    }
} else {
    echo "Source validation failed.\n";
}
```

### Multistore Product Import

For multistore environments, you can import global data first, then store-specific overrides:

```php
// Step 1: Import global/default data
$globalData = [
    [
        'sku' => 'MULTISTORE-001',
        '_store' => '', // Empty = global scope
        '_attribute_set' => 'Default',
        '_type' => 'simple',
        'name' => 'Global Product Name',
        'description' => 'Default product description',
        'price' => '49.99',
        'status' => '1',
        'visibility' => '4',
        'tax_class_id' => '2',
        'weight' => '1.0',
        'qty' => '100',
        'is_in_stock' => '1'
    ]
];

// Import global data
$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'catalog_product',
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
]);

if ($import->validateSource($globalData)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($globalData);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $entityAdapter->importData();
    }
}

// Step 2: Import store-specific overrides
$storeData = [
    [
        'sku' => 'MULTISTORE-001',
        '_store' => 'german', // Store code
        'name' => 'Deutscher Produktname',
        'description' => 'Deutsche Produktbeschreibung'
    ],
    [
        'sku' => 'MULTISTORE-001',
        '_store' => 'french',
        'name' => 'Nom du produit français',
        'description' => 'Description du produit français'
    ]
];

// Import store-specific data
if ($import->validateSource($storeData)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($storeData);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $entityAdapter->importData();
    }
}
```

### Customer Import Example

```php
$customerData = [
    [
        'email' => 'john.doe@example.com',
        'firstname' => 'John',
        'lastname' => 'Doe',
        'password' => 'SecurePass123',
        'group_id' => '1',
        'website_id' => '1',
        'store_id' => '1',
        'is_active' => '1'
    ],
    [
        'email' => 'jane.smith@example.com',
        'firstname' => 'Jane',
        'lastname' => 'Smith',
        'password' => 'SecurePass456',
        'group_id' => '1',
        'website_id' => '1',
        'store_id' => '1',
        'is_active' => '1'
    ]
];

$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'customer',
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
]);

if ($import->validateSource($customerData)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($customerData);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $result = $entityAdapter->importData();
    }
}
```

## Supported Data Formats

The array adapter supports two data formats:

### Associative Arrays
Keys serve as column names:

```php
$data = [
    ['sku' => 'PROD-001', 'name' => 'Product 1', 'price' => '29.99'],
    ['sku' => 'PROD-002', 'name' => 'Product 2', 'price' => '39.99'],
];
```

### Indexed Arrays
First row contains column headers:

```php
$data = [
    ['sku', 'name', 'price'],           // Header row
    ['PROD-001', 'Product 1', '29.99'], // Data row
    ['PROD-002', 'Product 2', '39.99'], // Data row
];
```

Both formats produce identical results and integrate seamlessly with the import system.

## Advanced Features

### Batch Processing

For large datasets, process data in batches to manage memory usage:

```php
$batchSize = 500;
$offset = 0;

$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'catalog_product', 
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
]);

do {
    $batch = array_slice($largeDataset, $offset, $batchSize);
    if (!empty($batch)) {
        if ($import->validateSource($batch)) {
            $entityAdapter = $import->getEntityAdapter();
            $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($batch);
            $entityAdapter->setSource($adapter);
            
            if ($entityAdapter->isDataValid()) {
                $result = $entityAdapter->importData();
                if (!$result) {
                    echo "Batch import failed at offset {$offset}\n";
                    break;
                }
                echo "Processed batch: " . $entityAdapter->getProcessedEntitiesCount() . " entities\n";
            }
        }
    }
    $offset += $batchSize;
} while (count($batch) === $batchSize);
```

### Error Handling

```php
$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'catalog_product',
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
]);

if ($import->validateSource($data)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $result = $entityAdapter->importData();
        
        if (!$result) {
            echo "Import failed with errors:\n";
            foreach ($entityAdapter->getErrorMessages() as $errorCode => $rows) {
                echo "Error: {$errorCode}\n";
                echo "Affected rows: " . implode(', ', $rows) . "\n";
            }
        }
        
        // Statistics
        echo "\nImport Statistics:\n";
        echo "- Processed entities: " . $entityAdapter->getProcessedEntitiesCount() . "\n";
        echo "- Errors: " . $entityAdapter->getErrorsCount() . "\n";
    } else {
        echo "Data validation failed:\n";
        foreach ($entityAdapter->getErrorMessages() as $errorCode => $rows) {
            echo "- {$errorCode}: rows " . implode(', ', $rows) . "\n";
        }
    }
}
```

## Supported Entities

The array adapter works with all standard ImportExport entities:

- **Products** (`catalog_product`): Simple, configurable, grouped, virtual products
- **Categories** (`catalog_category`): Category structure and attributes  
- **Customers** (`customer`): Customer accounts and attributes

## Import Behaviors

All standard import behaviors are supported:

- **`BEHAVIOR_APPEND`**: Add new entities (default)
- **`BEHAVIOR_REPLACE`**: Update existing entities, add new ones
- **`BEHAVIOR_DELETE`**: Remove entities based on provided data

## Performance Considerations

### Memory Usage

- The array you pass to the adapter is held in memory in full (it is already a materialized PHP array), and the adapter iterates over it row by row. This avoids file I/O, but it does not reduce the memory footprint of the source data itself.
- For large datasets, build and import the data in batches (see below) rather than assembling one large array, so the whole dataset is never resident at once. As a rough guideline, consider batching once you are into the tens of thousands of rows.
- Monitor memory usage with `memory_get_usage()` during development

### Optimization Tips

1. **Batch Processing**: Process large datasets in smaller chunks (500-1000 rows)
2. **Minimal Data**: Only include necessary columns in your arrays
3. **Pre-validation**: Validate data structure before import to avoid partial failures

## API Reference

### Mage_ImportExport_Model_Import_Adapter

#### `static createArrayAdapter($data): Mage_ImportExport_Model_Import_Adapter_Abstract`

Creates a new array adapter instance. The `$data` parameter is untyped in the signature and validated at runtime (it must be an array). The declared return type is the abstract base class `Mage_ImportExport_Model_Import_Adapter_Abstract`; the concrete instance returned is always a `Mage_ImportExport_Model_Import_Adapter_Array`.

**Parameters:**
- `$data`: Import data array in a supported format

**Returns:** Array adapter instance (`Mage_ImportExport_Model_Import_Adapter_Array`)

**Throws:** `Mage_Core_Exception` ("Source data must be an array") if `$data` is not an array

### Mage_ImportExport_Model_Import

#### `validateSource(string|array $source): bool`

Validates import source (file path or array data).

**Parameters:**
- `$source` (string|array): File path or array data

**Returns:** `true` if source is valid, `false` otherwise

#### `getEntityAdapter(): Mage_ImportExport_Model_Import_Entity_Abstract`

Gets the entity adapter for the configured import type.

**Returns:** Entity adapter instance

### Mage_ImportExport_Model_Import_Adapter_Array

#### `getRowCount(): int`

Gets the total number of data rows (excluding header).

**Returns:** Number of data rows

#### `validateSource(): self`

Validates the array data structure and logs warnings for inconsistencies.

**Returns:** Self for method chaining

## Troubleshooting

### Common Issues

1. **"Source data array cannot be empty"**
   - Ensure your data array contains at least one row
   - Check that the array is not accidentally emptied before import

2. **"Column names have duplicates"**
   - Ensure your array data doesn't have duplicate column names
   - Check for extra spaces or case variations in column names

3. **"No data rows found after header processing"**
   - Verify your array contains actual data rows beyond the header
   - Check that your associative arrays have consistent keys

4. **"Each data row must be an array"**
   - Ensure all data rows are arrays (associative or indexed)
   - Check for mixed data types in your dataset

### Debug Mode

Enable detailed validation messages:

```php
$import = Mage::getModel('importexport/import');
$import->setData([
    'entity' => 'catalog_product',
    'behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
]);

if (!$import->validateSource($data)) {
    echo "Source validation failed\n";
} else {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($data);
    $entityAdapter->setSource($adapter);
    
    if (!$entityAdapter->isDataValid()) {
        echo "Data validation failed:\n";
        foreach ($entityAdapter->getErrorMessages() as $error => $rows) {
            echo "- {$error}: rows " . implode(', ', $rows) . "\n";
        }
    }
}
```

## Use Cases

### API Data Integration

Perfect for importing data from external APIs:

```php
// Fetch data from external API
$apiResponse = file_get_contents('https://api.example.com/products');
$apiData = json_decode($apiResponse, true);

// Transform to Maho format
$mahoProducts = [];
foreach ($apiData['products'] as $product) {
    $mahoProducts[] = [
        'sku' => $product['id'],
        '_attribute_set' => 'Default',
        '_type' => 'simple',
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'status' => $product['active'] ? '1' : '0',
        'visibility' => '4',
        'tax_class_id' => '2',
        'weight' => $product['weight'] ?? '1.0',
        'qty' => $product['stock'] ?? '0',
        'is_in_stock' => $product['stock'] > 0 ? '1' : '0'
    ];
}

// Import using array adapter
$import = Mage::getModel('importexport/import');
$import->setData(['entity' => 'catalog_product', 'behavior' => 'append']);

if ($import->validateSource($mahoProducts)) {
    $entityAdapter = $import->getEntityAdapter();
    $adapter = Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($mahoProducts);
    $entityAdapter->setSource($adapter);
    
    if ($entityAdapter->isDataValid()) {
        $entityAdapter->importData();
    }
}
```

### Database Migration

Import data directly from database queries:

```php
// Connect to external database
$pdo = new PDO('mysql:host=external-db;dbname=legacy_store', $user, $pass);
$stmt = $pdo->query("SELECT * FROM legacy_products WHERE active = 1");

$products = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $products[] = [
        'sku' => $row['product_code'],
        '_attribute_set' => 'Default',
        '_type' => 'simple',
        'name' => $row['product_name'],
        'description' => $row['long_description'],
        'short_description' => $row['short_description'],
        'price' => $row['unit_price'],
        'status' => '1',
        'visibility' => '4',
        'tax_class_id' => '2',
        'weight' => $row['shipping_weight'],
        'qty' => $row['quantity_available'],
        'is_in_stock' => $row['quantity_available'] > 0 ? '1' : '0'
    ];
}

// Import using array adapter
$import = Mage::getModel('importexport/import');
$import->setData(['entity' => 'catalog_product', 'behavior' => 'append']);
// ... continue with import process
```

The Array Import Adapter provides a powerful and flexible way to integrate Maho with external systems and perform programmatic data imports without the overhead of file-based operations.