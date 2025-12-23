<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use PDO;

/**
 * Sample Data Importer with EAV attribute ID remapping
 *
 * This class handles importing sample data SQL while remapping attribute IDs
 * to match the current Maho installation. It parses the eav_attribute table
 * from the SQL dump to build a mapping, then remaps all attribute_id references
 * in the EAV value tables.
 */
class SampleDataImporter
{
    private PDO $pdo;

    /**
     * Tables that contain attribute_id column that needs remapping
     */
    private const TABLES_WITH_ATTRIBUTE_ID = [
        'catalog_category_entity_datetime',
        'catalog_category_entity_decimal',
        'catalog_category_entity_int',
        'catalog_category_entity_text',
        'catalog_category_entity_varchar',
        'catalog_product_entity_datetime',
        'catalog_product_entity_decimal',
        'catalog_product_entity_int',
        'catalog_product_entity_text',
        'catalog_product_entity_varchar',
        'catalog_eav_attribute',
        'customer_eav_attribute',
        'eav_attribute_group',
        'eav_attribute_label',
        'eav_attribute_option',
        'eav_entity_attribute',
        'catalog_product_super_attribute',
        'customer_form_attribute',
    ];

    /**
     * Tables that contain option_id values that need remapping
     */
    private const TABLES_WITH_OPTION_ID = [
        'eav_attribute_option_swatch',
    ];

    /**
     * Parsed attribute mapping from SQL: old_id => ['code' => string, 'entity_type_id' => int, ...]
     */
    private array $oldAttributes = [];

    /**
     * Parsed option mapping from SQL: old_option_id => ['attribute_id' => int, 'sort_order' => int]
     */
    private array $oldOptions = [];

    /**
     * Parsed option values from SQL: old_option_id => ['admin_value' => string]
     */
    private array $oldOptionValues = [];

    /**
     * Current DB attribute mapping: "entity_type_id:code" => current_attribute_id
     */
    private array $currentAttributes = [];

    /**
     * Remap: old_attribute_id => new_attribute_id
     */
    private array $attributeRemap = [];

    /**
     * Remap: old_option_id => new_option_id
     */
    private array $optionRemap = [];

    /**
     * User-defined attributes that need to be created
     */
    private array $attributesToCreate = [];

    /**
     * Column names from eav_attribute table (loaded from DB schema)
     */
    private array $eavAttributeColumns = [];

    /**
     * Column names from catalog_eav_attribute table (loaded from DB schema)
     */
    private array $catalogEavAttributeColumns = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadTableSchemas();
    }

    /**
     * Load column names from database table schemas
     */
    private function loadTableSchemas(): void
    {
        $this->eavAttributeColumns = $this->getTableColumns('eav_attribute');
        $this->catalogEavAttributeColumns = $this->getTableColumns('catalog_eav_attribute');
    }

    /**
     * Get column names for a table from the database
     */
    private function getTableColumns(string $table): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } elseif ($driver === 'pgsql') {
            $stmt = $this->pdo->prepare('
                SELECT column_name FROM information_schema.columns
                WHERE table_name = ? ORDER BY ordinal_position
            ');
            $stmt->execute([$table]);
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');
        } elseif ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info(`{$table}`)");
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
        }

        return [];
    }

    /**
     * Import sample data SQL with attribute ID remapping
     */
    public function import(string $sql, ?callable $progressCallback = null): string
    {
        // Step 1: Parse eav_attribute from SQL
        $this->parseEavAttributeFromSql($sql);

        // Step 2: Parse eav_attribute_option and option values from SQL
        $this->parseEavAttributeOptionFromSql($sql);

        // Step 3: Load current attribute mapping from installed DB
        $this->loadCurrentAttributes();

        // Step 4: Build attribute remap and identify attributes to create
        $this->buildAttributeRemap();

        // Step 5: Create missing user-defined attributes
        $this->createMissingAttributes();

        // Step 6: Build option remap (after attributes are created)
        $this->buildOptionRemap($sql);

        // Step 7: Remap the SQL
        $remappedSql = $this->remapSql($sql);

        return $remappedSql;
    }

    /**
     * Parsed catalog_eav_attribute data from SQL: attribute_id => [settings]
     */
    private array $oldCatalogEavAttributes = [];

    /**
     * Parse eav_attribute INSERT statements from SQL using dynamic column names
     */
    private function parseEavAttributeFromSql(string $sql): void
    {
        // Extract column names from SQL INSERT statement
        $pattern = '/REPLACE\s+INTO\s+`eav_attribute`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;\s*(?:\/\*|LOCK|UNLOCK|$))/si';

        if (!preg_match($pattern, $sql, $matches)) {
            return;
        }

        $sqlColumns = array_map(fn($c) => trim($c, ' `'), explode(',', $matches[1]));
        $valuesBlock = $matches[2];

        // Parse each row using the value row parser
        preg_match_all('/\(([^)]+)\)/s', $valuesBlock, $rowMatches);

        foreach ($rowMatches[1] as $rowContent) {
            $values = $this->parseValueRow($rowContent);

            if (count($values) < count($sqlColumns)) {
                continue;
            }

            // Build associative array from column names and values
            $rowData = [];
            foreach ($sqlColumns as $i => $column) {
                $rowData[$column] = $this->cleanSqlValue($values[$i] ?? '');
            }

            $attributeId = (int) ($rowData['attribute_id'] ?? 0);
            if ($attributeId === 0) {
                continue;
            }

            // Store with attribute_code as 'code' for consistency
            $rowData['code'] = $rowData['attribute_code'] ?? '';
            $this->oldAttributes[$attributeId] = $rowData;
        }

        // Also parse catalog_eav_attribute for product attribute settings
        $this->parseCatalogEavAttributeFromSql($sql);
    }

    /**
     * Parse catalog_eav_attribute INSERT statements from SQL using dynamic column names
     */
    private function parseCatalogEavAttributeFromSql(string $sql): void
    {
        $pattern = '/REPLACE\s+INTO\s+`catalog_eav_attribute`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;\s*(?:\/\*|LOCK|UNLOCK|$))/si';

        if (!preg_match($pattern, $sql, $matches)) {
            return;
        }

        $sqlColumns = array_map(fn($c) => trim($c, ' `'), explode(',', $matches[1]));
        $valuesBlock = $matches[2];

        preg_match_all('/\(([^)]+)\)/s', $valuesBlock, $rowMatches);

        foreach ($rowMatches[1] as $rowContent) {
            $values = $this->parseValueRow($rowContent);

            if (count($values) < count($sqlColumns)) {
                continue;
            }

            // Build associative array from column names and values
            $rowData = [];
            foreach ($sqlColumns as $i => $column) {
                $rowData[$column] = $this->cleanSqlValue($values[$i] ?? '');
            }

            $attributeId = (int) ($rowData['attribute_id'] ?? 0);
            if ($attributeId === 0) {
                continue;
            }

            $this->oldCatalogEavAttributes[$attributeId] = $rowData;
        }
    }

    /**
     * Clean a SQL value (remove quotes, handle NULL)
     */
    private function cleanSqlValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === 'NULL' || $value === '') {
            return null;
        }
        // Remove surrounding quotes
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
            // Unescape SQL quotes
            $value = str_replace("''", "'", $value);
        }
        return $value;
    }

    /**
     * Parse eav_attribute_option INSERT statements from SQL
     */
    private function parseEavAttributeOptionFromSql(string $sql): void
    {
        // Parse eav_attribute_option: (option_id, attribute_id, sort_order)
        $pattern = '/REPLACE\s+INTO\s+`eav_attribute_option`[^V]*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|REPLACE|$))/si';

        if (preg_match($pattern, $sql, $matches)) {
            preg_match_all('/\((\d+),(\d+),(\d+)\)/', $matches[1], $rowMatches, PREG_SET_ORDER);

            foreach ($rowMatches as $row) {
                $optionId = (int) $row[1];
                $attributeId = (int) $row[2];
                $sortOrder = (int) $row[3];

                $this->oldOptions[$optionId] = [
                    'attribute_id' => $attributeId,
                    'sort_order' => $sortOrder,
                ];
            }
        }

        // Parse eav_attribute_option_value: (value_id, option_id, store_id, 'value')
        $pattern = '/REPLACE\s+INTO\s+`eav_attribute_option_value`\s*\([^)]+\)\s*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|REPLACE|UNLOCK|$))/si';

        if (preg_match($pattern, $sql, $matches)) {
            // Match: (value_id, option_id, store_id, 'value')
            preg_match_all('/\((\d+),(\d+),(\d+),\'((?:[^\']|\'\')*)\'\)/', $matches[1], $rowMatches, PREG_SET_ORDER);

            foreach ($rowMatches as $row) {
                $optionId = (int) $row[2];
                $storeId = (int) $row[3];
                $value = str_replace("''", "'", $row[4]); // Unescape SQL quotes

                // We only need admin (store_id=0) values for matching
                if ($storeId === 0 && !isset($this->oldOptionValues[$optionId])) {
                    $this->oldOptionValues[$optionId] = $value;
                }
            }
        }
    }

    /**
     * Load current attribute mapping from installed database
     */
    private function loadCurrentAttributes(): void
    {
        $stmt = $this->pdo->query('SELECT attribute_id, attribute_code, entity_type_id, is_user_defined FROM eav_attribute');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $key = $row['entity_type_id'] . ':' . $row['attribute_code'];
            $this->currentAttributes[$key] = [
                'attribute_id' => (int) $row['attribute_id'],
                'is_user_defined' => (bool) $row['is_user_defined'],
            ];
        }
    }

    /**
     * Build attribute ID remap and identify missing attributes
     */
    private function buildAttributeRemap(): void
    {
        foreach ($this->oldAttributes as $oldId => $info) {
            $key = $info['entity_type_id'] . ':' . $info['code'];

            if (isset($this->currentAttributes[$key])) {
                // Attribute exists - map old ID to current ID
                $this->attributeRemap[$oldId] = $this->currentAttributes[$key]['attribute_id'];
            } else {
                // Attribute doesn't exist - need to create it
                // But only create user-defined attributes (system attributes should exist)
                if (($info['is_user_defined'] ?? 0) == 1) {
                    $this->attributesToCreate[$oldId] = $info;
                } else {
                    echo "WARNING: System attribute not found: {$info['code']} (entity_type={$info['entity_type_id']}, old_id={$oldId})\n";
                }
            }
        }
    }

    /**
     * Create missing user-defined attributes in the database
     */
    private function createMissingAttributes(): void
    {
        if (empty($this->attributesToCreate)) {
            return;
        }

        // Build dynamic INSERT for eav_attribute using schema columns (excluding auto-increment attribute_id)
        $columns = array_filter($this->eavAttributeColumns, fn($c) => $c !== 'attribute_id');
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        $stmt = $this->pdo->prepare("INSERT INTO eav_attribute ({$columnList}) VALUES ({$placeholders})");

        foreach ($this->attributesToCreate as $oldId => $info) {
            // Build values array matching column order
            $values = [];
            foreach ($columns as $column) {
                $value = $info[$column] ?? null;

                // Apply defaults for required fields
                if ($value === null) {
                    $value = match ($column) {
                        'backend_type' => 'varchar',
                        'frontend_input' => 'text',
                        'frontend_label' => ucwords(str_replace('_', ' ', $info['code'] ?? '')),
                        'is_user_defined' => 1,
                        'is_required', 'is_unique' => 0,
                        default => null,
                    };
                }

                $values[] = $value;
            }

            $stmt->execute($values);

            $newId = (int) $this->pdo->lastInsertId();
            $this->attributeRemap[$oldId] = $newId;

            // If this is a product/category attribute, also create catalog_eav_attribute entry
            $entityTypeId = (int) ($info['entity_type_id'] ?? 0);
            if (in_array($entityTypeId, [3, 4]) && isset($this->oldCatalogEavAttributes[$oldId])) {
                $this->createCatalogEavAttribute($newId, $this->oldCatalogEavAttributes[$oldId]);
            }

            // Update current attributes cache
            $key = $entityTypeId . ':' . $info['code'];
            $this->currentAttributes[$key] = [
                'attribute_id' => $newId,
                'is_user_defined' => true,
            ];
        }
    }

    /**
     * Create catalog_eav_attribute entry for product/category attributes
     */
    private function createCatalogEavAttribute(int $attributeId, array $settings): void
    {
        // Build dynamic INSERT using schema columns
        $columns = $this->catalogEavAttributeColumns;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        $stmt = $this->pdo->prepare("INSERT INTO catalog_eav_attribute ({$columnList}) VALUES ({$placeholders})");

        // Build values array matching column order
        $values = [];
        foreach ($columns as $column) {
            if ($column === 'attribute_id') {
                $values[] = $attributeId;
            } else {
                $value = $settings[$column] ?? null;

                // Apply defaults for common fields
                if ($value === null) {
                    $value = match ($column) {
                        'is_global' => 1,
                        'is_visible' => 1,
                        default => str_starts_with($column, 'is_') || str_starts_with($column, 'used_') ? 0 : null,
                    };
                }

                $values[] = $value;
            }
        }

        $stmt->execute($values);
    }

    /**
     * Build option ID remap
     */
    private function buildOptionRemap(string $sql): void
    {
        // Load current options from DB
        $currentOptions = [];
        $stmt = $this->pdo->query('
            SELECT o.option_id, o.attribute_id, v.value
            FROM eav_attribute_option o
            LEFT JOIN eav_attribute_option_value v ON o.option_id = v.option_id AND v.store_id = 0
        ');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['attribute_id'] . ':' . strtolower($row['value'] ?? '');
            $currentOptions[$key] = (int) $row['option_id'];
        }

        // Build remap
        foreach ($this->oldOptions as $oldOptionId => $optionInfo) {
            $oldAttributeId = $optionInfo['attribute_id'];
            $newAttributeId = $this->attributeRemap[$oldAttributeId] ?? $oldAttributeId;

            $optionValue = $this->oldOptionValues[$oldOptionId] ?? '';
            $key = $newAttributeId . ':' . strtolower($optionValue);

            if (isset($currentOptions[$key])) {
                $this->optionRemap[$oldOptionId] = $currentOptions[$key];
            } else {
                // Option doesn't exist - create it
                $stmt = $this->pdo->prepare('INSERT INTO eav_attribute_option (attribute_id, sort_order) VALUES (?, ?)');
                $stmt->execute([$newAttributeId, $optionInfo['sort_order']]);
                $newOptionId = (int) $this->pdo->lastInsertId();

                // Add option value
                if ($optionValue !== '') {
                    $stmt = $this->pdo->prepare('INSERT INTO eav_attribute_option_value (option_id, store_id, value) VALUES (?, 0, ?)');
                    $stmt->execute([$newOptionId, $optionValue]);
                }

                $this->optionRemap[$oldOptionId] = $newOptionId;
                $currentOptions[$key] = $newOptionId;
            }
        }
    }

    /**
     * Remap attribute IDs and option IDs in the SQL
     */
    private function remapSql(string $sql): string
    {
        // Skip eav_attribute table - core attributes already exist, we just remap IDs
        $sql = $this->removeTableBlock($sql, 'eav_attribute');

        // Skip eav_attribute_option - we created options in buildOptionRemap
        $sql = $this->removeTableBlock($sql, 'eav_attribute_option');

        // Skip eav_attribute_option_value - we created option values in buildOptionRemap
        $sql = $this->removeTableBlock($sql, 'eav_attribute_option_value');

        // Remap attribute_id in EAV value tables
        foreach (self::TABLES_WITH_ATTRIBUTE_ID as $table) {
            $sql = $this->remapAttributeIdInTable($sql, $table);
        }

        // Remap option_id in option-related tables (including eav_attribute_option_value for store translations)
        foreach (self::TABLES_WITH_OPTION_ID as $table) {
            $sql = $this->remapOptionIdInTable($sql, $table);
        }

        // Remap option values in catalog_product_entity_int (select attribute values)
        $sql = $this->remapOptionValuesInProductEntityInt($sql);

        // Also remap option values in catalog_category_entity_int
        $sql = $this->remapOptionValuesInCategoryEntityInt($sql);

        // Handle special config values like configswatches/general/swatch_attributes
        $sql = $this->remapConfigValues($sql);

        return $sql;
    }

    /**
     * Remove a table's INSERT block from SQL (we've already processed it)
     */
    private function removeTableBlock(string $sql, string $table): string
    {
        $pattern = '/LOCK TABLES `' . preg_quote($table, '/') . '` WRITE;.*?UNLOCK TABLES;\s*/si';
        return preg_replace($pattern, '', $sql);
    }

    /**
     * Remap attribute_id values in a specific table's INSERT statements
     */
    private function remapAttributeIdInTable(string $sql, string $table): string
    {
        // Find the INSERT block for this table
        $pattern = '/REPLACE\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|UNLOCK|$))/si';

        return preg_replace_callback($pattern, function ($matches) use ($table) {
            $columns = $matches[1];
            $valuesBlock = $matches[2];

            // Find the position of attribute_id column
            $columnList = array_map('trim', explode(',', str_replace('`', '', $columns)));
            $attrIdPos = array_search('attribute_id', $columnList);

            if ($attrIdPos === false) {
                return $matches[0];
            }

            // Remap each row
            $valuesBlock = $this->remapColumnInValues($valuesBlock, $attrIdPos, $this->attributeRemap);

            return "REPLACE INTO `{$table}` ({$columns}) VALUES {$valuesBlock}";
        }, $sql);
    }

    /**
     * Remap option_id values in a specific table's INSERT statements
     */
    private function remapOptionIdInTable(string $sql, string $table): string
    {
        $pattern = '/REPLACE\s+INTO\s+`' . preg_quote($table, '/') . '`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|UNLOCK|$))/si';

        return preg_replace_callback($pattern, function ($matches) use ($table) {
            $columns = $matches[1];
            $valuesBlock = $matches[2];

            $columnList = array_map('trim', explode(',', str_replace('`', '', $columns)));
            $optionIdPos = array_search('option_id', $columnList);

            if ($optionIdPos === false) {
                return $matches[0];
            }

            $valuesBlock = $this->remapColumnInValues($valuesBlock, $optionIdPos, $this->optionRemap);

            return "REPLACE INTO `{$table}` ({$columns}) VALUES {$valuesBlock}";
        }, $sql);
    }

    /**
     * Remap option values in catalog_product_entity_int
     * For select/multiselect attributes, the value column contains option_id
     */
    private function remapOptionValuesInProductEntityInt(string $sql): string
    {
        $pattern = '/REPLACE\s+INTO\s+`catalog_product_entity_int`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|UNLOCK|$))/si';

        return preg_replace_callback($pattern, function ($matches) {
            $columns = $matches[1];
            $valuesBlock = $matches[2];

            $columnList = array_map('trim', explode(',', str_replace('`', '', $columns)));
            $attrIdPos = array_search('attribute_id', $columnList);
            $valuePos = array_search('value', $columnList);

            if ($attrIdPos === false || $valuePos === false) {
                return $matches[0];
            }

            // Get list of select/multiselect attributes that use option IDs as values
            $selectAttributes = $this->getSelectAttributeIds();

            // Parse and remap each row
            $valuesBlock = $this->remapOptionValueInProductInt($valuesBlock, $attrIdPos, $valuePos, $selectAttributes);

            return "REPLACE INTO `catalog_product_entity_int` ({$columns}) VALUES {$valuesBlock}";
        }, $sql);
    }

    /**
     * Remap option values in catalog_category_entity_int
     * For select/multiselect attributes, the value column contains option_id
     */
    private function remapOptionValuesInCategoryEntityInt(string $sql): string
    {
        $pattern = '/REPLACE\s+INTO\s+`catalog_category_entity_int`\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?=;[\s]*(?:LOCK|UNLOCK|$))/si';

        return preg_replace_callback($pattern, function ($matches) {
            $columns = $matches[1];
            $valuesBlock = $matches[2];

            $columnList = array_map('trim', explode(',', str_replace('`', '', $columns)));
            $attrIdPos = array_search('attribute_id', $columnList);
            $valuePos = array_search('value', $columnList);

            if ($attrIdPos === false || $valuePos === false) {
                return $matches[0];
            }

            // Get list of select/multiselect attributes that use option IDs as values
            $selectAttributes = $this->getSelectAttributeIds();

            // Parse and remap each row (reuse the product method)
            $valuesBlock = $this->remapOptionValueInProductInt($valuesBlock, $attrIdPos, $valuePos, $selectAttributes);

            return "REPLACE INTO `catalog_category_entity_int` ({$columns}) VALUES {$valuesBlock}";
        }, $sql);
    }

    /**
     * Get attribute IDs that are select/multiselect (their int values are option IDs)
     */
    private function getSelectAttributeIds(): array
    {
        $stmt = $this->pdo->query("
            SELECT attribute_id FROM eav_attribute
            WHERE frontend_input IN ('select', 'multiselect')
              AND (source_model IS NULL OR source_model = 'eav/entity_attribute_source_table')
        ");

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'attribute_id');
    }

    /**
     * Remap a specific column position in VALUES block
     */
    private function remapColumnInValues(string $valuesBlock, int $columnPos, array $remap): string
    {
        // Parse row by row
        return preg_replace_callback('/\(([^)]+)\)/', function ($match) use ($columnPos, $remap) {
            $values = $this->parseValueRow($match[1]);

            if (isset($values[$columnPos])) {
                $oldValue = trim($values[$columnPos]);
                if (is_numeric($oldValue) && isset($remap[(int) $oldValue])) {
                    $values[$columnPos] = (string) $remap[(int) $oldValue];
                }
            }

            return '(' . implode(',', $values) . ')';
        }, $valuesBlock);
    }

    /**
     * Remap option values in product entity int rows
     */
    private function remapOptionValueInProductInt(string $valuesBlock, int $attrIdPos, int $valuePos, array $selectAttributes): string
    {
        return preg_replace_callback('/\(([^)]+)\)/', function ($match) use ($attrIdPos, $valuePos, $selectAttributes) {
            $values = $this->parseValueRow($match[1]);

            // Remap attribute_id first
            if (isset($values[$attrIdPos])) {
                $oldAttrId = (int) trim($values[$attrIdPos]);
                if (isset($this->attributeRemap[$oldAttrId])) {
                    $newAttrId = $this->attributeRemap[$oldAttrId];
                    $values[$attrIdPos] = (string) $newAttrId;
                } else {
                    $newAttrId = $oldAttrId;
                }

                // If this is a select attribute, remap the value (option_id)
                if (in_array($newAttrId, $selectAttributes) && isset($values[$valuePos])) {
                    $oldOptionId = trim($values[$valuePos]);
                    if ($oldOptionId !== 'NULL' && is_numeric($oldOptionId) && isset($this->optionRemap[(int) $oldOptionId])) {
                        $values[$valuePos] = (string) $this->optionRemap[(int) $oldOptionId];
                    }
                }
            }

            return '(' . implode(',', $values) . ')';
        }, $valuesBlock);
    }

    /**
     * Parse a SQL value row, handling quoted strings and NULLs
     */
    private function parseValueRow(string $row): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($row); $i++) {
            $char = $row[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($char === "'" && !$inString) {
                $inString = true;
                $current .= $char;
                continue;
            }

            if ($char === "'" && $inString) {
                // Check for escaped quote ''
                if (isset($row[$i + 1]) && $row[$i + 1] === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }
                $inString = false;
                $current .= $char;
                continue;
            }

            if ($char === ',' && !$inString) {
                $values[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $values[] = $current;

        return $values;
    }

    /**
     * Remap attribute IDs in config values like configswatches/general/swatch_attributes
     */
    private function remapConfigValues(string $sql): string
    {
        // Handle configswatches paths that contain comma-separated attribute IDs
        // Format: ('default', '0', 'path', 'value', NULL)
        $configPaths = [
            'configswatches/general/swatch_attributes',
            'configswatches/general/product_list_attribute',
        ];

        foreach ($configPaths as $path) {
            $escapedPath = preg_quote($path, '#');
            $sql = preg_replace_callback(
                "#\('([^']*)',\s*'([^']*)',\s*'{$escapedPath}',\s*'([^']*)'#",
                function ($match) use ($path) {
                    $scope = $match[1];
                    $scopeId = $match[2];
                    $value = $match[3];

                    if (str_contains($value, ',')) {
                        // Comma-separated list of attribute IDs
                        $ids = array_map('intval', explode(',', $value));
                        $newIds = array_map(fn($id) => $this->attributeRemap[$id] ?? $id, $ids);
                        $value = implode(',', $newIds);
                    } else {
                        // Single attribute ID
                        $id = (int) $value;
                        if (isset($this->attributeRemap[$id])) {
                            $value = (string) $this->attributeRemap[$id];
                        }
                    }

                    return "('{$scope}', '{$scopeId}', '{$path}', '{$value}'";
                },
                $sql,
            );
        }

        return $sql;
    }

    /**
     * Get the attribute remap for debugging/logging
     */
    public function getAttributeRemap(): array
    {
        return $this->attributeRemap;
    }

    /**
     * Build attribute mappings without full import (for pre-processing db_preparation.sql)
     */
    public function buildAttributeMappings(string $sql): void
    {
        // Parse attributes from SQL
        $this->parseEavAttributeFromSql($sql);

        // Load current attributes from DB
        $this->loadCurrentAttributes();

        // Build attribute remap
        $this->buildAttributeRemap();

        // Create missing attributes (so they exist when we import db_preparation.sql)
        $this->createMissingAttributes();
    }

    /**
     * Remap only config values (for db_preparation.sql)
     */
    public function remapConfigValuesOnly(string $sql): string
    {
        return $this->remapConfigValues($sql);
    }

    /**
     * Generate SQL to update config values after db_preparation.sql has been imported
     */
    public function generateConfigUpdateSql(): string
    {
        $updates = [];

        // Update configswatches/general/swatch_attributes
        $result = $this->pdo->query("
            SELECT value FROM core_config_data
            WHERE path = 'configswatches/general/swatch_attributes'
        ");
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        if ($row && $row['value']) {
            $ids = array_map('intval', explode(',', $row['value']));
            $newIds = array_map(fn($id) => $this->attributeRemap[$id] ?? $id, $ids);
            $newValue = implode(',', $newIds);

            if ($newValue !== $row['value']) {
                $updates[] = "UPDATE core_config_data SET value = '{$newValue}' WHERE path = 'configswatches/general/swatch_attributes'";
            }
        }

        // Update configswatches/general/product_list_attribute
        $result = $this->pdo->query("
            SELECT value FROM core_config_data
            WHERE path = 'configswatches/general/product_list_attribute'
        ");
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        if ($row && $row['value']) {
            $id = (int) $row['value'];
            if (isset($this->attributeRemap[$id])) {
                $newId = $this->attributeRemap[$id];
                $updates[] = "UPDATE core_config_data SET value = '{$newId}' WHERE path = 'configswatches/general/product_list_attribute'";
            }
        }

        return implode('; ', $updates);
    }

    /**
     * Get the option remap for debugging/logging
     */
    public function getOptionRemap(): array
    {
        return $this->optionRemap;
    }
}
