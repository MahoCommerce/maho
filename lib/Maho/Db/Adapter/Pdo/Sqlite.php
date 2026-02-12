<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter\Pdo;

use Maho\Db\Adapter\AbstractPdoAdapter;
use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Helper;

class Sqlite extends AbstractPdoAdapter
{
    // SQLite-specific constants
    public const DDL_CACHE_PREFIX = 'DB_PDO_SQLITE_DDL';
    public const DDL_CACHE_TAG = 'DB_PDO_SQLITE_DDL';

    /**
     * Default class name for a DB statement.
     */
    protected string $_defaultStmtClass = \Maho\Db\Statement\Pdo\Sqlite::class;

    /**
     * Log file name for SQL debug data (override parent's default)
     */
    protected string $_debugFile = 'pdo_sqlite.log';

    /**
     * SQLite column - Table DDL type pairs
     *
     * SQLite uses dynamic typing with type affinity. These mappings
     * provide reasonable defaults while respecting SQLite's flexibility.
     */
    protected array $_ddlColumnTypes = [
        \Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'INTEGER',
        \Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'INTEGER',
        \Maho\Db\Ddl\Table::TYPE_INTEGER       => 'INTEGER',
        \Maho\Db\Ddl\Table::TYPE_BIGINT        => 'INTEGER',
        \Maho\Db\Ddl\Table::TYPE_FLOAT         => 'REAL',
        \Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'NUMERIC',
        \Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'NUMERIC',
        \Maho\Db\Ddl\Table::TYPE_DATE          => 'TEXT',
        \Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'TEXT',
        \Maho\Db\Ddl\Table::TYPE_DATETIME      => 'TEXT',
        \Maho\Db\Ddl\Table::TYPE_TEXT          => 'TEXT',
        \Maho\Db\Ddl\Table::TYPE_VARCHAR       => 'TEXT',
        \Maho\Db\Ddl\Table::TYPE_BLOB          => 'BLOB',
        \Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'BLOB',
    ];

    /**
     * SQLite interval units mapping (for strftime)
     */
    protected array $_intervalUnits = [
        self::INTERVAL_SECOND => 'seconds',
        self::INTERVAL_MINUTE => 'minutes',
        self::INTERVAL_HOUR   => 'hours',
        self::INTERVAL_DAY    => 'days',
        self::INTERVAL_MONTH  => 'months',
        self::INTERVAL_YEAR   => 'years',
    ];

    // =========================================================================
    // Abstract Method Implementations from AbstractPdoAdapter
    // =========================================================================

    /**
     * Get the driver name for this adapter
     */
    #[\Override]
    protected function getDriverName(): string
    {
        return 'pdo_sqlite';
    }

    /**
     * Get the identifier quote character for SQLite (double quote)
     */
    #[\Override]
    protected function getIdentifierQuoteChar(): string
    {
        return '"';
    }

    /**
     * Run SQLite-specific initialization statements after connection
     */
    #[\Override]
    protected function _initConnection(): void
    {
        // Enable foreign key constraints (disabled by default in SQLite)
        $this->_connection->executeStatement('PRAGMA foreign_keys = ON');

        // Enable Write-Ahead Logging for better concurrency
        $this->_connection->executeStatement('PRAGMA journal_mode = WAL');

        // Increase cache size for better performance (negative = KB, positive = pages)
        $this->_connection->executeStatement('PRAGMA cache_size = -64000'); // 64MB

        // Improve write performance (with acceptable durability trade-off)
        $this->_connection->executeStatement('PRAGMA synchronous = NORMAL');

        // Use memory-mapped I/O for better read performance
        $this->_connection->executeStatement('PRAGMA mmap_size = 268435456'); // 256MB

        // Store temp tables in memory
        $this->_connection->executeStatement('PRAGMA temp_store = MEMORY');

        // Register custom REGEXP function for SQLite (required for REGEXP queries)
        $this->_registerCustomFunctions();
    }

    /**
     * Register custom SQLite functions for compatibility with MySQL/PostgreSQL
     */
    protected function _registerCustomFunctions(): void
    {
        $pdo = $this->_connection->getNativeConnection();

        // REGEXP function - uses PHP's preg_match for regex matching
        // Usage in SQL: column REGEXP 'pattern'
        $this->_createSqliteFunction($pdo, 'REGEXP', function ($pattern, $value) {
            if ($pattern === null || $value === null) {
                return null;
            }
            // Convert MySQL-style regex to PCRE
            // MySQL REGEXP is case-insensitive by default, so we add 'i' modifier
            return (int) @preg_match('/' . str_replace('/', '\\/', $pattern) . '/i', (string) $value);
        }, 2);

        // GREATEST function - returns the largest value from a list of arguments
        // SQLite doesn't have GREATEST(), so we implement it using MAX() scalar behavior
        $this->_createSqliteFunction($pdo, 'GREATEST', function (...$args) {
            $args = array_filter($args, fn($v) => $v !== null);
            if (empty($args)) {
                return null;
            }
            return max($args);
        }, -1); // -1 means variadic number of arguments

        // LEAST function - returns the smallest value from a list of arguments
        // SQLite's built-in MIN() is aggregate, so we need scalar version
        $this->_createSqliteFunction($pdo, 'LEAST', function (...$args) {
            $args = array_filter($args, fn($v) => $v !== null);
            if (empty($args)) {
                return null;
            }
            return min($args);
        }, -1);
    }

    /**
     * Create a SQLite user-defined function
     * Uses Pdo\Sqlite::createFunction() in PHP 8.5+ (non-deprecated method)
     */
    protected function _createSqliteFunction(\PDO $pdo, string $name, callable $callback, int $numArgs): void
    {
        // PHP 8.5+ has Pdo\Sqlite with createFunction(), older versions use PDO::sqliteCreateFunction()
        if (PHP_VERSION_ID >= 80500 && method_exists($pdo, 'createFunction')) {
            $pdo->createFunction($name, $callback, $numArgs);
        } else {
            $pdo->sqliteCreateFunction($name, $callback, $numArgs);
        }
    }

    /**
     * Get DDL cache prefix for SQLite
     */
    #[\Override]
    protected function getDdlCachePrefix(): string
    {
        return self::DDL_CACHE_PREFIX;
    }

    /**
     * Get DDL cache tag for SQLite
     */
    #[\Override]
    protected function getDdlCacheTag(): string
    {
        return self::DDL_CACHE_TAG;
    }

    // =========================================================================
    // SQLite-Specific Connection Methods
    // =========================================================================

    /**
     * Creates a PDO object and connects to the database.
     *
     * @throws \RuntimeException
     */
    #[\Override]
    protected function _connect(): void
    {
        if ($this->_connection) {
            return;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException('pdo_sqlite extension is not installed');
        }

        $this->_debugTimer();

        // SQLite uses file path instead of host/user/password
        $path = $this->_config['path'] ?? $this->_config['dbname'] ?? ':memory:';

        // Handle in-memory database
        if ($path === ':memory:' || empty($path)) {
            $params = [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ];
        } else {
            // If path is not absolute, store in var/db/ directory
            if ($path[0] !== '/' && !str_contains($path, ':')) {
                $baseDir = defined('BP') ? BP : getcwd();
                $dbDir = $baseDir . '/var/db';

                // Ensure the directory exists
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $path = $dbDir . '/' . $path;
            }

            $params = [
                'driver' => 'pdo_sqlite',
                'path' => $path,
            ];
        }

        $this->_connection = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_debugStat(self::DEBUG_CONNECT, '');

        $this->_initConnection();

        $this->_connectionFlagsSet = true;
    }

    /**
     * Run RAW Query
     *
     * @throws \PDOException
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function raw_query(string $sql): \Maho\Db\Statement\Pdo\Sqlite
    {
        $tries = 0;
        do {
            $retry = false;
            try {
                $result = $this->query($sql);
            } catch (\Exception $e) {
                // Convert to PDOException to maintain backwards compatibility
                if ($e instanceof \RuntimeException) {
                    $e = $e->getPrevious();
                    if (!($e instanceof \PDOException)) {
                        $e = new \PDOException($e->getMessage(), (int) $e->getCode());
                    }
                }
                // Check for SQLite busy/locked errors (database is locked)
                if ($tries < 10 && (
                    str_contains($e->getMessage(), 'database is locked') ||
                    str_contains($e->getMessage(), 'database table is locked')
                )) {
                    $retry = true;
                    $tries++;
                    usleep(100000); // Wait 100ms before retry
                } else {
                    throw $e;
                }
            }
        } while ($retry);

        return $result;
    }

    /**
     * Run RAW query and Fetch First row
     *
     * @param string|int $field
     * @return array|string|int|false
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function raw_fetchRow(string $sql, string|int|null $field = null): array|string|int|false
    {
        $result = $this->raw_query($sql);
        $row = $result->getResult()->fetchAssociative();
        if (!$row) {
            return false;
        }

        if (empty($field)) {
            return $row;
        }
        return $row[$field] ?? false;
    }

    /**
     * Special handling for PDO query().
     *
     * @throws \RuntimeException To re-throw PDOException.
     */
    #[\Override]
    public function query(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): \Maho\Db\Statement\Pdo\Sqlite
    {
        $this->_debugTimer();
        try {
            $this->_checkDdlTransaction($sql);
            $this->_prepareQuery($sql, $bind);

            // Connect if not already connected
            $this->_connect();

            // Execute query using Doctrine DBAL
            if (!empty($bind)) {
                $result = $this->_connection->executeQuery($sql, $bind);
            } else {
                $result = $this->_connection->executeQuery($sql);
            }

            // Wrap the result in statement class for compatibility
            $result = new \Maho\Db\Statement\Pdo\Sqlite($this, $result);
        } catch (\Exception $e) {
            $this->_debugStat(self::DEBUG_QUERY, $sql, $bind);

            // Detect implicit rollback - SQLite constraint violation
            if ($this->_transactionLevel > 0
                && str_contains($e->getMessage(), 'constraint failed')
            ) {
                if ($this->_debug) {
                    $this->_debugWriteToFile('IMPLICIT ROLLBACK AFTER CONSTRAINT VIOLATION');
                }
                $this->_transactionLevel = 1;
                $this->rollBack();
            }

            $this->_debugException($e);
        }
        $this->_debugStat(self::DEBUG_QUERY, $sql, $bind, $result);
        return $result;
    }

    /**
     * Prepares SQL query by normalizing bind parameters
     *
     * @param \Maho\Db\Select|string $sql
     * @param mixed $bind
     * @param-out string $sql
     * @return $this
     */
    protected function _prepareQuery(&$sql, &$bind = [])
    {
        $sql = (string) $sql;
        if (!is_array($bind)) {
            $bind = [$bind];
        }

        // Convert named bind to positional
        $isNamedBind = false;
        if ($bind) {
            foreach ($bind as $k => $v) {
                if (!is_int($k)) {
                    $isNamedBind = true;
                    if ($k[0] != ':') {
                        $bind[":{$k}"] = $v;
                        unset($bind[$k]);
                    }
                }
            }
        }

        if ($isNamedBind) {
            $this->_convertMixedBind($sql, $bind);
        }

        // Convert DateTime and Expr objects to strings
        foreach ($bind as $k => $v) {
            if ($v instanceof \DateTime) {
                $bind[$k] = $v->format('Y-m-d H:i:s');
            } elseif ($v instanceof \Maho\Db\Expr) {
                $exprValue = (string) $v;
                $bind[$k] = trim($exprValue, "'\"");
            }
        }

        // Special query hook
        if ($this->_queryHook) {
            $object = $this->_queryHook['object'];
            $method = $this->_queryHook['method'];
            $object->$method($sql, $bind);
        }

        return $this;
    }

    /**
     * Normalizes mixed positional-named bind to positional bind
     *
     * @param string $sql
     * @param array $bind
     * @return $this
     */
    protected function _convertMixedBind(&$sql, &$bind)
    {
        $positions = [];
        $offset = 0;
        while (true) {
            $pos = strpos($sql, '?', $offset);
            if ($pos !== false) {
                $positions[] = $pos;
                $offset = ++$pos;
            } else {
                break;
            }
        }

        $bindResult = [];
        $map = [];
        foreach ($bind as $k => $v) {
            if (is_int($k)) {
                if (!isset($positions[$k])) {
                    continue;
                }
                $bindResult[$positions[$k]] = $v;
            } else {
                $offset = 0;
                while (true) {
                    $pos = strpos($sql, $k, $offset);
                    if ($pos === false) {
                        break;
                    } else {
                        $offset = $pos + strlen($k);
                        $bindResult[$pos] = $v;
                    }
                }
                $map[$k] = '?';
            }
        }

        ksort($bindResult);
        $bind = array_values($bindResult);
        $sql = strtr($sql, $map);

        return $this;
    }

    /**
     * Sets (removes) query hook.
     *
     * @param array|null $hook
     * @return mixed
     */
    public function setQueryHook($hook)
    {
        $prev = $this->_queryHook;
        $this->_queryHook = $hook;
        return $prev;
    }

    // =========================================================================
    // SQL Generation Methods - SQLite-specific implementations
    // =========================================================================

    /**
     * Quote a value for safe use in SQL - SQLite-specific implementation
     *
     * SQLite has strict type affinity in certain contexts (like CASE WHEN expressions).
     * When comparing CASE WHEN results (which preserve the original column type) against
     * quoted values, type mismatches can cause comparisons to fail.
     *
     * This override ensures integers are not quoted as strings, allowing proper
     * comparisons in SQLite's type system.
     */
    #[\Override]
    public function quote(\Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value, null|string|int $type = null): string
    {
        // Handle integers without quoting for SQLite's strict type comparisons
        if (is_int($value) || (is_numeric($value) && !str_contains((string) $value, '.') && !str_contains((string) $value, 'e'))) {
            return (string) (int) $value;
        }

        // Handle floats without quoting
        if (is_float($value)) {
            return (string) $value;
        }

        return parent::quote($value, $type);
    }

    /**
     * Generate fragment of SQL, that check condition and return true or false value
     * Uses CASE WHEN (standard SQL, works in SQLite)
     */
    #[\Override]
    public function getCheckSql(\Maho\Db\Expr|\Maho\Db\Select|string $expression, \Maho\Db\Expr|string $true, \Maho\Db\Expr|string $false): \Maho\Db\Expr
    {
        if ($expression instanceof \Maho\Db\Expr || $expression instanceof \Maho\Db\Select) {
            $expression = sprintf('CASE WHEN (%s) THEN %s ELSE %s END', $expression, $true, $false);
        } else {
            $expression = sprintf('CASE WHEN %s THEN %s ELSE %s END', $expression, $true, $false);
        }

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Returns valid COALESCE expression (SQLite supports COALESCE and IFNULL)
     */
    #[\Override]
    public function getIfNullSql(\Maho\Db\Expr|\Maho\Db\Select|string $expression, string|int $value = '0'): \Maho\Db\Expr
    {
        if ($expression instanceof \Maho\Db\Expr || $expression instanceof \Maho\Db\Select) {
            $expression = sprintf('COALESCE((%s), %s)', $expression, $value);
        } else {
            $expression = sprintf('COALESCE(%s, %s)', $expression, $value);
        }

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Generate fragment of SQL for rounding a numeric value to specified precision.
     */
    #[\Override]
    public function getRoundSql(string $expression, int $precision = 0): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('ROUND(%s, %d)', $expression, $precision));
    }

    /**
     * Generate fragment of SQL to cast a value to text for comparison.
     */
    #[\Override]
    public function getCastToTextSql(string $expression): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('CAST(%s AS TEXT)', $expression));
    }

    /**
     * Generate fragment of SQL, that check value against multiple condition cases
     */
    #[\Override]
    public function getCaseSql(string $valueName, array $casesResults, ?string $defaultValue = null): \Maho\Db\Expr
    {
        $expression = 'CASE ' . $valueName;
        foreach ($casesResults as $case => $result) {
            $expression .= ' WHEN ' . $case . ' THEN ' . $result;
        }
        if ($defaultValue !== null) {
            $expression .= ' ELSE ' . $defaultValue;
        }
        $expression .= ' END';

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Generate fragment of SQL for concatenation with separator
     * SQLite doesn't have CONCAT_WS, use nested replace with || operator
     */
    #[\Override]
    protected function getConcatWithSeparatorSql(array $data, string $separator): \Maho\Db\Expr
    {
        $parts = [];
        foreach ($data as $i => $item) {
            if ($i > 0) {
                $parts[] = "'{$separator}'";
            }
            $parts[] = $item;
        }
        return new \Maho\Db\Expr('(' . implode(' || ', $parts) . ')');
    }

    /**
     * Generate LEAST SQL (SQLite uses MIN())
     */
    #[\Override]
    public function getLeastSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('MIN(%s)', implode(', ', $data)));
    }

    /**
     * Generate GREATEST SQL (SQLite uses MAX())
     */
    #[\Override]
    public function getGreatestSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('MAX(%s)', implode(', ', $data)));
    }

    /**
     * Get SQLite interval modifier string
     */
    protected function _getIntervalModifier(int|string $interval, string $unit): string
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        return sprintf('%+d %s', (int) $interval, $this->_intervalUnits[$unit]);
    }

    /**
     * Add time values (intervals) to a date value
     * SQLite uses datetime() with modifiers
     */
    #[\Override]
    public function getDateAddSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
    {
        $modifier = $this->_getIntervalModifier((int) $interval, $unit);
        $expr = sprintf("DATE(%s, '%s')", $date, $modifier);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Subtract time values (intervals) from a date value
     */
    #[\Override]
    public function getDateSubSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
    {
        $modifier = $this->_getIntervalModifier(-1 * (int) $interval, $unit);
        $expr = sprintf("DATE(%s, '%s')", $date, $modifier);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Format date using strftime (SQLite's date formatting function)
     *
     * Converts MySQL format specifiers to SQLite strftime format:
     * %H -> %H   Hour (00..23)
     * %i -> %M   Minutes (00..59) - note: SQLite uses %M for minutes
     * %s -> %S   Seconds (00..59)
     * %d -> %d   Day of month (01..31)
     * %m -> %m   Month (01..12)
     * %Y -> %Y   Year, four digits
     */
    #[\Override]
    public function getDateFormatSql(\Maho\Db\Expr|string $date, string $format): \Maho\Db\Expr
    {
        // Convert MySQL format to SQLite strftime format
        $sqliteFormat = str_replace(
            ['%i'],
            ['%M'],
            $format,
        );

        $expr = sprintf("STRFTIME('%s', %s)", $sqliteFormat, $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Get SQL expression for days until next annual occurrence of a date
     *
     * Uses SQLite's date functions to calculate days until anniversary.
     * Handles leap year birthdays (Feb 29) by using Feb 28 in non-leap years.
     *
     * @param \Maho\Db\Expr|string $dateField The date field containing the anniversary
     * @param string $referenceDate The reference date (usually today)
     */
    #[\Override]
    public function getDaysUntilAnniversarySql(\Maho\Db\Expr|string $dateField, string $referenceDate): \Maho\Db\Expr
    {
        $refDate = $this->quote($referenceDate);

        // Build the anniversary date for this year
        $currentYearAnniversary = "DATE(STRFTIME('%Y', {$refDate}) || '-' || STRFTIME('%m-%d', {$dateField}))";
        $nextYearAnniversary = "DATE(STRFTIME('%Y', {$refDate}, '+1 year') || '-' || STRFTIME('%m-%d', {$dateField}))";

        // Normalize reference date to start of day to compare dates only, not times
        // This ensures that comparing 12-16 22:24 to 12-17 gives 1 day, not 0
        $refDateOnly = "DATE({$refDate})";

        $sql = "CASE
            WHEN JULIANDAY({$currentYearAnniversary}) >= JULIANDAY({$refDateOnly}) THEN
                CAST(JULIANDAY({$currentYearAnniversary}) - JULIANDAY({$refDateOnly}) AS INTEGER)
            ELSE
                CAST(JULIANDAY({$nextYearAnniversary}) - JULIANDAY({$refDateOnly}) AS INTEGER)
        END";

        return new \Maho\Db\Expr($sql);
    }

    /**
     * Extract the date part of a date or datetime expression
     */
    #[\Override]
    public function getDatePartSql(\Maho\Db\Expr|string $date): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('DATE(%s)', $date));
    }

    /**
     * Prepare standard deviation sql function
     * Note: SQLite doesn't have built-in STDDEV. This returns 0 as a fallback.
     * For proper std dev support, you would need to use an extension or calculate manually.
     */
    #[\Override]
    public function getStandardDeviationSql(\Maho\Db\Expr|string $expressionField): \Maho\Db\Expr
    {
        // SQLite doesn't have built-in standard deviation
        // Return a subquery that calculates it manually
        // For simplicity, return 0 - proper implementation would require extension
        return new \Maho\Db\Expr('0');
    }

    /**
     * Extract part of a date
     */
    #[\Override]
    public function getDateExtractSql(\Maho\Db\Expr|string $date, string $unit): \Maho\Db\Expr
    {
        $formatMap = [
            self::INTERVAL_YEAR   => '%Y',
            self::INTERVAL_MONTH  => '%m',
            self::INTERVAL_DAY    => '%d',
            self::INTERVAL_HOUR   => '%H',
            self::INTERVAL_MINUTE => '%M',
            self::INTERVAL_SECOND => '%S',
        ];

        if (!isset($formatMap[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        $expr = sprintf("CAST(STRFTIME('%s', %s) AS INTEGER)", $formatMap[$unit], $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Convert date format to unix timestamp
     */
    #[\Override]
    public function getUnixTimestamp(string|\Maho\Db\Expr $date): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf("CAST(STRFTIME('%%s', %s) AS INTEGER)", $date));
    }

    /**
     * Convert unix time to date format
     */
    #[\Override]
    public function fromUnixtime(int|\Maho\Db\Expr $timestamp): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf("DATETIME(%s, 'unixepoch')", $timestamp));
    }

    /**
     * Get SQL expression for timestamp difference in seconds
     *
     * Returns the difference between two timestamps in seconds (end - start).
     */
    #[\Override]
    public function getTimestampDiffExpr(string $startTimestamp, string $endTimestamp): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf(
            'CAST((JULIANDAY(%s) - JULIANDAY(%s)) * 86400 AS INTEGER)',
            $endTimestamp,
            $startTimestamp,
        ));
    }

    /**
     * Get SQL expression for concatenating grouped values
     * SQLite has GROUP_CONCAT built-in
     */
    #[\Override]
    public function getGroupConcatExpr(string $expression, string $separator = ','): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf("GROUP_CONCAT(%s, '%s')", $expression, $separator));
    }

    /**
     * Get SQL expression for FIND_IN_SET functionality
     * SQLite doesn't have FIND_IN_SET, simulate with INSTR and string manipulation
     */
    #[\Override]
    public function getFindInSetExpr(string $needle, string $haystack): \Maho\Db\Expr
    {
        // Check if needle appears in comma-separated haystack
        // Uses a trick: surround both with commas to handle edge cases
        return new \Maho\Db\Expr(sprintf(
            "(INSTR(',' || %s || ',', ',' || %s || ',') > 0)",
            $haystack,
            $needle,
        ));
    }

    /**
     * Get SQL expression for Unix timestamp
     * SQLite uses strftime('%s', ...) to get unix timestamp
     */
    #[\Override]
    public function getUnixTimestampExpr(?string $timestamp = null): \Maho\Db\Expr
    {
        if ($timestamp === null) {
            return new \Maho\Db\Expr("CAST(STRFTIME('%s', 'now') AS INTEGER)");
        }
        return new \Maho\Db\Expr(sprintf("CAST(STRFTIME('%%s', %s) AS INTEGER)", $timestamp));
    }

    /**
     * Extract a scalar value from a JSON column at a given path (SQLite)
     */
    #[\Override]
    public function getJsonExtractExpr(string $column, string $path): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf(
            'JSON_EXTRACT(%s, %s)',
            $column,
            $this->quote($path),
        ));
    }

    /**
     * Search for a string value within a JSON column (SQLite)
     */
    #[\Override]
    public function getJsonSearchExpr(string $column, string $value, string $path): \Maho\Db\Expr
    {
        if (str_contains($path, '$**')) {
            // Extract the key name from the wildcard path (e.g., '$**.attribute' -> 'attribute')
            $lastDot = strrpos($path, '.');
            $key = $lastDot !== false ? substr($path, $lastDot + 1) : ltrim($path, '$*.');

            return new \Maho\Db\Expr(sprintf(
                'EXISTS(SELECT 1 FROM JSON_TREE(%s) WHERE "key" = %s AND "value" = %s AND "type" != %s)',
                $column,
                $this->quote($key),
                $this->quote($value),
                $this->quote('object'),
            ));
        }

        $extractExpr = $this->getJsonExtractExpr($column, $path);
        return new \Maho\Db\Expr(sprintf('%s = %s', $extractExpr, $this->quote($value)));
    }

    /**
     * Check if a JSON column contains a specific JSON value (SQLite)
     */
    #[\Override]
    public function getJsonContainsExpr(string $column, string $value, ?string $path = null): \Maho\Db\Expr
    {
        if ($path !== null && str_contains($path, '$**')) {
            throw new \InvalidArgumentException('Wildcard paths are not supported in getJsonContainsExpr(). Use getJsonSearchExpr() instead.');
        }

        if ($path !== null) {
            // JSON_EACH(col, path) handles both arrays (multiple rows) and scalars (single row)
            return new \Maho\Db\Expr(sprintf(
                'EXISTS(SELECT 1 FROM JSON_EACH(%s, %s) WHERE "value" = JSON_EXTRACT(JSON(%s), %s))',
                $column,
                $this->quote($path),
                $this->quote($value),
                $this->quote('$'),
            ));
        }

        // Top-level: JSON_EACH iterates array elements or object values,
        // but JSON_CONTAINS should only match array elements, not object values
        return new \Maho\Db\Expr(sprintf(
            '(JSON_TYPE(%s) = %s AND EXISTS(SELECT 1 FROM JSON_EACH(%s) WHERE "value" = JSON_EXTRACT(JSON(%s), %s)))',
            $column,
            $this->quote('array'),
            $column,
            $this->quote($value),
            $this->quote('$'),
        ));
    }

    // =========================================================================
    // Insert Methods - SQLite-specific implementations
    // =========================================================================

    /**
     * Inserts a table row with ON CONFLICT DO UPDATE (SQLite's upsert syntax)
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertOnDuplicate(string|array|\Maho\Db\Select $table, array $data, array $fields = []): int
    {
        $row = reset($data);
        $bind = [];
        $values = [];

        if (is_array($row)) {
            $cols = array_keys($row);
            foreach ($data as $row) {
                if (array_diff($cols, array_keys($row))) {
                    throw new \Maho\Db\Exception('Invalid data for insert');
                }
                $values[] = $this->_prepareInsertData($row, $bind);
            }
            unset($row);
        } else {
            $cols = array_keys($data);
            $values[] = $this->_prepareInsertData($data, $bind);
        }

        $updateFields = [];
        if (empty($fields)) {
            $fields = $cols;
        }

        // Get the conflict columns (unique constraint matching insert cols, or primary key)
        $conflictColumns = $this->_getPrimaryKeyColumns($table, $cols);
        if (empty($conflictColumns)) {
            // Fall back to first column if no primary key
            $conflictColumns = [$cols[0]];
        }

        // Prepare ON CONFLICT DO UPDATE conditions
        foreach ($fields as $k => $v) {
            $field = $value = null;
            if (!is_numeric($k)) {
                $field = $this->quoteIdentifier($k);
                if ($v instanceof \Maho\Db\Expr) {
                    $value = $v->__toString();
                } elseif (is_string($v)) {
                    $value = sprintf('excluded.%s', $this->quoteIdentifier($v));
                } elseif (is_numeric($v)) {
                    $value = $this->quoteInto('?', $v);
                }
            } elseif (is_string($v)) {
                $value = sprintf('excluded.%s', $this->quoteIdentifier($v));
                $field = $this->quoteIdentifier($v);
            }

            if ($field && $value) {
                $updateFields[] = sprintf('%s = %s', $field, $value);
            }
        }

        $insertSql = $this->_getInsertSqlQuery($table, $cols, $values);

        if ($updateFields) {
            $conflictCols = array_map([$this, 'quoteIdentifier'], $conflictColumns);
            $insertSql .= sprintf(
                ' ON CONFLICT (%s) DO UPDATE SET %s',
                implode(', ', $conflictCols),
                implode(', ', $updateFields),
            );
        }

        $stmt = $this->query($insertSql, array_values($bind));

        return $stmt->rowCount() ?: 1;
    }

    /**
     * Get conflict columns for ON CONFLICT clause based on data being inserted
     *
     * SQLite requires specifying the exact columns that form the constraint
     * being violated. We check for unique indexes that match the insert columns
     * first, then fall back to primary key.
     *
     * @param bool $allowRetry If true and no unique index is found, will reset DDL cache and retry
     */
    protected function _getPrimaryKeyColumns(string|array|\Maho\Db\Select $table, array $insertCols = [], bool $allowRetry = true): array
    {
        $tableName = is_array($table) ? reset($table) : (string) $table;

        // Get all indexes for the table
        $indexes = $this->getIndexList($tableName);

        // First, look for a unique index whose columns are all in the insert data
        foreach ($indexes as $indexName => $index) {
            if ($indexName === 'PRIMARY') {
                continue; // Check primary key last
            }

            if ($index['INDEX_TYPE'] !== AdapterInterface::INDEX_TYPE_UNIQUE) {
                continue;
            }

            $indexCols = $index['COLUMNS_LIST'];
            // Check if all index columns are in the insert data
            if (!empty($insertCols) && !array_diff($indexCols, $insertCols)) {
                return $indexCols;
            }
        }

        // If no matching unique index was found and retry is allowed,
        // the DDL cache might be stale - reset it and try once more
        if ($allowRetry && !empty($insertCols)) {
            $this->resetDdlCache($tableName);
            $result = $this->_getPrimaryKeyColumns($table, $insertCols, false);
            // If we found a unique index after cache reset, return it
            if (!empty($result) && $result !== ($indexes['PRIMARY']['COLUMNS_LIST'] ?? [])) {
                return $result;
            }
        }

        // Fall back to primary key
        if (isset($indexes['PRIMARY'])) {
            return $indexes['PRIMARY']['COLUMNS_LIST'];
        }

        // Last resort: get from describe table
        $describe = $this->describeTable($tableName);
        $primaryKeys = [];
        foreach ($describe as $column) {
            if (!empty($column['PRIMARY'])) {
                $primaryKeys[] = $column['COLUMN_NAME'];
            }
        }

        return $primaryKeys;
    }

    /**
     * Inserts a table multiply rows with specified data.
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertMultiple(string|array|\Maho\Db\Select $table, array $data): int
    {
        $row = reset($data);
        if (!is_array($row)) {
            return $this->insert($table, $data);
        }

        $cols = array_keys($row);
        $insertArray = [];
        foreach ($data as $row) {
            $line = [];
            if (array_diff($cols, array_keys($row))) {
                throw new \Maho\Db\Exception('Invalid data for insert');
            }
            foreach ($cols as $field) {
                $line[] = $row[$field];
            }
            $insertArray[] = $line;
        }
        unset($row);

        return $this->insertArray($table, $cols, $insertArray);
    }

    /**
     * Insert array to table based on columns definition
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertArray(string $table, array $columns, array $data): int
    {
        $values = [];
        $bind = [];
        $columnsCount = count($columns);
        foreach ($data as $row) {
            if ($columnsCount != count($row)) {
                throw new \Maho\Db\Exception('Invalid data for insert');
            }
            $values[] = $this->_prepareInsertData($row, $bind);
        }

        $insertQuery = $this->_getInsertSqlQuery($table, $columns, $values);

        $stmt = $this->query($insertQuery, $bind);
        return $stmt->rowCount();
    }

    /**
     * Inserts a table row with ON CONFLICT DO NOTHING (SQLite's INSERT OR IGNORE)
     */
    #[\Override]
    public function insertIgnore(string|array|\Maho\Db\Select $table, array $bind): int
    {
        $cols = [];
        $vals = [];
        foreach ($bind as $col => $val) {
            $cols[] = $this->quoteIdentifier($col, true);
            if ($val instanceof \Maho\Db\Expr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                $vals[] = '?';
            }
        }

        $sql = 'INSERT OR IGNORE INTO '
            . $this->quoteIdentifier($table, true)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES (' . implode(', ', $vals) . ')';

        $bind = array_values($bind);
        $stmt = $this->query($sql, $bind);

        return $stmt->rowCount();
    }

    /**
     * Returns the ID of the last inserted row
     */
    #[\Override]
    public function lastInsertId(?string $tableName = null, ?string $primaryKey = null): string|int
    {
        $this->_connect();
        return $this->_connection->lastInsertId();
    }

    /**
     * Acquire a named lock using a locks table
     *
     * SQLite doesn't have advisory locks, so we implement using a table.
     * The locks table is created on first use.
     */
    #[\Override]
    public function getLock(string $lockName, int $timeout = 0): bool
    {
        $this->_connect();
        $this->_ensureLocksTableExists();

        $lockKey = md5($lockName);
        $expireTime = time() + 3600; // Locks expire after 1 hour

        // Clean up expired locks
        $this->raw_query(sprintf(
            'DELETE FROM maho_advisory_locks WHERE expire_time < %d',
            time(),
        ));

        $startTime = time();
        do {
            try {
                // Try to insert lock record
                $sql = sprintf(
                    'INSERT INTO maho_advisory_locks (lock_key, expire_time) VALUES (%s, %d)',
                    $this->quote($lockKey),
                    $expireTime,
                );
                $this->raw_query($sql);
                return true;
            } catch (\Exception $e) {
                // Lock already exists
                if ($timeout <= 0) {
                    return false;
                }
                usleep(100000); // Wait 100ms before retrying
            }
        } while ((time() - $startTime) < $timeout);

        return false;
    }

    /**
     * Release a named lock
     */
    #[\Override]
    public function releaseLock(string $lockName): bool
    {
        $this->_connect();

        if (!$this->_locksTableExists()) {
            return true;
        }

        $lockKey = md5($lockName);
        $sql = sprintf(
            'DELETE FROM maho_advisory_locks WHERE lock_key = %s',
            $this->quote($lockKey),
        );
        $this->raw_query($sql);

        return true;
    }

    /**
     * Check if a named lock is currently held
     */
    #[\Override]
    public function isLocked(string $lockName): bool
    {
        $this->_connect();

        if (!$this->_locksTableExists()) {
            return false;
        }

        $lockKey = md5($lockName);
        $result = $this->fetchOne(
            sprintf(
                'SELECT 1 FROM maho_advisory_locks WHERE lock_key = %s AND expire_time > %d',
                $this->quote($lockKey),
                time(),
            ),
        );

        return (bool) $result;
    }

    /**
     * Ensure the advisory locks table exists
     */
    protected function _ensureLocksTableExists(): void
    {
        $this->raw_query('
            CREATE TABLE IF NOT EXISTS maho_advisory_locks (
                lock_key TEXT PRIMARY KEY,
                expire_time INTEGER NOT NULL
            )
        ');
    }

    /**
     * Check if locks table exists
     */
    protected function _locksTableExists(): bool
    {
        $result = $this->fetchOne(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='maho_advisory_locks'",
        );
        return (bool) $result;
    }

    // =========================================================================
    // DDL Methods - SQLite-specific implementations
    // =========================================================================

    /**
     * Executes a SQL statement(s)
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function multiQuery(string $sql): array
    {
        try {
            $stmts = $this->_splitMultiQuery($sql);
            $result = [];
            foreach ($stmts as $stmt) {
                $result[] = $this->raw_query($stmt);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        $this->resetDdlCache();

        return $result;
    }

    /**
     * Split multi statement query
     *
     * @return array<string>
     */
    protected function _splitMultiQuery(string $sql): array
    {
        $parts = preg_split(
            '#(;|\'|"|\\\\|//|--|\n|/\*|\*/)#',
            $sql,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE,
        );

        $q = false;
        $c = false;
        $stmts = [];
        $s = '';

        foreach ($parts as $i => $part) {
            if (($part === "'" || $part === '"') && ($i === 0 || $parts[$i - 1] !== '\\')) {
                if ($q === false) {
                    $q = $part;
                } elseif ($q === $part) {
                    $q = false;
                }
            }

            if (($part === '//' || $part === '--') && ($i === 0 || $parts[$i - 1] === "\n")) {
                $c = $part;
            } elseif ($part === "\n" && ($c === '//' || $c === '--')) {
                $c = false;
            }

            if ($part === '/*' && $c === false) {
                $c = '/*';
            } elseif ($part === '*/' && $c === '/*') {
                $c = false;
            }

            if ($part === ';' && $q === false && $c === false) {
                if (trim($s) !== '') {
                    $stmts[] = trim($s);
                    $s = '';
                }
            } else {
                $s .= $part;
            }
        }
        if (trim($s) !== '') {
            $stmts[] = trim($s);
        }

        return $stmts;
    }

    /**
     * Drop the Foreign Key from table
     *
     * SQLite doesn't support ALTER TABLE DROP FOREIGN KEY directly.
     * This method recreates the table without the specified foreign key.
     */
    #[\Override]
    public function dropForeignKey(string $tableName, string $fkName, ?string $schemaName = null): self
    {
        $actualTableName = $this->_getTableName($tableName, $schemaName);

        // Get current foreign keys
        $foreignKeys = $this->getForeignKeys($actualTableName, $schemaName);

        // Check if the FK exists
        // SQLite doesn't preserve FK constraint names, so we need to match by:
        // 1. The stored FK_NAME (if it exists)
        // 2. The array key
        // 3. Generating what the FK name WOULD be based on structure
        $fkNameUpper = strtoupper($fkName);
        $foundFk = false;
        $newForeignKeys = [];

        foreach ($foreignKeys as $key => $fk) {
            $isMatch = false;

            // Check by stored name
            $existingFkName = strtoupper($fk['FK_NAME'] ?? '');
            if ($existingFkName !== '' && $existingFkName === $fkNameUpper) {
                $isMatch = true;
            }

            // Check by array key
            if (!$isMatch && strtoupper($key) === $fkNameUpper) {
                $isMatch = true;
            }

            // Check by generating expected FK name from structure
            if (!$isMatch && !empty($fk['COLUMN_NAME']) && !empty($fk['REF_TABLE_NAME']) && !empty($fk['REF_COLUMN_NAME'])) {
                $generatedName = $this->getForeignKeyName(
                    $actualTableName,
                    $fk['COLUMN_NAME'],
                    $fk['REF_TABLE_NAME'],
                    $fk['REF_COLUMN_NAME'],
                );
                if (strtoupper($generatedName) === $fkNameUpper) {
                    $isMatch = true;
                }
            }

            if (!$isMatch) {
                $newForeignKeys[$key] = $fk;
            } else {
                $foundFk = true;
            }
        }

        // If FK wasn't found, nothing to do
        if (!$foundFk) {
            return $this;
        }

        // Get current table columns
        $describe = $this->describeTable($actualTableName, $schemaName);

        // Build column definitions for CREATE TABLE
        $columnDefs = [];
        $columnNames = [];

        foreach ($describe as $colName => $colInfo) {
            $columnNames[] = $this->quoteIdentifier($colName);
            $existingDef = [
                'TYPE' => $colInfo['DATA_TYPE'],
                'LENGTH' => $colInfo['LENGTH'],
                'NULLABLE' => $colInfo['NULLABLE'],
                'DEFAULT' => $colInfo['DEFAULT'],
                'PRIMARY' => $colInfo['PRIMARY'],
                'IDENTITY' => $colInfo['IDENTITY'],
            ];
            $columnDefs[] = $this->quoteIdentifier($colName) . ' ' . $this->_getColumnDefinition($existingDef);
        }

        // Get indexes (excluding PRIMARY)
        $indexes = $this->getIndexList($actualTableName, $schemaName);
        $indexDefs = [];
        foreach ($indexes as $indexData) {
            if ($indexData['KEY_NAME'] === 'PRIMARY' || $indexData['INDEX_TYPE'] === AdapterInterface::INDEX_TYPE_PRIMARY) {
                continue;
            }
            $indexDefs[] = $indexData;
        }

        // Build foreign key definitions (without the one we're dropping)
        $fkDefs = [];
        foreach ($newForeignKeys as $fk) {
            $fkDefs[] = sprintf(
                'FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE %s',
                $this->quoteIdentifier($fk['COLUMN_NAME']),
                $this->quoteIdentifier($fk['REF_TABLE_NAME']),
                $this->quoteIdentifier($fk['REF_COLUMN_NAME']),
                $fk['ON_DELETE'],
                $fk['ON_UPDATE'],
            );
        }

        // Create temporary table name
        $tempTableName = $actualTableName . '_temp_' . uniqid();

        $allDefs = array_merge($columnDefs, $fkDefs);
        $createSql = sprintf(
            'CREATE TABLE %s (%s)',
            $this->quoteIdentifier($tempTableName),
            implode(', ', $allDefs),
        );

        // Execute table recreation using native SQLite transaction
        $this->_connect();
        $conn = $this->_connection;

        try {
            $conn->executeStatement('PRAGMA foreign_keys = OFF');
            $conn->executeStatement('BEGIN TRANSACTION');

            $conn->executeStatement($createSql);

            $copySql = sprintf(
                'INSERT INTO %s (%s) SELECT %s FROM %s',
                $this->quoteIdentifier($tempTableName),
                implode(', ', $columnNames),
                implode(', ', $columnNames),
                $this->quoteIdentifier($actualTableName),
            );
            $conn->executeStatement($copySql);

            $conn->executeStatement(sprintf('DROP TABLE %s', $this->quoteIdentifier($actualTableName)));

            $conn->executeStatement(sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $this->quoteIdentifier($tempTableName),
                $this->quoteIdentifier($actualTableName),
            ));

            foreach ($indexDefs as $indexData) {
                $indexType = $indexData['INDEX_TYPE'] === AdapterInterface::INDEX_TYPE_UNIQUE ? 'UNIQUE ' : '';
                $indexSql = sprintf(
                    'CREATE %sINDEX %s ON %s (%s)',
                    $indexType,
                    $this->quoteIdentifier($indexData['KEY_NAME']),
                    $this->quoteIdentifier($actualTableName),
                    implode(', ', array_map([$this, 'quoteIdentifier'], $indexData['COLUMNS_LIST'])),
                );
                $conn->executeStatement($indexSql);
            }

            $conn->executeStatement('COMMIT');
            $conn->executeStatement('PRAGMA foreign_keys = ON');
        } catch (\Exception $e) {
            $conn->executeStatement('ROLLBACK');
            $conn->executeStatement('PRAGMA foreign_keys = ON');
            throw new \Maho\Db\Exception(sprintf('Failed to drop foreign key: %s', $e->getMessage()), 0, $e);
        }

        $this->resetDdlCache($actualTableName, $schemaName);

        return $this;
    }

    /**
     * Check does table column exist
     */
    #[\Override]
    public function tableColumnExists(string $tableName, string $columnName, ?string $schemaName = null): bool
    {
        $describe = $this->describeTable($tableName, $schemaName);
        foreach ($describe as $column) {
            if (strcasecmp($column['COLUMN_NAME'], $columnName) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds new column to table.
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function addColumn(string $tableName, string $columnName, array|string $definition, ?string $schemaName = null): self
    {
        if ($this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return $this;
        }

        if (is_array($definition)) {
            $definition = array_change_key_case($definition, CASE_UPPER);
            if (empty($definition['COMMENT'])) {
                throw new \Maho\Db\Exception('Impossible to create a column without comment.');
            }
            $definition = $this->_getColumnDefinition($definition);
        }

        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            $this->quoteIdentifier($columnName),
            $definition,
        );

        $this->raw_query($sql);
        $this->resetDdlCache($tableName, $schemaName);

        return $this;
    }

    /**
     * Delete table column
     *
     * SQLite 3.35+ supports ALTER TABLE DROP COLUMN
     */
    #[\Override]
    public function dropColumn(string $tableName, string $columnName, ?string $schemaName = null): bool
    {
        if (!$this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return true;
        }

        $sql = sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            $this->quoteIdentifier($columnName),
        );

        $this->raw_query($sql);
        $this->resetDdlCache($tableName, $schemaName);

        return true;
    }

    /**
     * Change the column name and definition
     *
     * SQLite 3.25+ supports ALTER TABLE RENAME COLUMN
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function changeColumn(
        string $tableName,
        string $oldColumnName,
        string $newColumnName,
        array|string $definition,
        bool $flushData = false,
        ?string $schemaName = null,
    ): self {
        if (!$this->tableColumnExists($tableName, $oldColumnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf(
                'Column "%s" does not exist in table "%s".',
                $oldColumnName,
                $tableName,
            ));
        }

        $quotedTable = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));

        // SQLite only supports RENAME COLUMN, not type changes via ALTER
        if ($oldColumnName !== $newColumnName) {
            $sql = sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $quotedTable,
                $this->quoteIdentifier($oldColumnName),
                $this->quoteIdentifier($newColumnName),
            );
            $this->raw_query($sql);
            $this->resetDdlCache($tableName, $schemaName);
        }

        // Note: SQLite doesn't support changing column type via ALTER TABLE
        // Type changes would require table recreation, which Doctrine DBAL handles
        // For now, we just handle the rename

        return $this;
    }

    /**
     * Modify the column definition
     *
     * SQLite doesn't support ALTER TABLE MODIFY COLUMN directly.
     * This method uses Doctrine DBAL's SchemaManager which handles table recreation automatically.
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function modifyColumn(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self
    {
        $actualTableName = $this->_getTableName($tableName, $schemaName);

        if (!$this->tableColumnExists($actualTableName, $columnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Column "%s" does not exist in table "%s".', $columnName, $actualTableName));
        }

        // Convert string definition to array if needed
        if (is_string($definition)) {
            $definition = ['COLUMN_TYPE' => $definition];
        }
        $definition = array_change_key_case($definition, CASE_UPPER);

        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();

        // Introspect the current table
        $table = $schemaManager->introspectTableByUnquotedName($actualTableName);

        // Save existing indexes BEFORE modification
        $indexesBefore = $this->_saveIndexesBeforeModification($table);

        // Modify column WITHOUT touching indexes
        $newTable = $table->edit()->modifyColumn(
            \Doctrine\DBAL\Schema\Name\UnqualifiedName::unquoted($columnName),
            function (\Doctrine\DBAL\Schema\ColumnEditor $editor) use ($definition): void {
                if (array_key_exists('NULLABLE', $definition)) {
                    $editor->setNotNull(!$definition['NULLABLE']);
                }
                if (array_key_exists('DEFAULT', $definition)) {
                    $editor->setDefaultValue($definition['DEFAULT']);
                }
                if (isset($definition['LENGTH'])) {
                    $editor->setLength((int) $definition['LENGTH']);
                }
                if (isset($definition['PRECISION'])) {
                    $editor->setPrecision((int) $definition['PRECISION']);
                }
                if (isset($definition['SCALE'])) {
                    $editor->setScale((int) $definition['SCALE']);
                }
                if (isset($definition['UNSIGNED'])) {
                    $editor->setUnsigned((bool) $definition['UNSIGNED']);
                }
                if (isset($definition['COMMENT'])) {
                    $editor->setComment($definition['COMMENT']);
                }
            },
        )
            ->create();

        // Compare and apply changes - DBAL handles table recreation for SQLite
        $diff = $comparator->compareTables($table, $newTable);
        if (!$diff->isEmpty()) {
            $schemaManager->alterTable($diff);
        }

        // Recreate any indexes that were lost
        $this->_recreateMissingIndexes($actualTableName, $indexesBefore, $schemaManager);

        $this->resetDdlCache($actualTableName, $schemaName);

        return $this;
    }

    /**
     * Internal method to load table description from database
     *
     * SQLite-specific override to correctly handle INTEGER PRIMARY KEY columns.
     * In SQLite, INTEGER PRIMARY KEY columns are aliases to rowid and are effectively
     * NOT NULL, but PRAGMA table_info reports notnull=0 for them.
     */
    #[\Override]
    protected function _loadTableDescription(string $tableName, ?string $schemaName = null): array
    {
        // Get base description from parent
        $result = parent::_loadTableDescription($tableName, $schemaName);

        // Use PRAGMA table_info to get accurate NOT NULL info
        // SQLite's PRAGMA gives us the actual schema constraints
        $pragma = $this->fetchAll("PRAGMA table_info({$this->quoteIdentifier($tableName)})");
        $pragmaByName = [];
        foreach ($pragma as $col) {
            $pragmaByName[$col['name']] = $col;
        }

        // Fix up NULLABLE for columns where Doctrine DBAL doesn't match SQLite's intent
        foreach ($result as $columnName => &$columnInfo) {
            if (isset($pragmaByName[$columnName])) {
                $pragmaCol = $pragmaByName[$columnName];

                // If PRAGMA says notnull=1, it's definitely NOT NULL
                if ($pragmaCol['notnull'] == 1) {
                    $columnInfo['NULLABLE'] = false;
                }

                // For INTEGER PRIMARY KEY, SQLite allows NULL in the constraint check
                // but the column is effectively NOT NULL since it becomes rowid alias
                if ($pragmaCol['pk'] == 1 && strtoupper($pragmaCol['type']) === 'INTEGER') {
                    $columnInfo['NULLABLE'] = false;
                }
            }
        }

        return $result;
    }

    /**
     * Show table status (SQLite implementation)
     */
    #[\Override]
    public function showTableStatus(string $tableName, ?string $schemaName = null): array|false
    {
        // First check if table exists
        if (!$this->isTableExists($tableName)) {
            return false;
        }

        // Get row count
        $countSql = sprintf('SELECT COUNT(*) as row_count FROM %s', $this->quoteIdentifier($tableName));
        $row = $this->raw_fetchRow($countSql);

        if (!$row) {
            return false;
        }

        // SQLite doesn't provide per-table size info
        // We can only get total database size, so we estimate based on row count
        // This is acceptable since showTableStatus is mainly used for existence checks
        return [
            'Name' => $tableName,
            'Rows' => $row['row_count'] ?? 0,
            'Data_length' => 0, // SQLite doesn't provide per-table size
            'Index_length' => 0, // SQLite doesn't separate index size
        ];
    }

    /**
     * Retrieve valid table name
     */
    #[\Override]
    public function getTableName(string $tableName): string
    {
        $prefix = 't_';
        if (strlen($tableName) > self::LENGTH_TABLE_NAME) {
            $shortName = Helper::shortName($tableName);
            if (strlen($shortName) > self::LENGTH_TABLE_NAME) {
                $hash = md5($tableName);
                if (strlen($prefix . $hash) > self::LENGTH_TABLE_NAME) {
                    $tableName = $this->_minusSuperfluous($hash, $prefix, self::LENGTH_TABLE_NAME);
                } else {
                    $tableName = $prefix . $hash;
                }
            } else {
                $tableName = $shortName;
            }
        }

        return $tableName;
    }

    /**
     * Retrieve valid index name
     */
    #[\Override]
    public function getIndexName(string $tableName, string|array $fields, string $indexType = ''): string
    {
        if (is_array($fields)) {
            $fields = implode('_', $fields);
        }

        switch (strtolower($indexType)) {
            case AdapterInterface::INDEX_TYPE_UNIQUE:
                $prefix = 'unq_';
                $shortPrefix = 'u_';
                break;
            case AdapterInterface::INDEX_TYPE_FULLTEXT:
                $prefix = 'fti_';
                $shortPrefix = 'f_';
                break;
            case AdapterInterface::INDEX_TYPE_INDEX:
            default:
                $prefix = 'idx_';
                $shortPrefix = 'i_';
        }

        $hash = $tableName . '_' . $fields;

        if (strlen($hash) + strlen($prefix) > self::LENGTH_INDEX_NAME) {
            $short = Helper::shortName($prefix . $hash);
            if (strlen($short) > self::LENGTH_INDEX_NAME) {
                $hash = md5($hash);
                if (strlen($hash) + strlen($shortPrefix) > self::LENGTH_INDEX_NAME) {
                    $hash = $this->_minusSuperfluous($hash, $shortPrefix, self::LENGTH_INDEX_NAME);
                }
            } else {
                $hash = $short;
            }
        } else {
            $hash = $prefix . $hash;
        }

        return strtolower($hash);
    }

    /**
     * Retrieve valid foreign key name
     */
    #[\Override]
    public function getForeignKeyName(string $priTableName, string $priColumnName, string $refTableName, string $refColumnName): string
    {
        $prefix = 'fk_';
        $hash = sprintf('%s_%s_%s_%s', $priTableName, $priColumnName, $refTableName, $refColumnName);
        if (strlen($prefix . $hash) > self::LENGTH_FOREIGN_NAME) {
            $short = Helper::shortName($prefix . $hash);
            if (strlen($short) > self::LENGTH_FOREIGN_NAME) {
                $hash = md5($hash);
                if (strlen($prefix . $hash) > self::LENGTH_FOREIGN_NAME) {
                    $hash = $this->_minusSuperfluous($hash, $prefix, self::LENGTH_FOREIGN_NAME);
                } else {
                    $hash = $prefix . $hash;
                }
            } else {
                $hash = $short;
            }
        } else {
            $hash = $prefix . $hash;
        }

        return strtolower($hash);
    }

    /**
     * Stop updating indexes
     *
     * SQLite does not have an equivalent to MySQL's `ALTER TABLE ... DISABLE KEYS`.
     * This method is a no-op for SQLite.
     */
    #[\Override]
    public function disableTableKeys(string $tableName, ?string $schemaName = null): self
    {
        // No-op for SQLite
        return $this;
    }

    /**
     * Re-create missing indexes
     *
     * SQLite does not have an equivalent to MySQL's `ALTER TABLE ... ENABLE KEYS`.
     * This method is a no-op for SQLite.
     */
    #[\Override]
    public function enableTableKeys(string $tableName, ?string $schemaName = null): self
    {
        // No-op for SQLite
        return $this;
    }

    /**
     * Get insert from Select object query
     *
     * Note: SQLite doesn't support ON CONFLICT DO UPDATE with INSERT ... SELECT.
     * For INSERT_ON_DUPLICATE mode, we use INSERT OR REPLACE INTO which:
     * - Deletes the existing row if a conflict occurs on the PRIMARY KEY or UNIQUE constraint
     * - Inserts the new row
     * This achieves similar "upsert" semantics to MySQL's ON DUPLICATE KEY UPDATE.
     */
    #[\Override]
    public function insertFromSelect(\Maho\Db\Select $select, string $table, array $fields = [], bool|int $mode = false): string
    {
        // Determine the INSERT variant based on mode
        $insertType = match ($mode) {
            self::INSERT_IGNORE => 'INSERT OR IGNORE INTO',
            self::INSERT_ON_DUPLICATE => 'INSERT OR REPLACE INTO',
            default => 'INSERT INTO',
        };

        $query = sprintf('%s %s', $insertType, $this->quoteIdentifier($table));
        if ($fields) {
            $columns = array_map([$this, 'quoteIdentifier'], $fields);
            $query = sprintf('%s (%s)', $query, implode(', ', $columns));
        }

        $query = sprintf('%s %s', $query, $select->assemble());

        return $query;
    }

    /**
     * Get update table query using select object for join and update
     */
    #[\Override]
    public function updateFromSelect(\Maho\Db\Select $select, string|array $table): string
    {
        if (is_array($table)) {
            $keys = array_keys($table);
            $tableAlias = $keys[0];
            $tableName = $table[$tableAlias];
        } else {
            $tableAlias = $tableName = $table;
        }

        // SQLite UPDATE ... FROM syntax (similar to PostgreSQL)
        $query = sprintf('UPDATE %s', $this->quoteIdentifier($tableName));

        if ($tableAlias !== $tableName) {
            $query .= sprintf(' AS %s', $this->quoteIdentifier($tableAlias));
        }

        // Build SET clause from select columns
        $columns = $select->getPart(\Maho\Db\Select::COLUMNS);
        $setClauses = [];
        foreach ($columns as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if ($alias) {
                // Handle Expr objects
                if ($column instanceof \Maho\Db\Expr) {
                    $valueExpr = $column->__toString();
                    $setClauses[] = sprintf(
                        '%s = %s',
                        $this->quoteIdentifier($alias),
                        $valueExpr,
                    );
                } elseif ($correlationName) {
                    $setClauses[] = sprintf(
                        '%s = %s.%s',
                        $this->quoteIdentifier($alias),
                        $this->quoteIdentifier($correlationName),
                        $this->quoteIdentifier($column),
                    );
                } else {
                    $setClauses[] = sprintf(
                        '%s = %s',
                        $this->quoteIdentifier($alias),
                        $this->quoteIdentifier($column),
                    );
                }
            }
        }

        if ($setClauses) {
            $query .= sprintf(' SET %s', implode(', ', $setClauses));
        }

        // Add FROM clause
        $from = $select->getPart(\Maho\Db\Select::FROM);
        $fromTables = [];
        $joinConditions = [];
        foreach ($from as $alias => $tableInfo) {
            if ($alias !== $tableAlias) {
                if ($tableInfo['tableName'] instanceof \Maho\Db\Select) {
                    $fromTables[] = sprintf(
                        '(%s) AS %s',
                        $tableInfo['tableName']->assemble(),
                        $this->quoteIdentifier($alias),
                    );
                } else {
                    $fromTables[] = sprintf(
                        '%s AS %s',
                        $this->quoteIdentifier($tableInfo['tableName']),
                        $this->quoteIdentifier($alias),
                    );
                }
                if (!empty($tableInfo['joinCondition'])) {
                    $joinConditions[] = $tableInfo['joinCondition'];
                }
            }
        }

        if ($fromTables) {
            $query .= sprintf(' FROM %s', implode(', ', $fromTables));
        }

        // Build WHERE clause
        $whereClauses = $joinConditions;

        $where = $select->getPart(\Maho\Db\Select::WHERE);
        if ($where) {
            foreach ($where as $wherePart) {
                if (is_array($wherePart)) {
                    foreach ($wherePart as $conjunction => $condition) {
                        if (!empty($whereClauses) && strtoupper($conjunction) === 'OR') {
                            $whereClauses[] = 'OR ' . $condition;
                        } else {
                            $whereClauses[] = $condition;
                        }
                    }
                } else {
                    $whereClauses[] = $wherePart;
                }
            }
        }

        if ($whereClauses) {
            $query .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        return $query;
    }

    /**
     * Get delete from select object query
     */
    #[\Override]
    public function deleteFromSelect(\Maho\Db\Select $select, string $table): string
    {
        $actualTableName = $table;
        $from = $select->getPart(\Maho\Db\Select::FROM);
        if (isset($from[$table]) && isset($from[$table]['tableName'])) {
            $actualTableName = $from[$table]['tableName'];
        }

        // Get primary key column
        $pkColumns = $this->_getPrimaryKeyColumns($actualTableName);
        if (!empty($pkColumns)) {
            $pkColumn = $pkColumns[0];
        } else {
            $tableColumns = array_keys($this->describeTable($actualTableName));
            if (in_array('entity_id', $tableColumns)) {
                $pkColumn = 'entity_id';
            } elseif (in_array('id', $tableColumns)) {
                $pkColumn = 'id';
            } else {
                $pkColumn = $tableColumns[0] ?? 'id';
            }
        }

        // Clone select and reset columns
        $subSelect = clone $select;
        $subSelect->reset(\Maho\Db\Select::COLUMNS);
        $subSelect->columns([$table . '.' . $pkColumn]);

        $query = sprintf(
            'DELETE FROM %s WHERE %s IN (%s)',
            $this->quoteIdentifier($actualTableName),
            $this->quoteIdentifier($pkColumn),
            $subSelect->assemble(),
        );

        return $query;
    }

    /**
     * Return array of table(s) checksum
     */
    #[\Override]
    public function getTablesChecksum(array|string $tableNames, ?string $schemaName = null): array
    {
        $result = [];
        $tableNames = is_array($tableNames) ? $tableNames : [$tableNames];

        foreach ($tableNames as $tableName) {
            // SQLite doesn't have CHECKSUM TABLE, calculate a hash from content
            // This is a simplified version - full implementation would hash all rows
            $sql = sprintf('SELECT COUNT(*) || SUM(rowid) as checksum FROM %s', $this->quoteIdentifier($tableName));
            $row = $this->raw_fetchRow($sql);
            $result[$tableName] = md5((string) ($row['checksum'] ?? ''));
        }

        return $result;
    }

    /**
     * Check if the database support STRAIGHT JOIN
     */
    #[\Override]
    public function supportStraightJoin(): bool
    {
        // SQLite doesn't support STRAIGHT_JOIN
        return false;
    }

    /**
     * Adds order by random to select object
     */
    #[\Override]
    public function orderRand(\Maho\Db\Select $select, ?string $field = null): self
    {
        $select->order(new \Maho\Db\Expr('RANDOM()'));
        return $this;
    }

    /**
     * Render SQL FOR UPDATE clause
     *
     * SQLite doesn't support SELECT ... FOR UPDATE in the same way.
     * The database is locked at the transaction level.
     */
    #[\Override]
    public function forUpdate(string $sql): string
    {
        // SQLite uses transaction-level locking, no row-level FOR UPDATE
        return $sql;
    }

    /**
     * Converts fetched blob into raw binary PHP data
     */
    #[\Override]
    public function decodeVarbinary(mixed $value): mixed
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        return $value;
    }

    /**
     * Drop trigger
     */
    #[\Override]
    public function dropTrigger(string $triggerName): self
    {
        $sql = sprintf('DROP TRIGGER IF EXISTS %s', $this->quoteIdentifier($triggerName));
        $this->raw_query($sql);
        return $this;
    }

    /**
     * Change table auto increment value
     *
     * SQLite uses sqlite_sequence table for autoincrement tracking
     */
    #[\Override]
    public function changeTableAutoIncrement(string $tableName, int $increment, ?string $schemaName = null): \Maho\Db\Statement\Pdo\Sqlite
    {
        // SQLite stores autoincrement values in sqlite_sequence table
        $sql = sprintf(
            'UPDATE sqlite_sequence SET seq = %d WHERE name = %s',
            $increment,
            $this->quote($tableName),
        );

        return $this->query($sql);
    }

    /**
     * Create new table from provided select statement
     */
    #[\Override]
    public function createTableFromSelect(string $tableName, \Maho\Db\Select $select, bool $temporary = false): void
    {
        $sql = sprintf(
            'CREATE %s TABLE %s AS %s',
            $temporary ? 'TEMPORARY' : '',
            $this->quoteIdentifier($tableName),
            $select->assemble(),
        );

        $this->query($sql);
    }

    // =========================================================================
    // DDL Table Management Methods
    // =========================================================================

    /**
     * Retrieve column definition fragment for SQLite
     *
     * @throws \Maho\Db\Exception
     */
    protected function _getColumnDefinition(array $options, ?string $ddlType = null): string
    {
        $options = array_change_key_case($options, CASE_UPPER);
        $cType = null;
        $cNullable = true;
        $cDefault = false;
        $cIdentity = false;

        if ($ddlType === null) {
            $ddlType = $this->_getDdlType($options);
        }

        if (empty($ddlType) || !isset($this->_ddlColumnTypes[$ddlType])) {
            throw new \Maho\Db\Exception('Invalid column definition data');
        }

        $cType = $this->_ddlColumnTypes[$ddlType];

        // Column size/precision handling (SQLite is flexible, but we honor requests)
        switch ($ddlType) {
            case \Maho\Db\Ddl\Table::TYPE_DECIMAL:
            case \Maho\Db\Ddl\Table::TYPE_NUMERIC:
                $precision = 10;
                $scale = 0;
                $match = [];
                if (!empty($options['LENGTH']) && preg_match('#^\(?(\d+),(\d+)\)?$#', $options['LENGTH'], $match)) {
                    $precision = $match[1];
                    $scale = $match[2];
                } else {
                    if (isset($options['SCALE']) && is_numeric($options['SCALE'])) {
                        $scale = $options['SCALE'];
                    }
                    if (isset($options['PRECISION']) && is_numeric($options['PRECISION'])) {
                        $precision = $options['PRECISION'];
                    }
                }
                $cType .= sprintf('(%d,%d)', $precision, $scale);
                break;

            case \Maho\Db\Ddl\Table::TYPE_TEXT:
            case \Maho\Db\Ddl\Table::TYPE_VARCHAR:
                if (!empty($options['LENGTH'])) {
                    $length = $this->_parseTextSize($options['LENGTH']);
                    if ($length <= 65535) {
                        $cType = sprintf('VARCHAR(%d)', $length);
                    }
                }
                break;
        }

        if (array_key_exists('DEFAULT', $options)) {
            $cDefault = $options['DEFAULT'];
        }
        if (array_key_exists('NULLABLE', $options)) {
            $cNullable = (bool) $options['NULLABLE'];
        }
        if (!empty($options['IDENTITY']) || !empty($options['AUTO_INCREMENT'])) {
            $cIdentity = true;
        }

        // Clean up default value quoting
        if ($cDefault !== null && is_string($cDefault) && strlen($cDefault)) {
            $cDefault = str_replace("'", '', $cDefault);
        }

        // Handle timestamp defaults
        if ($ddlType == \Maho\Db\Ddl\Table::TYPE_TIMESTAMP) {
            if ($cDefault === null) {
                $cDefault = new \Maho\Db\Expr('NULL');
            } elseif ($cDefault == \Maho\Db\Ddl\Table::TIMESTAMP_INIT || $cDefault == \Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE) {
                $cDefault = new \Maho\Db\Expr('CURRENT_TIMESTAMP');
            } elseif ($cNullable && !$cDefault) {
                $cDefault = new \Maho\Db\Expr('NULL');
            }
        } elseif (is_null($cDefault) && $cNullable) {
            $cDefault = new \Maho\Db\Expr('NULL');
        }

        // For SQLite INTEGER PRIMARY KEY is auto-incrementing
        if ($cIdentity) {
            // INTEGER PRIMARY KEY AUTOINCREMENT (used with explicit PRIMARY KEY later)
            return 'INTEGER';
        }

        return sprintf(
            '%s%s%s',
            $cType,
            $cNullable ? '' : ' NOT NULL',
            $cDefault !== false ? $this->quoteInto(' DEFAULT ?', $cDefault) : '',
        );
    }

    /**
     * Retrieve columns and primary keys definition array for create table
     *
     * @throws \Maho\Db\Exception
     */
    protected function _getColumnsDefinition(\Maho\Db\Ddl\Table $table): array
    {
        $definition = [];
        $primary = [];
        $columns = $table->getColumns();
        $hasIdentity = false;

        if (empty($columns)) {
            throw new \Maho\Db\Exception('Table columns are not defined');
        }

        foreach ($columns as $columnData) {
            $isIdentity = !empty($columnData['IDENTITY']) || !empty($columnData['AUTO_INCREMENT']);
            if ($isIdentity) {
                $hasIdentity = true;
            }

            $columnDefinition = $this->_getColumnDefinition($columnData);
            if ($columnData['PRIMARY']) {
                $primary[$columnData['COLUMN_NAME']] = $columnData['PRIMARY_POSITION'];

                // SQLite: INTEGER PRIMARY KEY is auto-incrementing
                if ($isIdentity) {
                    $definition[] = sprintf(
                        '  %s %s PRIMARY KEY AUTOINCREMENT',
                        $this->quoteIdentifier($columnData['COLUMN_NAME']),
                        $columnDefinition,
                    );
                    continue;
                }
            }

            $definition[] = sprintf(
                '  %s %s',
                $this->quoteIdentifier($columnData['COLUMN_NAME']),
                $columnDefinition,
            );
        }

        // Add composite PRIMARY KEY if no identity column and multiple primary columns
        if (!$hasIdentity && !empty($primary) && count($primary) > 0) {
            // Check if we didn't already add it inline
            $hasInlinePrimary = false;
            foreach ($definition as $def) {
                if (str_contains($def, 'PRIMARY KEY')) {
                    $hasInlinePrimary = true;
                    break;
                }
            }
            if (!$hasInlinePrimary) {
                asort($primary, SORT_NUMERIC);
                $primaryCols = array_map([$this, 'quoteIdentifier'], array_keys($primary));
                $definition[] = sprintf('  PRIMARY KEY (%s)', implode(', ', $primaryCols));
            }
        }

        return $definition;
    }

    /**
     * Retrieve table indexes definition array for create table
     */
    protected function _getIndexesDefinition(\Maho\Db\Ddl\Table $table): array
    {
        // SQLite unique constraints should be created as named indexes (CREATE UNIQUE INDEX)
        // rather than anonymous inline constraints (UNIQUE (...)) because:
        // 1. DBAL cannot introspect anonymous inline constraints
        // 2. Named indexes are required for insertOnDuplicate's ON CONFLICT detection
        // Unique indexes are created in createTable() after the table is created
        return [];
    }

    /**
     * Retrieve table foreign keys definition array for create table
     */
    protected function _getForeignKeysDefinition(\Maho\Db\Ddl\Table $table): array
    {
        $definition = [];
        $relations = $table->getForeignKeys();

        if (!empty($relations)) {
            foreach ($relations as $fkData) {
                $onDelete = $this->_getDdlAction($fkData['ON_DELETE']);
                $onUpdate = $this->_getDdlAction($fkData['ON_UPDATE']);

                $definition[] = sprintf(
                    '  FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
                    $this->quoteIdentifier($fkData['COLUMN_NAME']),
                    $this->quoteIdentifier($fkData['REF_TABLE_NAME']),
                    $this->quoteIdentifier($fkData['REF_COLUMN_NAME']),
                    $onDelete,
                    $onUpdate,
                );
            }
        }

        return $definition;
    }

    /**
     * Retrieve table options definition array for create table
     */
    protected function _getOptionsDefinition(\Maho\Db\Ddl\Table $table): array
    {
        // SQLite doesn't support table options like ENGINE, CHARSET
        return [];
    }

    /**
     * Retrieve DDL object for new table
     */
    #[\Override]
    public function newTable(?string $tableName = null, ?string $schemaName = null): \Maho\Db\Ddl\Table
    {
        $table = new \Maho\Db\Ddl\Table();
        if ($tableName !== null) {
            $table->setName($tableName);
        }
        if ($schemaName !== null) {
            $table->setSchema($schemaName);
        }

        return $table;
    }

    /**
     * Create table from DDL object
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function createTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Sqlite
    {
        $columns = $table->getColumns();
        foreach ($columns as $columnEntry) {
            if (empty($columnEntry['COMMENT'])) {
                throw new \Maho\Db\Exception('Cannot create table without columns comments');
            }
        }

        $sqlFragment = array_merge(
            $this->_getColumnsDefinition($table),
            $this->_getIndexesDefinition($table),
            $this->_getForeignKeysDefinition($table),
        );

        $sql = sprintf(
            "CREATE TABLE %s (\n%s\n)",
            $this->quoteIdentifier($table->getName()),
            implode(",\n", $sqlFragment),
        );

        $result = $this->query($sql);

        // Create indexes after table creation (except PRIMARY which is in table definition)
        // UNIQUE indexes must be created as named indexes (not anonymous inline constraints)
        // so that DBAL can introspect them for insertOnDuplicate's ON CONFLICT detection
        $indexes = $table->getIndexes();
        foreach ($indexes as $indexData) {
            $indexType = strtoupper($indexData['TYPE'] ?? 'INDEX');
            if ($indexType === 'PRIMARY') {
                continue; // Primary key is part of column definition
            }

            $indexColumns = [];
            foreach ($indexData['COLUMNS'] as $columnData) {
                $indexColumns[] = $this->quoteIdentifier($columnData['NAME']);
            }

            $createIndexSql = $indexType === 'UNIQUE'
                ? 'CREATE UNIQUE INDEX %s ON %s (%s)'
                : 'CREATE INDEX %s ON %s (%s)';

            $this->raw_query(sprintf(
                $createIndexSql,
                $this->quoteIdentifier($indexData['INDEX_NAME']),
                $this->quoteIdentifier($table->getName()),
                implode(', ', $indexColumns),
            ));
        }

        $this->resetDdlCache($table->getName());

        return $result;
    }

    /**
     * Create temporary table from DDL object
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function createTemporaryTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Sqlite
    {
        $sqlFragment = array_merge(
            $this->_getColumnsDefinition($table),
            $this->_getIndexesDefinition($table),
            $this->_getForeignKeysDefinition($table),
        );

        $sql = sprintf(
            "CREATE TEMPORARY TABLE %s (\n%s\n)",
            $this->quoteIdentifier($table->getName()),
            implode(",\n", $sqlFragment),
        );

        return $this->query($sql);
    }

    /**
     * Create DDL Table object by data from describe table
     */
    #[\Override]
    public function createTableByDdl(string $tableName, string $newTableName): \Maho\Db\Ddl\Table
    {
        $describe = $this->describeTable($tableName);
        $table = $this->newTable($newTableName)
            ->setComment(uc_words($newTableName, ' '));

        foreach ($describe as $columnData) {
            $columnInfo = $this->getColumnCreateByDescribe($columnData);

            $table->addColumn(
                $columnInfo['name'],
                $columnInfo['type'],
                $columnInfo['length'],
                $columnInfo['options'],
                $columnInfo['comment'],
            );
        }

        $indexes = $this->getIndexList($tableName);
        foreach ($indexes as $indexData) {
            if (($indexData['KEY_NAME'] == 'PRIMARY')
                || ($indexData['INDEX_TYPE'] == AdapterInterface::INDEX_TYPE_PRIMARY)
            ) {
                continue;
            }

            $fields = $indexData['COLUMNS_LIST'];
            $options = ['type' => $indexData['INDEX_TYPE']];
            $table->addIndex($this->getIndexName($newTableName, $fields, $indexData['INDEX_TYPE']), $fields, $options);
        }

        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $keyData) {
            $fkName = $this->getForeignKeyName(
                $newTableName,
                $keyData['COLUMN_NAME'],
                $keyData['REF_TABLE_NAME'],
                $keyData['REF_COLUMN_NAME'],
            );
            $onDelete = $this->_getDdlAction($keyData['ON_DELETE']);
            $onUpdate = $this->_getDdlAction($keyData['ON_UPDATE']);

            $table->addForeignKey(
                $fkName,
                $keyData['COLUMN_NAME'],
                $keyData['REF_TABLE_NAME'],
                $keyData['REF_COLUMN_NAME'],
                $onDelete,
                $onUpdate,
            );
        }

        return $table;
    }

    /**
     * Drop table from database
     */
    #[\Override]
    public function dropTable(string $tableName, ?string $schemaName = null): bool
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'DROP TABLE IF EXISTS ' . $table;
        $this->query($query);

        return true;
    }

    /**
     * Drop temporary table from database
     */
    #[\Override]
    public function dropTemporaryTable(string $tableName, ?string $schemaName = null): bool
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'DROP TABLE IF EXISTS ' . $table;
        $this->query($query);

        return true;
    }

    /**
     * Truncate a table
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function truncateTable(string $tableName, ?string $schemaName = null): self
    {
        if (!$this->isTableExists($tableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" does not exist', $tableName));
        }

        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        // SQLite doesn't have TRUNCATE, use DELETE
        $query = 'DELETE FROM ' . $table;
        $this->query($query);

        // Reset autoincrement sequence
        $this->raw_query(sprintf(
            'DELETE FROM sqlite_sequence WHERE name = %s',
            $this->quote($tableName),
        ));

        return $this;
    }

    /**
     * Rename table
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTable(string $oldTableName, string $newTableName, ?string $schemaName = null): bool
    {
        if (!$this->isTableExists($oldTableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" does not exist', $oldTableName));
        }
        if ($this->isTableExists($newTableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" already exists', $newTableName));
        }

        $oldTable = $this->quoteIdentifier($this->_getTableName($oldTableName, $schemaName));
        $newTable = $this->quoteIdentifier($newTableName);

        $query = sprintf('ALTER TABLE %s RENAME TO %s', $oldTable, $newTable);
        $this->query($query);

        $this->resetDdlCache($oldTableName, $schemaName);

        return true;
    }

    /**
     * Rename several tables
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTablesBatch(array $tablePairs): bool
    {
        if (count($tablePairs) == 0) {
            throw new \Maho\Db\Exception('Please provide tables for rename');
        }

        foreach ($tablePairs as $pair) {
            $oldTableName = $pair['oldName'];
            $newTableName = $pair['newName'];

            $query = sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $this->quoteIdentifier($oldTableName),
                $this->quoteIdentifier($newTableName),
            );
            $this->query($query);

            $this->resetDdlCache($oldTableName);
            $this->resetDdlCache($newTableName);
        }

        return true;
    }

    /**
     * Add new index to table
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function addIndex(
        string $tableName,
        string $indexName,
        string|array $fields,
        string $indexType = AdapterInterface::INDEX_TYPE_INDEX,
        ?string $schemaName = null,
    ): \Maho\Db\Statement\Pdo\Sqlite {
        $columns = $this->describeTable($tableName, $schemaName);
        $keyList = $this->getIndexList($tableName, $schemaName);

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        // Validate fields exist
        foreach ($fields as $field) {
            if (!isset($columns[$field])) {
                $availableColumns = implode(', ', array_keys($columns));
                throw new \Maho\Db\Exception(sprintf(
                    'There is no field "%s" that you are trying to create an index on "%s". Available columns: %s',
                    $field,
                    $tableName,
                    $availableColumns ?: '(none)',
                ));
            }
        }

        // Handle PRIMARY KEY - requires table recreation
        if (strtolower($indexType) === AdapterInterface::INDEX_TYPE_PRIMARY) {
            return $this->_recreateTableWithPrimaryKey($tableName, $fields, $schemaName);
        }

        // Check if we need to drop existing index
        if (isset($keyList[strtoupper($indexName)])) {
            $this->dropIndex($tableName, $indexName, $schemaName);
        }

        $fieldSql = [];
        foreach ($fields as $field) {
            $fieldSql[] = $this->quoteIdentifier($field);
        }
        $fieldSql = implode(', ', $fieldSql);

        $qualifiedTableName = $this->_getTableName($tableName, $schemaName);

        // SQLite uses CREATE INDEX syntax
        $query = match (strtolower($indexType)) {
            AdapterInterface::INDEX_TYPE_UNIQUE => sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s)',
                $this->quoteIdentifier($indexName),
                $this->quoteIdentifier($qualifiedTableName),
                $fieldSql,
            ),
            default => sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->quoteIdentifier($indexName),
                $this->quoteIdentifier($qualifiedTableName),
                $fieldSql,
            ),
        };

        $result = $this->raw_query($query);
        $this->resetDdlCache($tableName, $schemaName);

        return $result;
    }

    /**
     * Save indexes from a table before modification
     *
     * @return array<string, array{type: \Doctrine\DBAL\Schema\Index\IndexType, columns: list<string>}>
     */
    protected function _saveIndexesBeforeModification(\Doctrine\DBAL\Schema\Table $table): array
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $indexName = trim($index->getObjectName()->toString(), '"');
            if (strtolower($indexName) === 'primary') {
                continue;
            }
            $indexedColumns = $index->getIndexedColumns();
            $columnNames = [];
            foreach ($indexedColumns as $indexedColumn) {
                $columnNames[] = trim($indexedColumn->getColumnName()->toString(), '"');
            }
            $indexes[$indexName] = [
                'type' => $index->getType(),
                'columns' => $columnNames,
            ];
        }
        return $indexes;
    }

    /**
     * Recreate missing indexes after table modification
     *
     * @param array<string, array{type: \Doctrine\DBAL\Schema\Index\IndexType, columns: list<string>}> $indexesBefore
     * @param \Doctrine\DBAL\Schema\AbstractSchemaManager<\Doctrine\DBAL\Platforms\SQLitePlatform> $schemaManager
     */
    protected function _recreateMissingIndexes(
        string $tableName,
        array $indexesBefore,
        \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager,
    ): void {
        // Re-introspect to see what indexes were lost
        $tableAfter = $schemaManager->introspectTableByUnquotedName($tableName);
        $indexesAfter = [];
        foreach ($tableAfter->getIndexes() as $index) {
            $indexName = trim($index->getObjectName()->toString(), '"');
            $indexesAfter[$indexName] = true;
        }

        // Recreate missing indexes using raw SQL
        foreach ($indexesBefore as $indexName => $indexInfo) {
            if (!isset($indexesAfter[$indexName])) {
                $indexType = $indexInfo['type'] === \Doctrine\DBAL\Schema\Index\IndexType::UNIQUE ? 'UNIQUE INDEX' : 'INDEX';
                $quotedColumns = array_map([$this, 'quoteIdentifier'], $indexInfo['columns']);
                $sql = sprintf(
                    'CREATE %s %s ON %s (%s)',
                    $indexType,
                    $this->quoteIdentifier($indexName),
                    $this->quoteIdentifier($tableName),
                    implode(', ', $quotedColumns),
                );
                $this->raw_query($sql);
            }
        }
    }

    /**
     * Recreate table with new PRIMARY KEY using DBAL's alterTable
     *
     * SQLite doesn't support ALTER TABLE ADD PRIMARY KEY, so DBAL's alterTable
     * method automatically handles table recreation for such changes.
     */
    protected function _recreateTableWithPrimaryKey(
        string $tableName,
        array $primaryKeyColumns,
        ?string $schemaName = null,
    ): \Maho\Db\Statement\Pdo\Sqlite {
        $schemaManager = $this->_connection->createSchemaManager();

        // Introspect the current table
        $oldTable = $schemaManager->introspectTableByUnquotedName($tableName);

        // Save existing indexes BEFORE operation
        $indexesBefore = $this->_saveIndexesBeforeModification($oldTable);

        // Create a primary key constraint using the public editor API
        $pkEditor = \Doctrine\DBAL\Schema\PrimaryKeyConstraint::editor();
        $pkEditor->setUnquotedColumnNames($primaryKeyColumns[0], ...array_slice($primaryKeyColumns, 1));
        $pkEditor->setIsClustered(false);
        $primaryKeyConstraint = $pkEditor->create();

        // Create a new table with the primary key using the public editor API
        // Use setPrimaryKeyConstraint to replace any existing primary key
        $newTable = $oldTable->edit()
            ->setPrimaryKeyConstraint($primaryKeyConstraint)
            ->create();

        // Use DBAL's comparator to generate the table diff
        $comparator = $schemaManager->createComparator();
        $tableDiff = $comparator->compareTables($oldTable, $newTable);

        // Let DBAL handle the table recreation
        $schemaManager->alterTable($tableDiff);

        // Recreate any indexes that were lost
        $this->_recreateMissingIndexes($tableName, $indexesBefore, $schemaManager);

        $this->resetDdlCache($tableName, $schemaName);

        // Return a dummy statement since alterTable executes internally
        return $this->raw_query('SELECT 1');
    }

    /**
     * Drop the index from table
     */
    #[\Override]
    public function dropIndex(string $tableName, string $keyName, ?string $schemaName = null): bool|\Maho\Db\Statement\Pdo\Sqlite
    {
        $indexList = $this->getIndexList($tableName, $schemaName);
        $keyName = strtoupper($keyName);

        if (!isset($indexList[$keyName])) {
            return true;
        }

        if ($keyName == 'PRIMARY') {
            // SQLite cannot drop primary key
            return true;
        }

        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            $this->quoteIdentifier($indexList[$keyName]['KEY_NAME']),
        );

        $this->resetDdlCache($tableName, $schemaName);

        return $this->raw_query($sql);
    }

    /**
     * Add new Foreign Key to table
     *
     * SQLite doesn't support ALTER TABLE ADD FOREIGN KEY directly.
     * This method uses Doctrine DBAL's SchemaManager which handles table recreation automatically.
     */
    #[\Override]
    public function addForeignKey(
        string $fkName,
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = AdapterInterface::FK_ACTION_CASCADE,
        string $onUpdate = AdapterInterface::FK_ACTION_CASCADE,
        bool $purge = false,
        ?string $schemaName = null,
        ?string $refSchemaName = null,
    ): self {
        $actualTableName = $this->_getTableName($tableName, $schemaName);

        if ($purge) {
            $this->purgeOrphanRecords($tableName, $columnName, $refTableName, $refColumnName, $onDelete);
        }

        // Check if the column exists
        if (!$this->tableColumnExists($actualTableName, $columnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Column "%s" does not exist in table "%s".', $columnName, $actualTableName));
        }

        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $comparator = $schemaManager->createComparator();

        // Introspect the table using the recommended DBAL 4.4 method
        // Note: There's a bug where introspectTableByUnquotedName() returns index column names
        // with literal quote characters (e.g., "sku" instead of sku). We'll fix this ourselves.
        //
        // TODO: Once Doctrine DBAL fixes this bug, remove the index fixing workaround below
        // (lines 2751-2787) and the drop/add index loop (lines 2817-2830). Simply use:
        //   $newTable = $table->edit()->addForeignKeyConstraint($fk)->create();
        $table = $schemaManager->introspectTableByUnquotedName($actualTableName);

        // Save existing indexes BEFORE FK addition
        $indexesBefore = $this->_saveIndexesBeforeModification($table);

        // Map action strings to ReferentialAction enum
        $actionMap = [
            AdapterInterface::FK_ACTION_CASCADE => \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::CASCADE,
            AdapterInterface::FK_ACTION_SET_NULL => \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::SET_NULL,
            AdapterInterface::FK_ACTION_NO_ACTION => \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::NO_ACTION,
            AdapterInterface::FK_ACTION_RESTRICT => \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::RESTRICT,
            AdapterInterface::FK_ACTION_SET_DEFAULT => \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::SET_DEFAULT,
        ];

        $onDeleteAction = $actionMap[strtoupper($onDelete)] ?? \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::CASCADE;
        $onUpdateAction = $actionMap[strtoupper($onUpdate)] ?? \Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction::CASCADE;

        // Build the FK constraint using DBAL's fluent API
        $fk = \Doctrine\DBAL\Schema\ForeignKeyConstraint::editor()
            ->setUnquotedName($fkName)
            ->setUnquotedReferencingColumnNames($columnName)
            ->setUnquotedReferencedTableName($refTableName)
            ->setUnquotedReferencedColumnNames($refColumnName)
            ->setOnDeleteAction($onDeleteAction)
            ->setOnUpdateAction($onUpdateAction)
            ->create();

        // Add FK WITHOUT touching indexes
        $newTable = $table->edit()->addForeignKeyConstraint($fk)->create();

        // Compare and apply changes - DBAL handles table recreation for SQLite
        $diff = $comparator->compareTables($table, $newTable);
        if (!$diff->isEmpty()) {
            $schemaManager->alterTable($diff);
        }

        $this->_recreateMissingIndexes($actualTableName, $indexesBefore, $schemaManager);
        $this->resetDdlCache($actualTableName, $schemaName);

        return $this;
    }

    /**
     * Run additional environment before setup
     */
    #[\Override]
    public function startSetup(): self
    {
        // Disable foreign key checks temporarily
        $this->raw_query('PRAGMA foreign_keys = OFF');

        return $this;
    }

    /**
     * Run additional environment after setup
     */
    #[\Override]
    public function endSetup(): self
    {
        // Re-enable foreign key checks
        $this->raw_query('PRAGMA foreign_keys = ON');

        return $this;
    }

    /**
     * Build SQL statement for condition
     */
    #[\Override]
    public function prepareSqlCondition(string|array $fieldName, int|string|array|null $condition): string
    {
        $conditionKeyMap = [
            'eq'            => '{{fieldName}} = ?',
            'neq'           => '{{fieldName}} != ?',
            'like'          => '{{fieldName}} LIKE ?',
            'nlike'         => '{{fieldName}} NOT LIKE ?',
            'in'            => '{{fieldName}} IN(?)',
            'nin'           => '{{fieldName}} NOT IN(?)',
            'is'            => '{{fieldName}} IS ?',
            'notnull'       => '{{fieldName}} IS NOT NULL',
            'null'          => '{{fieldName}} IS NULL',
            'gt'            => '{{fieldName}} > ?',
            'lt'            => '{{fieldName}} < ?',
            'gteq'          => '{{fieldName}} >= ?',
            'lteq'          => '{{fieldName}} <= ?',
            'finset'        => "(INSTR(',' || {{fieldName}} || ',', ',' || ? || ',') > 0)",
            'regexp'        => '{{fieldName}} REGEXP ?',  // Requires SQLite extension or custom function
            'from'          => '{{fieldName}} >= ?',
            'to'            => '{{fieldName}} <= ?',
            'seq'           => null,
            'sneq'          => null,
        ];

        $query = '';
        if (is_array($condition)) {
            $key = key(array_intersect_key($condition, $conditionKeyMap));

            if (isset($condition['from']) || isset($condition['to'])) {
                if (isset($condition['from'])) {
                    $from = $this->_prepareSqlDateCondition($condition, 'from');
                    $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['from'], $from, $fieldName);
                }
                if (isset($condition['to'])) {
                    $query .= empty($query) ? '' : ' AND ';
                    $to = $this->_prepareSqlDateCondition($condition, 'to');
                    $query = $query . $this->_prepareQuotedSqlCondition($conditionKeyMap['to'], $to, $fieldName);
                }
            } elseif ($key !== null && array_key_exists($key, $conditionKeyMap)) {
                $value = $condition[$key];
                if (($key == 'seq') || ($key == 'sneq')) {
                    $key = $this->_transformStringSqlCondition($key, $value);
                }
                $query = $this->_prepareQuotedSqlCondition($conditionKeyMap[$key], $value, $fieldName);
            } else {
                $queries = [];
                foreach ($condition as $orCondition) {
                    $queries[] = sprintf('(%s)', $this->prepareSqlCondition($fieldName, $orCondition));
                }
                $query = sprintf('(%s)', implode(' OR ', $queries));
            }
        } elseif ($condition === null) {
            $query = str_replace('{{fieldName}}', (string) $fieldName, $conditionKeyMap['null']);
        } else {
            // Don't cast numeric values to string - SQLite has strict type checking
            // and comparing CASE WHEN results (integers) against quoted strings fails
            $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['eq'], $condition, $fieldName);
        }

        return $query;
    }

    /**
     * Prepare Sql condition
     */
    protected function _prepareQuotedSqlCondition(string $text, mixed $value, string|array $fieldName): string
    {
        $value = is_string($value) ? str_replace("\0", '', $value) : $value;
        $sql = $this->quoteInto($text, $value);
        return str_replace('{{fieldName}}', (string) $fieldName, $sql);
    }

    /**
     * Transform string sql condition
     */
    protected function _transformStringSqlCondition(string $conditionKey, mixed $value): string
    {
        $value = str_replace("\0", '', (string) $value);
        if ($value == '') {
            return ($conditionKey == 'seq') ? 'null' : 'notnull';
        }
        return ($conditionKey == 'seq') ? 'eq' : 'neq';
    }

    /**
     * Prepare value for save in column
     */
    #[\Override]
    public function prepareColumnValue(array $column, mixed $value): mixed
    {
        if ($value instanceof \Maho\Db\Expr) {
            return $value;
        }
        if ($value instanceof \Maho\Db\Statement\Parameter) {
            return $value;
        }

        if (!isset($column['DATA_TYPE'])) {
            return $value;
        }

        if (is_null($value) && $column['NULLABLE']) {
            return null;
        }

        switch ($column['DATA_TYPE']) {
            case 'smallint':
            case 'integer':
            case 'int':
                $value = (int) $value;
                break;
            case 'bigint':
                if (!is_integer($value)) {
                    $value = sprintf('%.0f', (float) $value);
                }
                break;
            case 'numeric':
            case 'decimal':
                $precision = 10;
                $scale = 0;
                if (isset($column['SCALE'])) {
                    $scale = $column['SCALE'];
                }
                if (isset($column['PRECISION'])) {
                    $precision = $column['PRECISION'];
                }
                $format = sprintf('%%%d.%dF', $precision - $scale, $scale);
                $value = (float) sprintf($format, $value);
                break;
            case 'real':
            case 'float':
                $value = (float) sprintf('%F', $value);
                break;
            case 'date':
                if ($column['NULLABLE'] && ($value === false || $value === '' || $value === null)) {
                    $value = null;
                } else {
                    $value = $this->formatDate($value, false);
                }
                break;
            case 'timestamp':
            case 'text':
                if ($column['NULLABLE'] && ($value === false || $value === '' || $value === null)) {
                    $value = null;
                } else {
                    // Check if this is a datetime field by the name pattern
                    if (preg_match('/(_at|_date|_time)$/', $column['COLUMN_NAME'] ?? '')) {
                        $value = $this->formatDate($value, true);
                    } else {
                        $value = str_replace("\0", '', (string) $value);
                    }
                }
                break;
            case 'blob':
                // No special processing for SQLite blob
                break;
        }

        return $value;
    }

    /**
     * Generate select queries for range
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function selectsByRange(string $rangeField, \Maho\Db\Select $select, int $stepCount = 100): array
    {
        $queries = [];
        $fromSelect = $select->getPart(\Maho\Db\Select::FROM);

        if (empty($fromSelect)) {
            throw new \Maho\Db\Exception('Select must have correct FROM part');
        }

        $tableName = [];
        $correlationName = '';
        foreach ($fromSelect as $correlationName => $formPart) {
            if ($formPart['joinType'] == \Maho\Db\Select::FROM) {
                $tableName = $formPart['tableName'];
                break;
            }
        }

        $selectRange = $this->select()
            ->from(
                $tableName,
                [
                    new \Maho\Db\Expr('MIN(' . $this->quoteIdentifier($rangeField) . ') AS min'),
                    new \Maho\Db\Expr('MAX(' . $this->quoteIdentifier($rangeField) . ') AS max'),
                ],
            );

        $rangeResult = $this->fetchRow($selectRange);
        $min = $rangeResult['min'];
        $max = $rangeResult['max'];

        while ($min <= $max) {
            $partialSelect = clone $select;
            $partialSelect->where(
                $this->quoteIdentifier($correlationName) . '.'
                    . $this->quoteIdentifier($rangeField) . ' >= ?',
                $min,
            )
            ->where(
                $this->quoteIdentifier($correlationName) . '.'
                    . $this->quoteIdentifier($rangeField) . ' < ?',
                $min + $stepCount,
            );
            $queries[] = $partialSelect;
            $min += $stepCount;
        }

        return $queries;
    }

    /**
     * Insert data with explicit ID (force insert even with 0 values)
     */
    #[\Override]
    public function insertForce(string $table, array $bind): int
    {
        return $this->insert($table, $bind);
    }

    /**
     * Modify the column definition by data from describe table
     */
    #[\Override]
    public function modifyColumnByDdl(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self
    {
        if (is_string($definition)) {
            $this->modifyColumn($tableName, $columnName, $definition, $flushData, $schemaName);
            return $this;
        }

        $definition = array_change_key_case($definition, CASE_UPPER);
        $definition['COLUMN_TYPE'] = $this->_getColumnTypeByDdl($definition);

        if (array_key_exists('DEFAULT', $definition) && is_null($definition['DEFAULT'])) {
            unset($definition['DEFAULT']);
        }

        $this->modifyColumn($tableName, $columnName, $definition, $flushData, $schemaName);

        return $this;
    }

    /**
     * Purge orphan records from a table based on foreign key relationship
     */
    #[\Override]
    public function purgeOrphanRecords(
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = AdapterInterface::FK_ACTION_CASCADE,
    ): self {
        $onDelete = strtoupper($onDelete);

        if ($onDelete == AdapterInterface::FK_ACTION_CASCADE
            || $onDelete == AdapterInterface::FK_ACTION_RESTRICT
        ) {
            $sql = sprintf(
                'DELETE FROM %s WHERE %s NOT IN (SELECT %s FROM %s WHERE %s IS NOT NULL)',
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
                $this->quoteIdentifier($refTableName),
                $this->quoteIdentifier($refColumnName),
            );
            $this->raw_query($sql);
        } elseif ($onDelete == AdapterInterface::FK_ACTION_SET_NULL) {
            $sql = sprintf(
                'UPDATE %s SET %s = NULL WHERE %s NOT IN (SELECT %s FROM %s WHERE %s IS NOT NULL)',
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
                $this->quoteIdentifier($refTableName),
                $this->quoteIdentifier($refColumnName),
            );
            $this->raw_query($sql);
        }

        return $this;
    }

    /**
     * Change table comment
     * SQLite doesn't support table comments
     */
    #[\Override]
    public function changeTableComment(string $tableName, string $comment, ?string $schemaName = null): mixed
    {
        // SQLite doesn't support table comments
        return null;
    }

    /**
     * Modify table structure
     */
    #[\Override]
    public function modifyTables(array $tables): self
    {
        $foreignKeys = $this->getForeignKeysTree();

        foreach ($tables as $table => $tableData) {
            if (!$this->isTableExists($table)) {
                continue;
            }

            foreach ($tableData['columns'] as $column => $columnDefinition) {
                if (!$this->tableColumnExists($table, $column)) {
                    continue;
                }

                $droppedKeys = [];
                foreach ($foreignKeys as $keyTable => $columns) {
                    foreach ($columns as $columnName => $keyOptions) {
                        if ($table == $keyOptions['REF_TABLE_NAME'] && $column == $keyOptions['REF_COLUMN_NAME']) {
                            $this->dropForeignKey($keyTable, $keyOptions['FK_NAME']);
                            $droppedKeys[] = $keyOptions;
                        }
                    }
                }

                $this->modifyColumn($table, $column, $columnDefinition);

                foreach ($droppedKeys as $options) {
                    unset($columnDefinition['identity'], $columnDefinition['primary'], $columnDefinition['comment']);

                    $onDelete = $options['ON_DELETE'];
                    $onUpdate = $options['ON_UPDATE'];

                    if ($onDelete == AdapterInterface::FK_ACTION_SET_NULL
                        || $onUpdate == AdapterInterface::FK_ACTION_SET_NULL
                    ) {
                        $columnDefinition['nullable'] = true;
                    }
                    $this->modifyColumn($options['TABLE_NAME'], $options['COLUMN_NAME'], $columnDefinition);
                    $this->addForeignKey(
                        $options['FK_NAME'],
                        $options['TABLE_NAME'],
                        $options['COLUMN_NAME'],
                        $options['REF_TABLE_NAME'],
                        $options['REF_COLUMN_NAME'],
                        $onDelete ?: AdapterInterface::FK_ACTION_NO_ACTION,
                        $onUpdate ?: AdapterInterface::FK_ACTION_NO_ACTION,
                    );
                }
            }

            if (!empty($tableData['comment'])) {
                $this->changeTableComment($table, $tableData['comment']);
            }
        }

        return $this;
    }
}
