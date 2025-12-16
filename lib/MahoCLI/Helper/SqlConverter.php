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
 * Simple SQL Converter for transforming MySQL SQL to PostgreSQL-compatible SQL
 *
 * This converter handles the basic differences between MySQL and PostgreSQL:
 * - Removes MySQL-specific comments and settings
 * - Converts backticks to double quotes for identifiers
 * - Converts boolean values (0/1) to PostgreSQL booleans (FALSE/TRUE)
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
     * Set PDO connection for schema checking
     */
    public function setPdo(\PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    /**
     * Convert MySQL SQL content to SQLite-compatible SQL
     */
    public function mysqlToSqlite(string $mysqlSql): string
    {
        // Remove MySQL-specific comments (/*!40101 SET ... */)
        $sql = preg_replace('/\/\*!\d+.*?\*\/;?\s*/s', '', $mysqlSql);

        // Remove LOCK/UNLOCK TABLE statements
        $sql = preg_replace('/\bLOCK TABLES.*?;\s*/i', '', $sql);
        $sql = preg_replace('/\bUNLOCK TABLES;\s*/i', '', $sql);

        // Remove MySQL SET statements
        $sql = preg_replace('/SET\s+@\w+\s*=.*?;\s*/i', '', $sql);
        $sql = preg_replace('/SET\s+(NAMES|TIME_ZONE|SQL_MODE|FOREIGN_KEY_CHECKS|UNIQUE_CHECKS|SQL_NOTES|SESSION|GLOBAL|@@)\s*[^;]*;\s*/i', '', $sql);

        // Convert REPLACE INTO to INSERT OR REPLACE INTO for SQLite
        $sql = preg_replace('/\bREPLACE\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql);

        // Convert MySQL hex literals (X'...' or 0x...) to SQLite integers
        $sql = preg_replace_callback(
            "/X'([0-9A-Fa-f]+)'/",
            fn($m) => (string) (strlen($m[1]) <= 15 ? hexdec($m[1]) : 0),
            $sql,
        );
        $sql = preg_replace_callback(
            '/\b0x([0-9A-Fa-f]+)\b/',
            fn($m) => (string) (strlen($m[1]) <= 15 ? hexdec($m[1]) : 0),
            $sql,
        );

        // Replace backticks with double quotes for identifiers
        $sql = $this->convertBackticksToDoubleQuotes($sql);

        // Convert MySQL escape sequences inside string literals
        $sql = $this->convertEscapeSequences($sql);

        // SQLite uses integers (0/1) for booleans, so no conversion needed

        return $sql;
    }

    /**
     * Convert MySQL SQL content to PostgreSQL-compatible SQL
     */
    public function mysqlToPostgresql(string $mysqlSql): string
    {
        // Remove MySQL-specific comments (/*!40101 SET ... */)
        $sql = preg_replace('/\/\*!\d+.*?\*\/;?\s*/s', '', $mysqlSql);

        // Remove LOCK/UNLOCK TABLE statements
        $sql = preg_replace('/\bLOCK TABLES.*?;\s*/i', '', $sql);
        $sql = preg_replace('/\bUNLOCK TABLES;\s*/i', '', $sql);

        // Remove MySQL SET statements
        $sql = preg_replace('/SET\s+@\w+\s*=.*?;\s*/i', '', $sql);
        $sql = preg_replace('/SET\s+(NAMES|TIME_ZONE|SQL_MODE|FOREIGN_KEY_CHECKS|UNIQUE_CHECKS|SQL_NOTES|SESSION|GLOBAL|@@)\s*[^;]*;\s*/i', '', $sql);

        // Convert REPLACE INTO to INSERT INTO (PostgreSQL doesn't have REPLACE)
        $sql = preg_replace('/\bREPLACE\s+INTO\b/i', 'INSERT INTO', $sql);

        // Convert MySQL hex literals (X'...' or 0x...) to PostgreSQL integers
        // MySQL uses hex for integers, PostgreSQL treats X'...' as bit strings
        $sql = preg_replace_callback(
            "/X'([0-9A-Fa-f]+)'/",
            fn($m) => (string) (strlen($m[1]) <= 15 ? hexdec($m[1]) : 0),
            $sql,
        );
        $sql = preg_replace_callback(
            '/\b0x([0-9A-Fa-f]+)\b/',
            fn($m) => (string) (strlen($m[1]) <= 15 ? hexdec($m[1]) : 0),
            $sql,
        );

        // Replace backticks with double quotes for identifiers
        $sql = $this->convertBackticksToDoubleQuotes($sql);

        // Convert MySQL escape sequences inside string literals
        // MySQL interprets \n, \t, \r as actual newline/tab/carriage return
        // PostgreSQL with standard_conforming_strings treats them as literal characters
        $sql = $this->convertEscapeSequences($sql);

        // Convert boolean values in INSERT statements
        $sql = $this->convertBooleanValues($sql);

        return $sql;
    }

    /**
     * Convert MySQL escape sequences inside string literals to actual characters
     * MySQL interprets \n, \t, \r, \', \\ inside strings, PostgreSQL doesn't
     */
    private function convertEscapeSequences(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $inString = false;
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];

            // Track when we enter/exit single-quoted strings
            if ($char === "'" && !$inString) {
                $inString = true;
                $result .= $char;
                $i++;
                continue;
            }

            if ($inString && $char === "'") {
                // Check for escaped quote ('' in the result so far means it was converted)
                // Or end of string
                if ($i + 1 < $length && $sql[$i + 1] === "'") {
                    // Double quote - keep it and skip both
                    $result .= "''";
                    $i += 2;
                    continue;
                }
                // End of string
                $inString = false;
                $result .= $char;
                $i++;
                continue;
            }

            // Convert escape sequences only inside strings
            if ($inString && $char === '\\' && $i + 1 < $length) {
                $nextChar = $sql[$i + 1];
                switch ($nextChar) {
                    case 'n':
                        $result .= "\n";
                        $i += 2;
                        continue 2;
                    case 't':
                        $result .= "\t";
                        $i += 2;
                        continue 2;
                    case 'r':
                        $result .= "\r";
                        $i += 2;
                        continue 2;
                    case '\\':
                        $result .= '\\';
                        $i += 2;
                        continue 2;
                    case "'":
                        // Convert \' to '' for PostgreSQL
                        $result .= "''";
                        $i += 2;
                        continue 2;
                    case '"':
                        // Convert \" to just "
                        $result .= '"';
                        $i += 2;
                        continue 2;
                    default:
                        // Keep other escapes as-is
                        $result .= $char;
                        $i++;
                        continue 2;
                }
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * Convert backtick-quoted identifiers to double-quoted identifiers
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

            // Convert backticks to double quotes when not inside a string
            if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $result .= '"';
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Convert 0/1 to FALSE/TRUE for boolean columns in INSERT statements
     */
    private function convertBooleanValues(string $sql): string
    {
        if ($this->pdo === null) {
            return $sql;
        }

        $lines = explode("\n", $sql);
        $result = [];
        $inInsert = false;
        $insertBuffer = '';
        $booleanIndices = [];
        $tableName = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detect start of INSERT statement
            if (preg_match('/^INSERT\s+INTO\s+"?(\w+)"?\s*\(([^)]+)\)/i', $trimmedLine, $matches)) {
                if ($inInsert && !empty($insertBuffer)) {
                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }
                    $result[] = $insertBuffer;
                }

                $insertBuffer = $line;
                $tableName = $matches[1];

                // Parse column names and find boolean indices
                $columns = array_map(fn($col) => trim(trim($col), '"'), explode(',', $matches[2]));
                $booleanIndices = [];
                foreach ($columns as $index => $column) {
                    if ($this->isBooleanColumn($tableName, $column)) {
                        $booleanIndices[] = $index;
                    }
                }

                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;
                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }
                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $booleanIndices = [];
                } else {
                    $inInsert = true;
                }
                continue;
            }

            if ($inInsert) {
                $insertBuffer .= "\n" . $line;

                if (str_ends_with($trimmedLine, ';')) {
                    $inInsert = false;
                    if (!empty($booleanIndices)) {
                        $insertBuffer = $this->convertBooleanInInsert($insertBuffer, $booleanIndices);
                    }
                    $result[] = $insertBuffer;
                    $insertBuffer = '';
                    $booleanIndices = [];
                }
                continue;
            }

            $result[] = $line;
        }

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
        ) ?? $sql;
    }

    /**
     * Check if a column is a boolean type
     */
    private function isBooleanColumn(string $tableName, string $columnName): bool
    {
        if ($this->pdo === null) {
            return false;
        }

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

        if ($current !== '' || !empty($values)) {
            $values[] = trim($current);
        }

        return $values;
    }

    /**
     * Tables that should use ON CONFLICT DO UPDATE instead of DO NOTHING
     * These are content tables where sample data should replace default content
     * Uses primary key or unique constraint columns for conflict detection
     *
     * conflict_columns: Column(s) that form the unique constraint (can be string or array)
     * update_columns: Columns to update on conflict
     */
    private const UPSERT_TABLES = [
        'cms_page' => ['conflict_columns' => 'page_id', 'update_columns' => ['title', 'root_template', 'meta_keywords', 'meta_description', 'identifier', 'content_heading', 'content', 'is_active', 'sort_order', 'layout_update_xml', 'custom_theme', 'custom_root_template', 'custom_layout_update_xml', 'custom_theme_from', 'custom_theme_to']],
        'cms_block' => ['conflict_columns' => 'block_id', 'update_columns' => ['title', 'identifier', 'content', 'is_active']],
        'core_config_data' => ['conflict_columns' => ['scope', 'scope_id', 'path'], 'update_columns' => ['value', 'updated_at']],
        'permission_block' => ['conflict_columns' => 'block_name', 'update_columns' => ['is_allowed']],
        'permission_variable' => ['conflict_columns' => 'variable_name', 'update_columns' => ['is_allowed']],
    ];

    /**
     * Execute SQL statements one by one
     */
    public function executeStatements(\PDO $pdo, string $sql, ?callable $progressCallback = null): void
    {
        $statements = $this->splitStatements($sql);
        $total = count($statements);
        $current = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            // Add ON CONFLICT handling to INSERT statements
            if (preg_match('/^\s*INSERT\s+INTO\s+/i', $statement)) {
                $statement = $this->addConflictHandling($statement);
            }

            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
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
     * Add appropriate ON CONFLICT handling to an INSERT statement
     */
    private function addConflictHandling(string $statement): string
    {
        // Extract table name from INSERT INTO "table_name" or INSERT INTO table_name
        if (!preg_match('/INSERT\s+INTO\s+"?(\w+)"?\s*/i', $statement, $matches)) {
            return $statement . ' ON CONFLICT DO NOTHING';
        }

        $tableName = $matches[1];

        // Check if this table needs upsert behavior
        if (!isset(self::UPSERT_TABLES[$tableName])) {
            return $statement . ' ON CONFLICT DO NOTHING';
        }

        $config = self::UPSERT_TABLES[$tableName];
        $conflictColumns = $config['conflict_columns'];
        $updateColumns = $config['update_columns'];

        // Handle single column or array of columns
        if (is_array($conflictColumns)) {
            $conflictClause = implode('", "', $conflictColumns);
        } else {
            $conflictClause = $conflictColumns;
        }

        // Build the DO UPDATE SET clause
        $updateParts = [];
        foreach ($updateColumns as $col) {
            $updateParts[] = "\"{$col}\" = EXCLUDED.\"{$col}\"";
        }

        $updateClause = implode(', ', $updateParts);

        return $statement . " ON CONFLICT (\"{$conflictClause}\") DO UPDATE SET {$updateClause}";
    }

    /**
     * Split SQL content into individual statements
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

        $statement = trim($current);
        if (!empty($statement)) {
            $statements[] = $statement;
        }

        return $statements;
    }
}
