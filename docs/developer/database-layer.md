---
description: Maho's database layer runs on Doctrine DBAL 4 with full Zend_Db compatibility and multi-engine support for MySQL, MariaDB, PostgreSQL and SQLite.
---

# Database Layer in Maho <span class="version-badge">v25.11+</span>

Maho uses [Doctrine DBAL 4](https://www.doctrine-project.org/projects/dbal.html){target=_blank} for all database operations, providing a modern, actively maintained foundation while maintaining 100% backward compatibility with existing code.

## Overview

The database layer in Maho provides:

- **100% backward compatibility** with existing Zend_Db code
- **Modern foundation** built on Doctrine DBAL 4
- **Multi-database support** for MySQL/MariaDB, PostgreSQL (beta), and SQLite (beta)
- **Type-safe queries** with proper parameter binding
- **Advanced query methods** for complex operations
- **Nested transaction support** with automatic level tracking
- **DDL cache** for performance-critical schema introspection
- **Debug and profiling** tools for development

## Supported Database Engines <span class="version-badge">v26.1+</span>

Maho supports the following database engines:

| Engine | Status | Notes |
|--------|--------|-------|
| MySQL 8.4+ | Stable | Fully supported, recommended for production |
| MariaDB 10.11+ | Stable | Fully supported, recommended for production |
| PostgreSQL 15+ | Beta | Experimental support, use with caution in production |
| SQLite | Beta | Lightweight option for development/testing |

To use PostgreSQL, specify `--db_engine pgsql` during installation (see [Getting Started](../about/getting-started.md)).

To use SQLite, specify `--db_engine sqlite` during installation. SQLite stores data in a single file, making it ideal for development, testing, or small deployments. The adapter includes SQLite-specific optimizations like WAL mode and memory-mapped I/O.

The database layer automatically handles SQL dialect differences between engines. For example, string concatenation uses `CONCAT()` in MySQL but the `||` operator in PostgreSQL/SQLite - Maho's SQL helper methods abstract these differences so your code works on all engines.

## Why Doctrine DBAL?

The migration from Zend_Db to Doctrine DBAL isn't just about removing old code - it's about building on a foundation that's:

- **Actively maintained** - Regular updates and security patches
- **Well-documented** - Comprehensive official documentation
- **Future-proof** - Will continue to evolve with PHP ecosystem
- **Type-safe** - Better IDE support and static analysis
- **Standards-compliant** - Follows PSR standards

No more security vulnerabilities from abandoned 2000s-era libraries.

## Backward Compatibility

All existing code continues to work without any changes:

```php
// ✅ Old code still works - no changes needed
$adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
$select = $adapter->select()
    ->from('catalog_product')
    ->where('status = ?', 1);

// ✅ Varien_Db_Expr still works
$expr = new Varien_Db_Expr('COUNT(*)');

// ✅ New namespace also available
$expr = new Maho\Db\Expr('COUNT(*)');
```

The `Varien_Db_*` classes are maintained as autoloader aliases (in `app/bootstrap.php`) for complete backward compatibility, while the new implementation in `lib/Maho/Db/` wraps Doctrine DBAL.

## Query Building

### Basic SELECT Queries

```php
// Get database adapter
$adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

// Simple select
$select = $adapter->select()
    ->from('catalog_product', ['entity_id', 'sku', 'name'])
    ->where('status = ?', 1)
    ->where('visibility IN (?)', [2, 3, 4])
    ->order('created_at DESC')
    ->limit(10);

// Execute and fetch results
$products = $adapter->fetchAll($select);
```

### Table Aliases

```php
$select = $adapter->select()
    ->from(['p' => 'catalog_product'], ['entity_id', 'sku'])
    ->joinLeft(
        ['e' => 'cataloginventory_stock_item'],
        'p.entity_id = e.product_id',
        ['qty', 'is_in_stock']
    )
    ->where('p.status = ?', 1);
```

### Column Expressions

Use `Maho\Db\Expr` (or `Varien_Db_Expr`) to prevent quoting of SQL expressions:

```php
use Maho\Db\Expr;

$select = $adapter->select()
    ->from('sales_order', [
        'order_count' => new Expr('COUNT(*)'),
        'total_revenue' => new Expr('SUM(grand_total)'),
        'avg_order' => new Expr('AVG(grand_total)'),
        'order_date' => new Expr('DATE(created_at)')
    ])
    ->group('DATE(created_at)')
    ->having('COUNT(*) > ?', 5);
```

### JOIN Operations

```php
// INNER JOIN
$select = $adapter->select()
    ->from(['o' => 'sales_order'], ['increment_id', 'grand_total'])
    ->join(
        ['c' => 'customer_entity'],
        'o.customer_id = c.entity_id',
        ['email', 'firstname', 'lastname']
    )
    ->where('o.status = ?', 'complete');

// LEFT JOIN
$select = $adapter->select()
    ->from(['p' => 'catalog_product'], ['sku', 'name'])
    ->joinLeft(
        ['s' => 'cataloginventory_stock_item'],
        'p.entity_id = s.product_id',
        ['qty', 'is_in_stock']
    );

// Multiple joins
$select = $adapter->select()
    ->from(['o' => 'sales_order'], ['entity_id', 'increment_id'])
    ->join(
        ['a' => 'sales_order_address'],
        'o.entity_id = a.parent_id AND a.address_type = "billing"',
        ['city', 'region', 'postcode']
    )
    ->joinLeft(
        ['p' => 'sales_order_payment'],
        'o.entity_id = p.parent_id',
        ['method']
    );
```

### UNION Queries

```php
// Create first select
$select1 = $adapter->select()
    ->from('catalog_product', ['entity_id', 'sku'])
    ->where('status = ?', 1);

// Create second select
$select2 = $adapter->select()
    ->from('catalog_product_archive', ['entity_id', 'sku'])
    ->where('archived_date > ?', '2024-01-01');

// Union them
$select1->union([$select2]);
```

## Database Operations

### Fetching Data

```php
// Fetch all rows
$rows = $adapter->fetchAll($select);

// Fetch single row
$row = $adapter->fetchRow($select);

// Fetch single column from all rows
$skus = $adapter->fetchCol($select);

// Fetch single value
$count = $adapter->fetchOne($select);

// Fetch key-value pairs
$select = $adapter->select()
    ->from('catalog_product', ['entity_id', 'sku']);
$pairs = $adapter->fetchPairs($select);
// Returns: [1 => 'SKU001', 2 => 'SKU002', ...]
```

### Inserting Data

```php
// Insert single row
$adapter->insert('catalog_product', [
    'sku' => 'NEW-SKU-001',
    'name' => 'New Product',
    'status' => 1,
    // Always use formatDateForDb() for database timestamps (stores in UTC)
    'created_at' => Mage::app()->getLocale()->formatDateForDb('now')
]);

// Get last insert ID
$productId = $adapter->lastInsertId();

// Insert multiple rows (more efficient)
$data = [
    ['sku' => 'SKU-001', 'name' => 'Product 1', 'status' => 1],
    ['sku' => 'SKU-002', 'name' => 'Product 2', 'status' => 1],
    ['sku' => 'SKU-003', 'name' => 'Product 3', 'status' => 1],
];
$adapter->insertMultiple('catalog_product', $data);
```

### Updating Data

```php
// Update with WHERE clause
$adapter->update(
    'catalog_product',
    ['status' => 0, 'updated_at' => Mage::app()->getLocale()->formatDateForDb('now')],
    'sku = "OLD-SKU-001"'
);

// Update with bound parameters
$adapter->update(
    'catalog_product',
    ['status' => 0],
    $adapter->quoteInto('category_id = ?', 5)
);

// Multiple conditions
$adapter->update(
    'catalog_product',
    ['visibility' => 4],
    [
        'status = ?' => 1,
        'price > ?' => 100
    ]
);
```

### Deleting Data

```php
// Delete with WHERE clause
$adapter->delete('catalog_product', 'sku = "DELETE-ME"');

// Delete with bound parameters
$adapter->delete(
    'catalog_product',
    $adapter->quoteInto('status = ?', 0)
);

// Multiple conditions
$adapter->delete('catalog_product', [
    'status = ?' => 0,
    'updated_at < ?' => '2020-01-01'
]);
```

## Transactions

Maho's database adapter supports **nested transactions** with automatic level tracking:

```php
// Basic transaction
$adapter->beginTransaction();
try {
    $adapter->insert('sales_order', $orderData);
    $orderId = $adapter->lastInsertId();

    $adapter->insert('sales_order_item', [
        'order_id' => $orderId,
        'product_id' => 123,
        'qty_ordered' => 2
    ]);

    $adapter->commit();
} catch (Exception $e) {
    $adapter->rollBack();
    throw $e;
}

// Nested transactions are safe - level tracking prevents premature commits
$adapter->beginTransaction(); // Level 1
try {
    $adapter->insert('table1', $data1);

    $adapter->beginTransaction(); // Level 2
    try {
        $adapter->insert('table2', $data2);
        $adapter->commit(); // Decrements to level 1
    } catch (Exception $e) {
        $adapter->rollBack(); // Rolls back level 2
        throw $e;
    }

    $adapter->commit(); // Actually commits
} catch (Exception $e) {
    $adapter->rollBack(); // Rolls back everything
    throw $e;
}
```

## Custom Query Methods

Maho extends Doctrine DBAL with several custom query methods for complex operations:

### insertFromSelect

Efficiently insert data selected from another table:

```php
// Copy active products to archive table
$select = $adapter->select()
    ->from('catalog_product', ['sku', 'name', 'price'])
    ->where('status = ?', 1);

$adapter->insertFromSelect(
    $select,
    'catalog_product_archive',
    ['sku', 'name', 'price']
);
```

### updateFromSelect

Update table using data from a SELECT query:

```php
// Update product prices from temporary import table
$select = $adapter->select()
    ->from('import_prices', ['sku', 'new_price'])
    ->where('import_id = ?', 123);

$adapter->updateFromSelect(
    $select,
    'catalog_product'
);
// Updates catalog_product.price = import_prices.new_price WHERE sku matches
```

### deleteFromSelect

Delete rows based on a complex SELECT query:

```php
// Delete products that have no inventory
$select = $adapter->select()
    ->from(['p' => 'catalog_product'], 'p.entity_id')
    ->joinLeft(
        ['s' => 'cataloginventory_stock_item'],
        'p.entity_id = s.product_id',
        []
    )
    ->where('s.qty IS NULL OR s.qty <= 0');

$adapter->deleteFromSelect($select, 'catalog_product');
```

## SQL Helper Methods

Maho provides database-agnostic SQL helper methods:

### Conditional Expressions

```php
// IF/ELSE logic in SQL
$qtyExpr = $adapter->getCheckSql(
    'qty > 0',
    'qty',
    '0'
);

$select = $adapter->select()
    ->from('cataloginventory_stock_item', [
        'product_id',
        'available_qty' => $qtyExpr  // Already an Expr, no need to wrap
    ]);
```

### String Functions

```php
// Concatenate strings
$productDesc = $adapter->getConcatSql(['sku', '" - "', 'type_id']);

$select = $adapter->select()
    ->from('catalog_product_entity', [
        'entity_id',
        'product_desc' => $productDesc  // Already an Expr, no need to wrap
    ]);

// String length
$lengthExpr = $adapter->getLengthSql('sku');

$select = $adapter->select()
    ->from('catalog_product_entity', [
        'sku',
        'sku_length' => $lengthExpr  // Already an Expr, no need to wrap
    ]);
```

### Date Functions

```php
use Maho\Db\Adapter\AdapterInterface;

// Add/subtract dates
$futureDate = $adapter->getDateAddSql(
    'created_at',
    30,
    AdapterInterface::INTERVAL_DAY
);

$select = $adapter->select()
    ->from('sales_flat_order', [
        'increment_id',
        'estimated_delivery' => $futureDate  // Already an Expr, no need to wrap
    ]);

// Available intervals: INTERVAL_SECOND, INTERVAL_MINUTE, INTERVAL_HOUR,
//                      INTERVAL_DAY, INTERVAL_MONTH, INTERVAL_YEAR

// Format dates (returns Expr for use in queries)
$formattedDate = $adapter->formatDate('2024-12-25');  // Returns Expr object

$select = $adapter->select()
    ->from('sales_flat_order')
    ->where('created_at >= ?', $formattedDate);
```

### Null Handling

```php
// IFNULL / COALESCE
$priceExpr = $adapter->getIfNullSql('special_price', 'price');

$select = $adapter->select()
    ->from('catalog_product_entity', [
        'sku',
        'final_price' => $priceExpr  // Already an Expr, no need to wrap
    ]);
```

## Parameter Binding

All queries use **parameterized queries** to prevent SQL injection:

```php
// ✅ Safe - parameterized
$select = $adapter->select()
    ->from('catalog_product')
    ->where('sku = ?', $userInput)
    ->where('status IN (?)', $statusArray);

// ✅ Safe - using quoteInto
$where = $adapter->quoteInto('category_id = ?', $categoryId);
$adapter->delete('catalog_product', $where);

// ❌ Unsafe - never concatenate user input
$select = $adapter->select()
    ->from('catalog_product')
    ->where("sku = '$userInput'"); // DON'T DO THIS!
```

### Named Parameters

Named parameters are automatically converted to positional parameters:

```php
// This works - automatically converted
$select = $adapter->select()
    ->from('catalog_product')
    ->where('sku = :sku', ['sku' => 'PROD-001']);

// Internally converted to:
// WHERE sku = ?
// with bound value 'PROD-001'
```

## Schema Operations

### Table Information

```php
// Check if table exists
if ($adapter->isTableExists('my_custom_table')) {
    // Table exists
}

// Get table description
$columns = $adapter->describeTable('catalog_product');
foreach ($columns as $columnName => $columnInfo) {
    echo $columnName . ': ' . $columnInfo['DATA_TYPE'] . "\n";
}

// List all tables
$tables = $adapter->listTables();
```

### Creating Tables

```php
use Maho\Db\Ddl\Table;

$table = $adapter->newTable('my_custom_table')
    ->addColumn('entity_id', Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
    ], 'Entity ID')
    ->addColumn('name', Table::TYPE_TEXT, 255, [
        'nullable' => false,
    ], 'Name')
    ->addColumn('status', Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
        'default' => '1',
    ], 'Status')
    ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default' => Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        'IDX_STATUS',
        ['status']
    )
    ->setComment('My Custom Table');

$adapter->createTable($table);
```

## Working with Collections

Collections provide an ORM layer on top of the database adapter:

```php
// Get product collection
$collection = Mage::getModel('catalog/product')->getCollection();

// Add filters
$collection->addFieldToFilter('status', 1)
    ->addFieldToFilter('visibility', ['in' => [2, 3, 4]])
    ->addFieldToFilter('price', ['gt' => 10]);

// Add sorting
$collection->setOrder('created_at', 'DESC');

// Limit results
$collection->setPageSize(20)
    ->setCurPage(1);

// Access underlying select
$select = $collection->getSelect();
echo $select; // See generated SQL

// Iterate
foreach ($collection as $product) {
    echo $product->getSku() . "\n";
}
```

## Migration from Zend_Db

If you're migrating custom code from Magento 1 or OpenMage, here's what changed:

### Constants and Types

```php
// Before (Zend_Db)
Zend_Db::INT_TYPE
Zend_Db::BIGINT_TYPE
Zend_Db_Select::SQL_SELECT

// After (Doctrine DBAL - usually not needed directly)
// Use Maho's wrapper classes instead
```

### Select Object

```php
// Before
$select = new Zend_Db_Select($adapter);

// After - no changes needed
$select = $adapter->select();
```

### Expression Objects

```php
// Before
$expr = new Zend_Db_Expr('COUNT(*)');

// After - both work
$expr = new Varien_Db_Expr('COUNT(*)');  // Backward compatible
$expr = new Maho\Db\Expr('COUNT(*)');    // Modern approach
```

## GROUP BY Strictness & SQL Modes <span class="version-badge">v26.9+</span>

Every GROUP BY query in the Maho core is compliant with strict SQL grouping rules - the standard that PostgreSQL always enforces and that MySQL enforces under `ONLY_FULL_GROUP_BY`. And Maho no longer runs MySQL connections in the legacy relaxed `SQL_MODE=''`: it pins an explicit modern strict baseline on every connection.

### The strict SQL_MODE baseline

On connection init, Maho sets the MySQL 8+ default mode set:

```
ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,
ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

Pinning the mode per connection (rather than inheriting the server's global mode) keeps behavior deterministic on every host: a self-managed MySQL, a managed cluster, or a provider with exotic global flags all behave identically. What each flag buys you:

- `STRICT_TRANS_TABLES`: an overlong or out-of-range value is a hard error instead of being silently truncated or clamped into corrupted data
- `NO_ZERO_DATE` / `NO_ZERO_IN_DATE`: `'0000-00-00'` can no longer be written
- `ONLY_FULL_GROUP_BY`: a non-compliant GROUP BY query fails loudly instead of returning an arbitrary row per group:

```
SQLSTATE[42000]: Expression #1 of SELECT list is not in GROUP BY clause
and contains nonaggregated column ... which is not functionally dependent
on columns in GROUP BY clause
```

If one of these errors appears in your custom module, fix the query or the write path (see the patterns below): the same code now behaves identically in development, CI, and production, and compliant queries are also the ones that run on PostgreSQL.

!!! info "Why exactly this set"
    It is the MySQL 8+ factory default, the most battle-tested flag combination there is, so a Maho connection behaves exactly like stock modern MySQL. Stricter or different flags were considered and excluded deliberately: `STRICT_ALL_TABLES` can leave partial multi-row writes on non-transactional tables with no rollback (the same reason MySQL excludes it), the `ANSI` group changes how SQL is parsed and would break MySQL-idiom queries, and `NO_BACKSLASH_ESCAPES` would desynchronize the server from PDO's quoting. Providers that add such flags globally (see below) don't affect Maho, since the mode is pinned per connection.

!!! warning "MariaDB is exempt from ONLY_FULL_GROUP_BY only"
    MariaDB gets the same strict baseline **minus** `ONLY_FULL_GROUP_BY`: its implementation lacks the functional-dependency detection that MySQL 5.7+ and PostgreSQL have ([MDEV-11588](https://jira.mariadb.org/browse/MDEV-11588){target=_blank}, unresolved since 2016), so it would reject SQL-standard queries like `SELECT t.* ... GROUP BY t.pk`. If you develop on MariaDB, run your test suite against MySQL (locally or in CI) to catch grouping violations.

### Upgrading legacy stores: zero dates and the escape hatch

Stores migrated from Magento/OpenMage may carry `'0000-00-00'` values (and zero-date column defaults) written over the years under the relaxed mode. Under `NO_ZERO_DATE`, an UPDATE touching such a row, or an INSERT relying on such a default, fails. Run the health check to find them before they bite, then fix them:

```bash
./maho health-check            # detects zero dates (among many other checks)
./maho legacy:fix-zero-dates   # dry run: reports what would change
./maho legacy:fix-zero-dates --force   # applies the fixes
```

The fixer sets zero-date rows to NULL and changes zero-date defaults to `DEFAULT NULL` on nullable columns. NOT NULL columns are listed for manual attention instead: decide per column whether to make it nullable or backfill a meaningful real date.

If a store is blocked while cleaning up, a `<sql_mode>` node on the connection in `app/etc/local.xml` overrides the baseline verbatim - an empty value restores the old fully-relaxed behavior:

```xml
<connection>
    <host><![CDATA[localhost]]></host>
    <!-- ... -->
    <sql_mode><![CDATA[]]></sql_mode>
</connection>
```

Treat it as a temporary exit, not a configuration choice: every release is developed and tested against the strict baseline.

### Writing strict-compliant GROUP BY queries

Every selected column must either appear in the GROUP BY clause, be wrapped in an aggregate function, or be functionally dependent on the grouped columns. In practice, a handful of patterns cover almost every query:

**Group by the primary key.** Other columns of the same table are functionally dependent on it, so they can be selected freely:

```php
// ✅ Compliant on MySQL strict mode and PostgreSQL
$select->from(['e' => 'customer_entity'], ['entity_id', 'email', 'created_at'])
    ->joinLeft(['o' => 'sales_flat_order'], 'o.customer_id = e.entity_id', ['orders' => 'COUNT(o.entity_id)'])
    ->group('e.entity_id');
```

**Wrap constant-per-group joined columns in `MAX()` or `MIN()`.** When a joined column has one value per group but the database can't prove it, aggregate it explicitly:

```php
// ❌ store_name is not functionally dependent on the grouped column
$select->columns(['store_name' => 's.name'])->group('o.customer_id');

// ✅ same value, provably one row per group
$select->columns(['store_name' => new Maho\Db\Expr('MAX(s.name)')])->group('o.customer_id');
```

**Use `DISTINCT` instead of dedup-only GROUP BY.** If the query groups only to remove duplicate rows and selects no aggregates, `DISTINCT` says the same thing without triggering grouping rules:

```php
// ❌ GROUP BY used purely for deduplication
$select->from(['p' => 'catalog_product'], ['entity_id', 'sku'])->group('p.entity_id');

// ✅
$select->distinct()->from(['p' => 'catalog_product'], ['entity_id', 'sku']);
```

**Group by the expression, not its alias.** PostgreSQL rejects grouping by a SELECT alias in some positions; repeat the expression instead:

```php
// ❌ fails on PostgreSQL
$select->columns(['period' => "DATE_FORMAT(created_at, '%Y-%m')"])->group('period');

// ✅
$select->columns(['period' => new Maho\Db\Expr("DATE_FORMAT(created_at, '%Y-%m')")])
    ->group(new Maho\Db\Expr("DATE_FORMAT(created_at, '%Y-%m')"));
```

**Move grouping into a derived table** when the outer select must stay ungrouped, or **use a correlated subquery** for per-row counts.

!!! info "PostgreSQL is stricter than MySQL"
    MySQL 5.7+ infers functional dependencies broadly (primary keys, unique keys, WHERE equalities). PostgreSQL only recognizes grouping by a table's primary key. If your module targets PostgreSQL, stick to PK grouping and explicit aggregates; passing MySQL's strict mode is a good tripwire but not a formal guarantee.

### Managed databases and clusters

Managed database offerings tend to enforce strict SQL modes globally and don't hand out the `SUPER` privilege to change them. [DigitalOcean Managed MySQL](https://docs.digitalocean.com/products/databases/mysql/how-to/set-sql-mode/){target=_blank}, for example, defaults to a mode set that includes `ONLY_FULL_GROUP_BY` (alongside `ANSI`, `STRICT_ALL_TABLES`, `NO_ZERO_DATE` and others), and changing it requires their API or control panel. MySQL 8 itself ships with `ONLY_FULL_GROUP_BY` in its default mode, so self-hosted clusters increasingly enforce it too.

None of this affects Maho: it pins its own strict baseline per connection, so the provider's global mode is irrelevant - the same queries run the same way on a laptop, a managed cluster, or a self-hosted replica set.

Clusters gain a correctness benefit as well: results that were previously "an arbitrary row per group" become deterministic, so a load-balanced replica answers a query the same way as its primary.

## Best Practices

### 1. Use Read/Write Adapters Appropriately

```php
// ✅ Use read adapter for SELECT queries
$readAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
$products = $readAdapter->fetchAll($select);

// ✅ Use write adapter for INSERT/UPDATE/DELETE
$writeAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
$writeAdapter->insert('catalog_product', $data);
```

### 2. Use Parameterized Queries

```php
// ✅ Good - parameterized
$select->where('status = ?', $status)
    ->where('sku IN (?)', $skuArray);

// ❌ Bad - string concatenation
$select->where("status = '$status'");
```

### 3. Use Transactions for Multiple Operations

```php
// ✅ Good - atomic operation
$adapter->beginTransaction();
try {
    $adapter->insert('table1', $data1);
    $adapter->insert('table2', $data2);
    $adapter->commit();
} catch (Exception $e) {
    $adapter->rollBack();
    throw $e;
}

// ❌ Bad - no transaction
$adapter->insert('table1', $data1);
$adapter->insert('table2', $data2);
```

### 4. Use Collections for Model Data

```php
// ✅ Good - use collections
$collection = Mage::getModel('catalog/product')->getCollection()
    ->addFieldToFilter('status', 1);

// ❌ Less ideal - raw SQL for model data
$select = $adapter->select()->from('catalog_product');
```

### 5. Leverage Expression Objects

```php
// ✅ Good - use Expr for SQL functions
use Maho\Db\Expr;

$select->columns([
    'total' => new Expr('SUM(grand_total)'),
    'count' => new Expr('COUNT(*)')
]);

// ❌ Bad - string in columns
$select->columns([
    'total' => 'SUM(grand_total)'  // Will be quoted!
]);
```

### 6. Index Your Queries

```php
// When creating custom tables, add indexes
$table->addIndex(
    'IDX_CUSTOMER_STATUS',
    ['customer_id', 'status']
);

// For frequently filtered/joined columns
$table->addIndex('IDX_SKU', ['sku']);
$table->addIndex('IDX_CREATED', ['created_at']);
```

## Debugging Queries

### View Generated SQL

```php
// See what SQL will be executed
$select = $adapter->select()
    ->from('catalog_product')
    ->where('status = ?', 1);

echo $select->__toString();
// or
echo (string) $select;
```

## Performance Optimization

### 1. Use insertMultiple for Bulk Inserts

```php
// ✅ Fast - single query
$adapter->insertMultiple('catalog_product', $dataArray);

// ❌ Slow - multiple queries
foreach ($dataArray as $row) {
    $adapter->insert('catalog_product', $row);
}
```

### 2. Use insertFromSelect for Large Data Copies

```php
// ✅ Fast - single query on server
$select = $adapter->select()->from('source_table');
$adapter->insertFromSelect($select, 'dest_table', ['col1', 'col2']);

// ❌ Slow - fetch and re-insert
$rows = $adapter->fetchAll($select);
foreach ($rows as $row) {
    $adapter->insert('dest_table', $row);
}
```

### 3. Limit Collection Size

```php
// ✅ Good - paginate large collections
$collection->setPageSize(100)->setCurPage(1);
// load() respects pagination settings

// ❌ Bad - no pagination set
$collection->load(); // Loads all rows without any limit if no pagination set
```

### 4. Select Only Needed Columns

```php
// ✅ Good - specific columns
$select->from('catalog_product', ['entity_id', 'sku', 'name']);

// ❌ Less efficient - all columns
$select->from('catalog_product', '*');
```

## Troubleshooting

### Common Issues

**1. "Named parameter does not have a bound value"**

Old code with named parameters will be automatically converted. If you see this error in custom code:

```php
// This is now automatically handled
$select->where('sku = :sku', ['sku' => 'value']);
```

**2. "Column not found" with functions**

Functions need to be wrapped in Expr:

```php
// ❌ Wrong
$select->columns(['total' => 'COUNT(*)']);

// ✅ Correct
$select->columns(['total' => new Maho\Db\Expr('COUNT(*)')]);
```

**3. Integers quoted in WHERE clauses**

This is normal and doesn't affect performance - MySQL handles both forms identically:

```php
// Both work the same
WHERE id = 42      // Doctrine DBAL style
WHERE id = '42'    // Old Zend_Db style
```

**4. SELECT * becoming SELECT table.\***

This is more explicit and better for debugging - functionally identical:

```php
SELECT *           // Old style
SELECT table.*     // New style - more explicit
```

## Additional Resources

- [Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/index.html){target=_blank}
- [Maho Developer Guide - Models and ORM](guide/models-and-orm.md)
- [Maho Developer Guide - Data Collections](guide/data-collections.md)
- [Maho Developer Guide - Setup Resources](guide/setup-resources.md)

The database layer in Maho provides a solid foundation for data access with modern tooling, excellent performance, and complete backward compatibility with existing code.
