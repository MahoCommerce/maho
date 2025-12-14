<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

/**
 * SQL Converter for transforming MySQL SQL dumps to PostgreSQL-compatible SQL
 */
class SqlConverter
{
    /**
     * PDO connection for checking column types
     */
    private ?\PDO $pdo = null;

    /**
     * Cache of boolean columns per table
     */
    private array $booleanColumnsCache = [];


    /**
     * Attribute code to ID mapping from target database
     */
    private array $attributeMapping = [];

    /**
     * Sample data attribute ID to code mapping based on value patterns
     * This maps hardcoded sample data attribute IDs to the correct attribute codes
     * The sample data was created with different attribute ID assignments than current Maho
     *
     * Key pattern values that identify each attribute:
     * - display_mode: PRODUCTS, PAGE, PRODUCTS_AND_PAGE
     * - url_path: category URL paths (women, men/shirts, etc.)
     * - page_layout: one_column, two_columns_left, etc.
     */
    private const SAMPLE_DATA_VALUE_PATTERNS = [
        'display_mode' => ['PRODUCTS', 'PAGE', 'PRODUCTS_AND_PAGE'],
        'page_layout' => ['one_column', 'two_columns_left', 'two_columns_right', 'three_columns', 'empty'],
    ];

    /**
     * Sample data attribute ID to attribute code mapping for INT/DECIMAL tables
     * The sample data uses different attribute IDs than current Maho
     * Format: entity_type_id => [sample_attr_id => attr_code]
     */
    private const SAMPLE_DATA_INT_ATTR_MAPPING = [
        // catalog_category (entity_type_id = 3)
        3 => [
            44 => 'is_active',
            52 => 'landing_page',
            53 => 'is_anchor',
            69 => 'custom_apply_to_products',
            70 => 'custom_use_parent_settings',
            71 => 'include_in_menu',
            134 => 'is_dynamic',
        ],
    ];

    /**
     * Set PDO connection for schema checking
     */
    public function setPdo(\PDO $pdo): void
    {
        $this->pdo = $pdo;
        $this->loadAttributeMapping();
    }

    /**
     * Load attribute code to ID mapping from the target database
     */
    private function loadAttributeMapping(): void
    {
        if ($this->pdo === null) {
            return;
        }

        try {
            $stmt = $this->pdo->query('
                SELECT attribute_code, attribute_id, backend_type, entity_type_id
                FROM eav_attribute
                WHERE entity_type_id IN (3, 4)
            '); // 3=catalog_category, 4=catalog_product
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $key = $row['entity_type_id'] . '_' . $row['attribute_code'];
                $this->attributeMapping[$key] = [
                    'attribute_id' => (int) $row['attribute_id'],
                    'backend_type' => $row['backend_type'],
                ];
            }
        } catch (\PDOException $e) {
            // Silently ignore - mapping won't be available
        }
    }

    /**
     * Get the correct attribute ID for a given attribute code and entity type
     */
    private function getAttributeId(int $entityTypeId, string $attributeCode): ?int
    {
        $key = $entityTypeId . '_' . $attributeCode;
        return $this->attributeMapping[$key]['attribute_id'] ?? null;
    }

    /**
     * Get the backend type for a given attribute code and entity type
     */
    private function getAttributeBackendType(int $entityTypeId, string $attributeCode): ?string
    {
        $key = $entityTypeId . '_' . $attributeCode;
        return $this->attributeMapping[$key]['backend_type'] ?? null;
    }

    /**
     * Known url_key values from sample data (exact matches)
     */
    private const KNOWN_URL_KEYS = [
        'sale', 'vip', 'women', 'men', 'accessories', 'home-decor',
        'new-arrivals', 'tops-blouses', 'pants-denim', 'dresses-skirts',
        'shirts', 'tees-knits-and-polos', 'blazers', 'eyewear', 'jewelry',
        'shoes', 'bags-luggage', 'books-music', 'bed-bath', 'electronics',
        'decorative-accents', 'default-category',
    ];

    /**
     * Known category names from sample data (exact matches)
     */
    private const KNOWN_CATEGORY_NAMES = [
        'Root Catalog', 'Default Category', 'Women', 'Men', 'Accessories',
        'Home & Decor', 'Sale', 'VIP', 'New Arrivals', 'Tops & Blouses',
        'Pants & Denim', 'Dresses & Skirts', 'Shirts', 'Tees, Knits and Polos',
        'Blazers', 'Eyewear', 'Jewelry', 'Shoes', 'Bags & Luggage',
        'Books & Music', 'Bed & Bath', 'Electronics', 'Decorative Accents',
    ];

    /**
     * Detect the correct attribute code based on value patterns
     */
    private function detectAttributeCodeFromValue(string $value): ?string
    {
        // Exact matches for known value patterns (display_mode, page_layout)
        foreach (self::SAMPLE_DATA_VALUE_PATTERNS as $attrCode => $patterns) {
            if (in_array($value, $patterns, true)) {
                return $attrCode;
            }
        }

        // Exact match for known category names (check BEFORE url_key since names may have
        // lowercase versions that match url_keys, e.g., "Sale" vs "sale")
        if (in_array($value, self::KNOWN_CATEGORY_NAMES, true)) {
            return 'name';
        }

        // Exact match for known url_keys (must be lowercase to match)
        // Only match if the value is already lowercase to avoid matching capitalized names
        if ($value === strtolower($value) && in_array($value, self::KNOWN_URL_KEYS, true)) {
            return 'url_key';
        }

        // url_path pattern: category paths with slashes (e.g., "men/shirts", "home-decor/electronics.html")
        if (preg_match('/^[a-z0-9-]+(\/[a-z0-9-]+)+(\.html)?$/', $value)) {
            return 'url_path';
        }

        return null;
    }

    /**
     * Convert MySQL SQL content to PostgreSQL-compatible SQL
     *
     * @param string $mysqlSql The MySQL SQL content
     * @return string PostgreSQL-compatible SQL
     */
    public function mysqlToPostgresql(string $mysqlSql): string
    {
        // Remove MySQL-specific comments (/*!40101 SET ... */)
        $sql = preg_replace('/\/\*!\d+.*?\*\/;?\s*/s', '', $mysqlSql);

        // Remove LOCK/UNLOCK TABLE statements (MySQL-specific)
        // Use word boundary \b to prevent matching "LOCK" inside "UNLOCK"
        $sql = preg_replace('/\bLOCK TABLES.*?;\s*/i', '', $sql);
        $sql = preg_replace('/\bUNLOCK TABLES;\s*/i', '', $sql);

        // Remove SET NAMES (MySQL-specific charset setting)
        $sql = preg_replace('/SET\s+NAMES\s+\w+\s*;?\s*/i', '', $sql);

        // Remove SET TIME_ZONE (MySQL-specific)
        $sql = preg_replace('/SET\s+TIME_ZONE\s*=\s*[^;]+;\s*/i', '', $sql);

        // Remove SET sql_mode (MySQL-specific)
        $sql = preg_replace('/SET\s+SQL_MODE\s*=\s*[^;]+;\s*/i', '', $sql);

        // Remove SET FOREIGN_KEY_CHECKS (MySQL-specific)
        $sql = preg_replace('/SET\s+FOREIGN_KEY_CHECKS\s*=\s*\d+\s*;?\s*/i', '', $sql);

        // Remove SET UNIQUE_CHECKS (MySQL-specific)
        $sql = preg_replace('/SET\s+UNIQUE_CHECKS\s*=\s*\d+\s*;?\s*/i', '', $sql);

        // Remove SET SQL_NOTES (MySQL-specific)
        $sql = preg_replace('/SET\s+SQL_NOTES\s*=\s*\d+\s*;?\s*/i', '', $sql);

        // Replace backticks with double quotes for identifiers
        $sql = $this->convertBackticksToDoubleQuotes($sql);

        // Handle NULL values in INSERT - convert \N to NULL
        $sql = str_replace("'\\N'", 'NULL', $sql);

        // Handle escaped single quotes - MySQL uses \', PostgreSQL uses ''
        $sql = str_replace("\\'", "''", $sql);

        // Handle escaped backslashes
        $sql = str_replace('\\\\', '\\', $sql);

        // Convert MySQL hex literals (X'...' or 0x...) to PostgreSQL decimal values
        // MySQL uses hex for integers, PostgreSQL treats X'...' as bit strings
        // PostgreSQL bigint max is 9223372036854775807 (2^63-1)
        // Note: Sample data has some corrupted hex values (contain UTF-8 replacement chars)
        // We use 0 as fallback since some columns have NOT NULL constraints
        $sql = preg_replace_callback(
            '/X\'([0-9A-Fa-f]+)\'/',
            function ($match) {
                $hexValue = $match[1];
                // Hex values over 15 chars (60 bits) may overflow bigint
                // Also check if hexdec returns a float (overflow indicator)
                if (strlen($hexValue) <= 15) {
                    $decimal = hexdec($hexValue);
                    if (is_int($decimal)) {
                        return (string) $decimal;
                    }
                }
                // For larger values, check against bigint max
                if (function_exists('gmp_cmp') && function_exists('gmp_strval')) {
                    $value = gmp_init($hexValue, 16);
                    $bigintMax = gmp_init('9223372036854775807');
                    if (gmp_cmp($value, $bigintMax) <= 0) {
                        return gmp_strval($value, 10);
                    }
                }
                // Value exceeds bigint range - use 0 as fallback (NOT NULL columns)
                return '0';
            },
            $sql,
        );

        // Also handle 0x... format hex literals
        $sql = preg_replace_callback(
            '/\b0x([0-9A-Fa-f]+)\b/',
            function ($match) {
                $hexValue = $match[1];
                if (strlen($hexValue) <= 15) {
                    $decimal = hexdec($hexValue);
                    if (is_int($decimal)) {
                        return (string) $decimal;
                    }
                }
                if (function_exists('gmp_cmp') && function_exists('gmp_strval')) {
                    $value = gmp_init($hexValue, 16);
                    $bigintMax = gmp_init('9223372036854775807');
                    if (gmp_cmp($value, $bigintMax) <= 0) {
                        return gmp_strval($value, 10);
                    }
                }
                return '0';
            },
            $sql,
        );

        // Convert REPLACE INTO to INSERT INTO (ON CONFLICT handling done in convertBooleanValues)
        $sql = preg_replace('/REPLACE\s+INTO/i', 'INSERT INTO', $sql);

        // Remove ENGINE= clauses that might appear
        $sql = preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $sql);

        // Remove CHARACTER SET and COLLATE clauses
        $sql = preg_replace('/\s*CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = preg_replace('/\s*COLLATE\s+\w+/i', '', $sql);

        // Handle ON DUPLICATE KEY UPDATE (convert to ON CONFLICT DO UPDATE for PostgreSQL)
        // For simple cases, just remove it since we're loading into empty tables
        $sql = preg_replace('/\s*ON\s+DUPLICATE\s+KEY\s+UPDATE.*?(?=;)/is', '', $sql);

        // Convert boolean values in INSERT statements
        $sql = $this->convertBooleanValues($sql);

        // Fix EAV attribute IDs in category/product value tables
        // Sample data has hardcoded attribute IDs that may not match the target database
        $sql = $this->fixEavAttributeIds($sql);

        return $sql;
    }

    /**
     * Fix EAV attribute IDs in value table INSERTs
     *
     * The sample data has hardcoded attribute IDs that don't match current Maho's
     * attribute ID assignments. This method detects the correct attribute code
     * based on value patterns and rewrites the attribute_id to match the target database.
     */
    private function fixEavAttributeIds(string $sql): string
    {
        if ($this->pdo === null || empty($this->attributeMapping)) {
            return $sql;
        }

        // EAV value tables that need attribute ID fixing
        // Format: table pattern => [entity_type_id, attribute_id column index, value column index, table_backend_type]
        $eavTables = [
            'catalog_category_entity_varchar' => [3, 2, 5, 'varchar'],
            'catalog_category_entity_text' => [3, 2, 5, 'text'],
            'catalog_category_entity_int' => [3, 2, 5, 'int'],
            'catalog_category_entity_decimal' => [3, 2, 5, 'decimal'],
            'catalog_product_entity_varchar' => [4, 2, 5, 'varchar'],
            'catalog_product_entity_text' => [4, 2, 5, 'text'],
            'catalog_product_entity_int' => [4, 2, 5, 'int'],
            'catalog_product_entity_decimal' => [4, 2, 5, 'decimal'],
        ];

        $lines = explode("\n", $sql);
        $result = [];
        $inInsert = false;
        $insertBuffer = '';
        $currentTable = null;
        $entityTypeId = null;
        $attrIdIndex = null;
        $valueIndex = null;
        $tableBackendType = null;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detect start of INSERT statement into EAV value table
            if (preg_match('/^INSERT\s+INTO\s+"?(\w+)"?\s*\(/i', $trimmedLine, $matches)) {
                // Finish any previous insert
                if ($inInsert && !empty($insertBuffer)) {
                    if ($currentTable !== null) {
                        $insertBuffer = $this->fixAttributeIdsInInsert($insertBuffer, $entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType);
                    }
                    $result[] = $insertBuffer;
                }

                $tableName = strtolower($matches[1]);
                if (isset($eavTables[$tableName])) {
                    $currentTable = $tableName;
                    [$entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType] = $eavTables[$tableName];
                } else {
                    $currentTable = null;
                    $tableBackendType = null;
                }

                $insertBuffer = $line;

                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;
                    if ($currentTable !== null) {
                        $insertBuffer = $this->fixAttributeIdsInInsert($insertBuffer, $entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType);
                    }
                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $currentTable = null;
                    $tableBackendType = null;
                } else {
                    $inInsert = true;
                }
                continue;
            }

            if ($inInsert) {
                $insertBuffer .= "\n" . $line;

                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;
                    if ($currentTable !== null) {
                        $insertBuffer = $this->fixAttributeIdsInInsert($insertBuffer, $entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType);
                    }
                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $currentTable = null;
                    $tableBackendType = null;
                }
                continue;
            }

            $result[] = $line;
        }

        if (!empty($insertBuffer)) {
            if ($currentTable !== null) {
                $insertBuffer = $this->fixAttributeIdsInInsert($insertBuffer, $entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType);
            }
            $result[] = $insertBuffer;
        }

        return implode("\n", $result);
    }

    /**
     * Fix attribute IDs in a single INSERT statement's VALUES
     * Also removes rows where the attribute belongs to a different backend type table
     */
    private function fixAttributeIdsInInsert(string $sql, int $entityTypeId, int $attrIdIndex, int $valueIndex, string $tableBackendType): string
    {
        // Split SQL into header (INSERT INTO ... VALUES) and values section
        // This prevents us from accidentally modifying the column names
        if (!preg_match('/^(INSERT\s+INTO\s+.+?\s+VALUES\s*)/is', $sql, $headerMatch)) {
            return $sql;
        }

        $header = $headerMatch[1];
        $valuesSection = substr($sql, strlen($header));

        // Only process the VALUES section
        $processedValues = preg_replace_callback(
            '/\(([^()]+)\)(?=\s*[,;]|\s*$)/m',
            function ($match) use ($entityTypeId, $attrIdIndex, $valueIndex, $tableBackendType) {
                $values = $this->parseValueTuple($match[1]);

                if (!isset($values[$attrIdIndex])) {
                    return $match[0];
                }

                // Get the sample data's attribute_id from the value tuple
                $sampleAttrId = (int) trim($values[$attrIdIndex]);

                // Look up what attribute this ID should map to based on value patterns
                $rawValue = isset($values[$valueIndex]) ? trim($values[$valueIndex]) : 'NULL';

                // For varchar/text tables, check if the value should go to a different attribute
                if ($tableBackendType === 'varchar' || $tableBackendType === 'text') {
                    // First check: if this attribute_id maps to a static/datetime/int attribute, remove it
                    $targetBackendType = $this->getBackendTypeByAttributeId($entityTypeId, $sampleAttrId);
                    if ($targetBackendType !== null && !in_array($targetBackendType, ['varchar', 'text'], true)) {
                        // This value is in the wrong table (e.g., static/datetime/int attr in varchar table)
                        return '';
                    }

                    // Second check: detect correct attribute from value pattern and fix if needed
                    if ($rawValue !== 'NULL') {
                        $value = trim($rawValue, "'\"");
                        $detectedAttrCode = $this->detectAttributeCodeFromValue($value);

                        if ($detectedAttrCode !== null) {
                            // Get the correct attribute ID from the target database
                            $correctAttrId = $this->getAttributeId($entityTypeId, $detectedAttrCode);
                            $backendType = $this->getAttributeBackendType($entityTypeId, $detectedAttrCode);

                            if ($correctAttrId !== null) {
                                // Check if this attribute belongs in this table
                                if ($backendType !== $tableBackendType && !($tableBackendType === 'varchar' && $backendType === 'text') && !($tableBackendType === 'text' && $backendType === 'varchar')) {
                                    // Wrong table - remove this row
                                    return '';
                                }

                                // Replace with correct attribute ID
                                $values[$attrIdIndex] = (string) $correctAttrId;
                                return '(' . implode(', ', $values) . ')';
                            }
                        }
                    }
                }

                // For int/decimal tables, try to remap sample data attribute IDs to Maho attribute IDs
                if ($tableBackendType === 'int' || $tableBackendType === 'decimal') {
                    // Check if we have a mapping for this sample attribute ID
                    $attrCode = self::SAMPLE_DATA_INT_ATTR_MAPPING[$entityTypeId][$sampleAttrId] ?? null;

                    if ($attrCode !== null) {
                        // Get the correct attribute ID from the target database
                        $correctAttrId = $this->getAttributeId($entityTypeId, $attrCode);
                        $backendType = $this->getAttributeBackendType($entityTypeId, $attrCode);

                        if ($correctAttrId !== null) {
                            // Check if this attribute belongs in this table
                            if ($backendType !== null && $backendType !== $tableBackendType) {
                                // Wrong table - remove this row
                                return '';
                            }

                            // Replace with correct attribute ID
                            $values[$attrIdIndex] = (string) $correctAttrId;
                            return '(' . implode(', ', $values) . ')';
                        }
                    } else {
                        // No mapping found - check if the attribute's backend_type matches in target DB
                        $targetBackendType = $this->getBackendTypeByAttributeId($entityTypeId, $sampleAttrId);

                        // If the target attribute has a different backend type, remove this row
                        if ($targetBackendType !== null && $targetBackendType !== $tableBackendType) {
                            // This value is in the wrong table (e.g., static/varchar attr in int table)
                            return '';
                        }
                    }
                }

                return $match[0];
            },
            $valuesSection,
        ) ?? $valuesSection;

        // Clean up the SQL after removing rows
        // Remove empty entries and fix commas repeatedly until stable
        $previousValues = '';
        while ($previousValues !== $processedValues) {
            $previousValues = $processedValues;
            $processedValues = preg_replace('/,\s*,+/', ',', $processedValues); // Remove consecutive commas
            $processedValues = preg_replace('/,\s*;/', ';', $processedValues); // Remove trailing comma before semicolon
            $processedValues = preg_replace('/^\s*,/', '', $processedValues); // Remove leading comma
            $processedValues = preg_replace('/,\s*$/', '', rtrim($processedValues, "; \t\n\r")); // Remove trailing comma
        }

        // If all values were removed, skip the entire statement
        $trimmedValues = trim($processedValues, " \t\n\r;");
        if ($trimmedValues === '' || !preg_match('/\([^)]+\)/', $trimmedValues)) {
            // No valid value tuples remain
            return '';
        }

        // Re-add semicolon if needed
        if (!str_ends_with(rtrim($processedValues), ';')) {
            $processedValues = rtrim($processedValues) . ';';
        }

        return $header . $processedValues;
    }

    /**
     * Get the backend type for an attribute by its ID in the target database
     */
    private function getBackendTypeByAttributeId(int $entityTypeId, int $attributeId): ?string
    {
        foreach ($this->attributeMapping as $key => $data) {
            if (str_starts_with($key, $entityTypeId . '_') && $data['attribute_id'] === $attributeId) {
                return $data['backend_type'];
            }
        }
        return null;
    }

    /**
     * Convert 0/1 to FALSE/TRUE for boolean columns in INSERT statements
     */
    private function convertBooleanValues(string $sql): string
    {
        // Skip if no PDO connection for schema checking
        if ($this->pdo === null) {
            return $sql;
        }

        // Process line by line to avoid regex issues with large content
        $lines = explode("\n", $sql);
        $result = [];
        $inInsert = false;
        $insertBuffer = '';
        $columns = [];
        $booleanIndices = [];
        $tableName = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detect start of INSERT statement
            if (preg_match('/^INSERT\s+INTO\s+"?(\w+)"?\s*\(([^)]+)\)/i', $trimmedLine, $matches)) {
                // If we were already in an INSERT, save the previous one first
                if ($inInsert && !empty($insertBuffer)) {
                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }
                    $result[] = $insertBuffer;
                }

                $insertBuffer = $line;
                $tableName = $matches[1];

                // Parse column names
                $columns = array_map(function ($col) {
                    return trim(trim($col), '"');
                }, explode(',', $matches[2]));

                // Find which columns are actually boolean type in the database
                $booleanIndices = [];
                foreach ($columns as $index => $column) {
                    if ($this->isBooleanColumn($tableName, $column)) {
                        $booleanIndices[] = $index;
                    }
                }

                // Check if this INSERT ends on the same line (single-line INSERT)
                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;

                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }

                    // Add ON CONFLICT DO UPDATE for PostgreSQL upsert
                    $insertBuffer = $this->addOnConflictClause($insertBuffer, $tableName, $columns);

                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $columns = [];
                    $booleanIndices = [];
                    $tableName = '';
                } else {
                    $inInsert = true;
                }
                continue;
            }

            if ($inInsert) {
                $insertBuffer .= "\n" . $line;

                // Check for end of INSERT statement
                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;

                    // If there are boolean columns, convert values
                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }

                    // Add ON CONFLICT DO UPDATE for PostgreSQL upsert
                    $insertBuffer = $this->addOnConflictClause($insertBuffer, $tableName, $columns);

                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $columns = [];
                    $booleanIndices = [];
                    $tableName = '';
                }
                continue;
            }

            $result[] = $line;
        }

        // Handle case where INSERT didn't end with semicolon
        if (!empty($insertBuffer)) {
            if (!empty($booleanIndices)) {
                $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
            }
            $result[] = $insertBuffer;
        }

        return implode("\n", $result);
    }

    /**
     * Convert boolean values in an INSERT statement's VALUES
     */
    private function convertBooleanInInsert(string $sql, array $booleanIndices): string
    {
        // Find VALUES block and convert boolean values
        return preg_replace_callback(
            '/\(([^()]+)\)(?=\s*[,;]|\s*$)/m',
            function ($match) use ($booleanIndices) {
                $values = $this->parseValueTuple($match[1]);
                foreach ($booleanIndices as $idx) {
                    if (isset($values[$idx])) {
                        $val = trim($values[$idx]);
                        if ($val === '0') {
                            $values[$idx] = 'FALSE';
                        } elseif ($val === '1') {
                            $values[$idx] = 'TRUE';
                        }
                    }
                }
                return '(' . implode(', ', $values) . ')';
            },
            $sql,
        ) ?? $sql; // Return original if regex fails
    }

    /**
     * Check if a column is a boolean type by querying the database schema
     */
    private function isBooleanColumn(string $tableName, string $columnName): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        // Check cache first
        $cacheKey = strtolower($tableName);
        if (!isset($this->booleanColumnsCache[$cacheKey])) {
            $this->booleanColumnsCache[$cacheKey] = $this->getBooleanColumns($tableName);
        }

        return in_array(strtolower($columnName), $this->booleanColumnsCache[$cacheKey], true);
    }

    /**
     * Get list of boolean columns for a table
     */
    private function getBooleanColumns(string $tableName): array
    {
        if ($this->pdo === null) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_name = :table_name
                AND table_schema = 'public'
                AND data_type = 'boolean'
            ");
            $stmt->execute(['table_name' => $tableName]);
            return array_map('strtolower', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Add ON CONFLICT DO NOTHING clause to an INSERT statement for PostgreSQL
     *
     * Uses DO NOTHING instead of DO UPDATE because sample data may have
     * multiple unique constraints (not just primary keys), and we want to
     * safely skip any duplicates rather than trying to update.
     */
    private function addOnConflictClause(string $sql, string $tableName, array $columns): string
    {
        if ($this->pdo === null) {
            return $sql;
        }

        // For sample data import, use ON CONFLICT DO NOTHING to skip duplicates
        // This handles both primary key and unique constraint conflicts
        $onConflict = ' ON CONFLICT DO NOTHING';

        // Insert the ON CONFLICT clause before the final semicolon
        if (str_ends_with(rtrim($sql), ';')) {
            $sql = rtrim($sql);
            $sql = substr($sql, 0, -1) . $onConflict . ';';
        }

        return $sql;
    }

    /**
     * Parse a value tuple into individual values, respecting string literals
     */
    private function parseValueTuple(string $tuple): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;
        $parenDepth = 0;

        $length = strlen($tuple);
        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

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

            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($inString && $char === $stringChar) {
                $inString = false;
                $current .= $char;
                continue;
            }

            if (!$inString && $char === '(') {
                $parenDepth++;
                $current .= $char;
                continue;
            }

            if (!$inString && $char === ')') {
                $parenDepth--;
                $current .= $char;
                continue;
            }

            if (!$inString && $parenDepth === 0 && $char === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Don't forget the last value
        if ($current !== '' || !empty($values)) {
            $values[] = trim($current);
        }

        return $values;
    }

    /**
     * Convert backtick-quoted identifiers to double-quoted identifiers
     * Being careful not to convert backticks inside string literals
     */
    private function convertBackticksToDoubleQuotes(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($escaped) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $result .= $char;
                continue;
            }

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $result .= $char;
                continue;
            }

            if ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $result .= $char;
                continue;
            }

            // Only convert backticks when not inside a string literal
            if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $result .= '"';
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Execute SQL statements one by one, handling PostgreSQL-specific issues
     *
     * @param \PDO $pdo PostgreSQL PDO connection
     * @param string $sql SQL content to execute
     * @param callable|null $progressCallback Optional callback for progress updates
     * @throws \PDOException
     */
    public function executeStatements(\PDO $pdo, string $sql, ?callable $progressCallback = null): void
    {
        // Split SQL into individual statements
        $statements = $this->splitStatements($sql);
        $total = count($statements);
        $current = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                // Log the failing statement for debugging
                $shortStatement = substr($statement, 0, 100);
                throw new \PDOException(
                    "Failed to execute: {$shortStatement}... Error: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e,
                );
            }

            $current++;
            if ($progressCallback && $current % 100 === 0) {
                $progressCallback($current, $total);
            }
        }

        if ($progressCallback) {
            $progressCallback($total, $total);
        }
    }

    /**
     * Split SQL content into individual statements
     * Handles multi-line INSERT statements properly
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;

        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

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

            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($inString && $char === $stringChar) {
                $inString = false;
                $current .= $char;
                continue;
            }

            if (!$inString && $char === ';') {
                $statement = trim($current);
                if (!empty($statement)) {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Don't forget the last statement if it doesn't end with semicolon
        $statement = trim($current);
        if (!empty($statement)) {
            $statements[] = $statement;
        }

        return $statements;
    }
}
