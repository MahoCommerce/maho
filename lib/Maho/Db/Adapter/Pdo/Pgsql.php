<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter\Pdo;

use Maho\Db\Adapter\AbstractPdoAdapter;
use Maho\Db\Helper;

/**
 * PostgreSQL database adapter
 */
class Pgsql extends AbstractPdoAdapter
{
    // PostgreSQL-specific constants
    public const DDL_CACHE_PREFIX = 'DB_PDO_PGSQL_DDL';
    public const DDL_CACHE_TAG = 'DB_PDO_PGSQL_DDL';

    /**
     * Default class name for a DB statement.
     */
    protected string $_defaultStmtClass = \Maho\Db\Statement\Pdo\Pgsql::class;

    /**
     * Last inserted ID from INSERT ... RETURNING
     */
    protected string|int|null $_lastInsertedId = null;

    /**
     * Log file name for SQL debug data (override parent's default)
     */
    protected string $_debugFile = 'pdo_pgsql.log';

    /**
     * PostgreSQL column - Table DDL type pairs
     */
    protected array $_ddlColumnTypes = [
        \Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'boolean',
        \Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'smallint',
        \Maho\Db\Ddl\Table::TYPE_INTEGER       => 'integer',
        \Maho\Db\Ddl\Table::TYPE_BIGINT        => 'bigint',
        \Maho\Db\Ddl\Table::TYPE_FLOAT         => 'real',
        \Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'numeric',
        \Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'numeric',
        \Maho\Db\Ddl\Table::TYPE_DATE          => 'date',
        \Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'timestamp',
        \Maho\Db\Ddl\Table::TYPE_DATETIME      => 'timestamp',
        \Maho\Db\Ddl\Table::TYPE_TEXT          => 'text',
        \Maho\Db\Ddl\Table::TYPE_VARCHAR       => 'varchar',
        \Maho\Db\Ddl\Table::TYPE_BLOB          => 'bytea',
        \Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'bytea',
    ];

    /**
     * PostgreSQL interval units mapping
     */
    protected array $_intervalUnits = [
        self::INTERVAL_SECOND => 'SECOND',
        self::INTERVAL_MINUTE => 'MINUTE',
        self::INTERVAL_HOUR   => 'HOUR',
        self::INTERVAL_DAY    => 'DAY',
        self::INTERVAL_MONTH  => 'MONTH',
        self::INTERVAL_YEAR   => 'YEAR',
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
        return 'pdo_pgsql';
    }

    /**
     * Get the identifier quote character for PostgreSQL (double quote)
     */
    #[\Override]
    protected function getIdentifierQuoteChar(): string
    {
        return '"';
    }

    /**
     * Run PostgreSQL-specific initialization statements after connection
     */
    #[\Override]
    protected function _initConnection(): void
    {
        // Set client encoding to UTF8
        $this->_connection->executeStatement("SET client_encoding = 'UTF8'");
        // Set standard conforming strings
        $this->_connection->executeStatement('SET standard_conforming_strings = on');
    }

    /**
     * Get DDL cache prefix for PostgreSQL
     */
    #[\Override]
    protected function getDdlCachePrefix(): string
    {
        return self::DDL_CACHE_PREFIX;
    }

    /**
     * Get DDL cache tag for PostgreSQL
     */
    #[\Override]
    protected function getDdlCacheTag(): string
    {
        return self::DDL_CACHE_TAG;
    }

    // =========================================================================
    // PostgreSQL-Specific Connection Methods
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

        if (!extension_loaded('pdo_pgsql')) {
            throw new \RuntimeException('pdo_pgsql extension is not installed');
        }

        $this->_debugTimer();

        // Create Doctrine DBAL connection
        $params = [
            'driver' => 'pdo_pgsql',
            'user' => $this->_config['username'] ?? '',
            'password' => $this->_config['password'] ?? '',
            'dbname' => $this->_config['dbname'] ?? '',
            'host' => $this->_config['host'] ?? 'localhost',
            'charset' => $this->_config['charset'] ?? 'utf8',
        ];

        if (isset($this->_config['port'])) {
            $params['port'] = $this->_config['port'];
        }

        // PostgreSQL-specific: sslmode
        if (isset($this->_config['sslmode'])) {
            $params['sslmode'] = $this->_config['sslmode'];
        }

        // Disable GSS encryption to prevent crashes on macOS with PHP-FPM
        // (libpq checks for Kerberos credentials after fork which causes SIGSEGV)
        $params['gssencmode'] = $this->_config['gssencmode'] ?? 'disable';

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
    public function raw_query(string $sql): \Maho\Db\Statement\Pdo\Pgsql
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
                // Check to reconnect (PostgreSQL connection lost)
                if ($tries < 10 && str_contains($e->getMessage(), 'server closed the connection unexpectedly')) {
                    $retry = true;
                    $tries++;
                    $this->_connection = null;
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
    public function query(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): \Maho\Db\Statement\Pdo\Pgsql
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
            $result = new \Maho\Db\Statement\Pdo\Pgsql($this, $result);
        } catch (\Exception $e) {
            $this->_debugStat(self::DEBUG_QUERY, $sql, $bind);

            // Detect implicit rollback - PostgreSQL deadlock detection
            if ($this->_transactionLevel > 0
                && str_contains($e->getMessage(), 'deadlock detected')
            ) {
                if ($this->_debug) {
                    $this->_debugWriteToFile('IMPLICIT ROLLBACK AFTER DEADLOCK');
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
    // SQL Generation Methods - PostgreSQL-specific implementations
    // =========================================================================

    /**
     * Generate fragment of SQL, that check condition and return true or false value
     * Uses CASE WHEN instead of MySQL's IF()
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
     * Returns valid COALESCE expression (PostgreSQL equivalent of IFNULL)
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
     * PostgreSQL requires casting to numeric for ROUND with precision.
     *
     * @param string $expression Expression to round
     * @param int $precision Number of decimal places
     */
    #[\Override]
    public function getRoundSql(string $expression, int $precision = 0): \Maho\Db\Expr
    {
        // PostgreSQL's ROUND(double precision, integer) doesn't exist
        // We must cast to numeric first
        return new \Maho\Db\Expr(sprintf('ROUND((%s)::numeric, %d)', $expression, $precision));
    }

    /**
     * Generate fragment of SQL to cast a value to text for comparison.
     * PostgreSQL requires explicit type casting.
     *
     * @param string $expression Expression to cast
     */
    #[\Override]
    public function getCastToTextSql(string $expression): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('(%s)::text', $expression));
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
     * Uses || operator with quoted separator values
     */
    #[\Override]
    protected function getConcatWithSeparatorSql(array $data, string $separator): \Maho\Db\Expr
    {
        // With separator, build expression like: (a || 'sep' || b || 'sep' || c)
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
     * Generate LEAST SQL
     */
    #[\Override]
    public function getLeastSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('LEAST(%s)', implode(', ', $data)));
    }

    /**
     * Generate GREATEST SQL
     */
    #[\Override]
    public function getGreatestSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('GREATEST(%s)', implode(', ', $data)));
    }

    /**
     * Get PostgreSQL interval unit SQL fragment
     */
    protected function _getIntervalUnitSql(int|string $interval, string $unit): string
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        return sprintf("INTERVAL '%d %s'", $interval, $this->_intervalUnits[$unit]);
    }

    /**
     * Add time values (intervals) to a date value
     */
    #[\Override]
    public function getDateAddSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
    {
        // Cast to timestamp to avoid PostgreSQL ambiguity, then cast back to date for clean output
        $expr = sprintf('((%s)::timestamp + %s)::date', $date, $this->_getIntervalUnitSql($interval, $unit));
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Subtract time values (intervals) from a date value
     */
    #[\Override]
    public function getDateSubSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
    {
        // Cast to timestamp to avoid PostgreSQL ambiguity, then cast back to date for clean output
        $expr = sprintf('((%s)::timestamp - %s)::date', $date, $this->_getIntervalUnitSql($interval, $unit));
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Format date using TO_CHAR (PostgreSQL equivalent of DATE_FORMAT)
     *
     * Converts MySQL format specifiers to PostgreSQL TO_CHAR format:
     * %H -> HH24   Hour (00..23)
     * %i -> MI     Minutes (00..59)
     * %s -> SS     Seconds (00..59)
     * %d -> DD     Day of month (01..31)
     * %m -> MM     Month (01..12)
     * %Y -> YYYY   Year, four digits
     */
    #[\Override]
    public function getDateFormatSql(\Maho\Db\Expr|string $date, string $format): \Maho\Db\Expr
    {
        // Convert MySQL format to PostgreSQL TO_CHAR format
        $pgFormat = str_replace(
            ['%Y', '%m', '%d', '%H', '%i', '%s'],
            ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
            $format,
        );

        // Cast to timestamp to avoid PostgreSQL ambiguity
        $expr = sprintf("TO_CHAR((%s)::timestamp, '%s')", $date, $pgFormat);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Get SQL expression for days until next annual occurrence of a date
     *
     * Uses PostgreSQL's EXTRACT, MAKE_DATE, and date arithmetic.
     * Handles leap year birthdays (Feb 29) by using Feb 28 in non-leap years.
     *
     * @param \Maho\Db\Expr|string $dateField The date field containing the anniversary
     * @param string $referenceDate The reference date (usually today)
     */
    #[\Override]
    public function getDaysUntilAnniversarySql(\Maho\Db\Expr|string $dateField, string $referenceDate): \Maho\Db\Expr
    {
        $refDate = $this->quote($referenceDate);

        // Helper expression to safely create a date, adjusting Feb 29 to Feb 28 in non-leap years
        // This prevents "date/time field value out of range" errors
        $makeSafeDate = function (string $yearExpr) use ($dateField): string {
            return "MAKE_DATE(
                {$yearExpr}::int,
                EXTRACT(MONTH FROM ({$dateField})::timestamp)::int,
                CASE
                    WHEN EXTRACT(MONTH FROM ({$dateField})::timestamp) = 2
                        AND EXTRACT(DAY FROM ({$dateField})::timestamp) = 29
                        AND NOT (({$yearExpr} % 4 = 0 AND {$yearExpr} % 100 != 0) OR {$yearExpr} % 400 = 0)
                    THEN 28
                    ELSE EXTRACT(DAY FROM ({$dateField})::timestamp)::int
                END
            )";
        };

        $currentYear = "EXTRACT(YEAR FROM {$refDate}::timestamp)";
        $nextYear = "(EXTRACT(YEAR FROM {$refDate}::timestamp) + 1)";

        $sql = "CASE
            WHEN EXTRACT(YEAR FROM ({$dateField})::timestamp) > EXTRACT(YEAR FROM {$refDate}::timestamp) THEN
                ({$makeSafeDate($currentYear)} - {$refDate}::date)
            ELSE
                CASE
                    WHEN EXTRACT(DOY FROM {$refDate}::timestamp) > EXTRACT(DOY FROM ({$dateField})::timestamp) THEN
                        ({$makeSafeDate($nextYear)} - {$refDate}::date)
                    ELSE
                        ({$makeSafeDate($currentYear)} - {$refDate}::date)
                END
            END";

        return new \Maho\Db\Expr($sql);
    }

    /**
     * Extract the date part of a date or datetime expression
     */
    #[\Override]
    public function getDatePartSql(\Maho\Db\Expr|string $date): \Maho\Db\Expr
    {
        // Cast to timestamp first to avoid ambiguity, then to date
        return new \Maho\Db\Expr(sprintf('(%s)::timestamp::date', $date));
    }

    /**
     * Prepare standard deviation sql function
     */
    #[\Override]
    public function getStandardDeviationSql(\Maho\Db\Expr|string $expressionField): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('STDDEV_SAMP(%s)', $expressionField));
    }

    /**
     * Extract part of a date
     */
    #[\Override]
    public function getDateExtractSql(\Maho\Db\Expr|string $date, string $unit): \Maho\Db\Expr
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        // Cast to timestamp to avoid PostgreSQL ambiguity
        $expr = sprintf('EXTRACT(%s FROM (%s)::timestamp)', $this->_intervalUnits[$unit], $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Convert date format to unix timestamp
     */
    #[\Override]
    public function getUnixTimestamp(string|\Maho\Db\Expr $date): \Maho\Db\Expr
    {
        // Cast to timestamp to avoid PostgreSQL ambiguity
        return new \Maho\Db\Expr(sprintf('EXTRACT(EPOCH FROM (%s)::timestamp)::integer', $date));
    }

    /**
     * Convert unix time to date format
     */
    #[\Override]
    public function fromUnixtime(int|\Maho\Db\Expr $timestamp): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('TO_TIMESTAMP(%s)', $timestamp));
    }

    /**
     * Get SQL expression for timestamp difference in seconds
     *
     * Returns the difference between two timestamps in seconds (end - start).
     */
    #[\Override]
    public function getTimestampDiffExpr(string $startTimestamp, string $endTimestamp): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('EXTRACT(EPOCH FROM (%s - %s))::integer', $endTimestamp, $startTimestamp));
    }

    /**
     * Get SQL expression for concatenating grouped values
     */
    #[\Override]
    public function getGroupConcatExpr(string $expression, string $separator = ','): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf("STRING_AGG(%s::text, '%s')", $expression, $separator));
    }

    /**
     * Get SQL expression for FIND_IN_SET functionality
     */
    #[\Override]
    public function getFindInSetExpr(string $needle, string $haystack): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('%s::text = ANY(string_to_array(%s, \',\'))', $needle, $haystack));
    }

    /**
     * Get SQL expression for Unix timestamp
     * PostgreSQL uses EXTRACT(EPOCH FROM ...) instead of UNIX_TIMESTAMP()
     */
    #[\Override]
    public function getUnixTimestampExpr(?string $timestamp = null): \Maho\Db\Expr
    {
        if ($timestamp === null) {
            return new \Maho\Db\Expr('EXTRACT(EPOCH FROM NOW())::bigint');
        }
        // Cast the timestamp to timestamp type to avoid ambiguity
        return new \Maho\Db\Expr(sprintf('EXTRACT(EPOCH FROM (%s)::timestamp)::bigint', $timestamp));
    }

    /**
     * Extract a scalar value from a JSON column at a given path (PostgreSQL)
     */
    #[\Override]
    public function getJsonExtractExpr(string $column, string $path): \Maho\Db\Expr
    {
        $keys = $this->convertJsonPathToPostgresKeys($path);

        if ($keys === null) {
            throw new \InvalidArgumentException('Wildcard paths are not supported in getJsonExtractExpr().');
        }

        if (count($keys) === 1) {
            return new \Maho\Db\Expr(sprintf(
                '%s::jsonb ->> %s',
                $column,
                $this->quote($keys[0]),
            ));
        }

        $escapedKeys = array_map(fn($k) => str_replace("'", "''", $k), $keys);
        $pgPath = '{' . implode(',', $escapedKeys) . '}';
        return new \Maho\Db\Expr(sprintf(
            "%s::jsonb #>> '%s'",
            $column,
            $pgPath,
        ));
    }

    /**
     * Search for a string value within a JSON column (PostgreSQL)
     */
    #[\Override]
    public function getJsonSearchExpr(string $column, string $value, string $path): \Maho\Db\Expr
    {
        if (str_contains($path, '$**')) {
            $pgPath = $this->convertJsonPathToPostgresJsonpath($path, $value);
            return new \Maho\Db\Expr(sprintf(
                'jsonb_path_exists(%s::jsonb, %s)',
                $column,
                $this->quote($pgPath),
            ));
        }

        $extractExpr = $this->getJsonExtractExpr($column, $path);
        return new \Maho\Db\Expr(sprintf('%s = %s', $extractExpr, $this->quote($value)));
    }

    /**
     * Check if a JSON column contains a specific JSON value (PostgreSQL)
     */
    #[\Override]
    public function getJsonContainsExpr(string $column, string $value, ?string $path = null): \Maho\Db\Expr
    {
        if ($path !== null && str_contains($path, '$**')) {
            throw new \InvalidArgumentException('Wildcard paths are not supported in getJsonContainsExpr(). Use getJsonSearchExpr() instead.');
        }

        if ($path !== null) {
            $keys = $this->convertJsonPathToPostgresKeys($path);
            if ($keys === null) {
                throw new \InvalidArgumentException('Invalid path for getJsonContainsExpr().');
            }

            if (count($keys) === 1) {
                $extract = sprintf('%s::jsonb -> %s', $column, $this->quote($keys[0]));
            } else {
                $escapedKeys = array_map(fn($k) => str_replace("'", "''", $k), $keys);
                $pgPath = '{' . implode(',', $escapedKeys) . '}';
                $extract = sprintf("%s::jsonb #> '%s'", $column, $pgPath);
            }

            return new \Maho\Db\Expr(sprintf(
                '(%s) @> %s::jsonb',
                $extract,
                $this->quote($value),
            ));
        }

        return new \Maho\Db\Expr(sprintf(
            '%s::jsonb @> %s::jsonb',
            $column,
            $this->quote($value),
        ));
    }

    /**
     * Convert a MySQL-style JSON path ($.key1.key2) to an array of keys for PostgreSQL #>>/# > operators
     *
     * Handles array index notation (e.g., '$.tags[1]' → ['tags', '1']).
     *
     * @return string[]|null Returns null for wildcard paths
     */
    private function convertJsonPathToPostgresKeys(string $path): ?array
    {
        if (str_contains($path, '**')) {
            return null;
        }

        if (str_starts_with($path, '$.')) {
            $path = substr($path, 2);
        } elseif (str_starts_with($path, '$')) {
            $path = substr($path, 1);
        }

        if ($path === '') {
            return [];
        }

        $segments = explode('.', $path);
        $keys = [];
        foreach ($segments as $segment) {
            if (preg_match('/^(.+?)\[(\d+)]$/', $segment, $matches)) {
                $keys[] = $matches[1];
                $keys[] = $matches[2];
            } else {
                $keys[] = $segment;
            }
        }

        return $keys;
    }

    /**
     * Convert a MySQL-style JSON path with wildcard ($**.attribute) to PostgreSQL jsonpath syntax
     *
     * Converts e.g. '$**.attribute' to '$.**."attribute" ? (@ == "value")'
     * Escapes backslashes and double quotes for jsonpath string literals.
     * SQL-level escaping is handled by the caller via $this->quote().
     */
    private function convertJsonPathToPostgresJsonpath(string $path, string $value): string
    {
        // Replace $** with $.** and quote attribute names
        $pgPath = str_replace('$**', '$.**', $path);

        // Quote the last segment (the attribute name)
        $lastDot = strrpos($pgPath, '.');
        if ($lastDot !== false) {
            $attr = substr($pgPath, $lastDot + 1);
            $pgPath = substr($pgPath, 0, $lastDot + 1) . '"' . $attr . '"';
        }

        // Escape backslashes and double quotes for jsonpath string literal
        $escapedValue = str_replace('\\', '\\\\', $value);
        $escapedValue = str_replace('"', '\\"', $escapedValue);

        return $pgPath . ' ? (@ == "' . $escapedValue . '")';
    }

    // =========================================================================
    // Insert Methods - PostgreSQL-specific implementations
    // =========================================================================

    /**
     * Inserts a table row with specified data using RETURNING clause
     *
     * PostgreSQL's RETURNING clause allows us to get the inserted ID directly
     * from the INSERT statement, avoiding a separate sequence query.
     *
     * @param string|array|\Maho\Db\Select $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    #[\Override]
    public function insert(string|array|\Maho\Db\Select $table, array $bind): int
    {
        // Reset last inserted ID
        $this->_lastInsertedId = null;

        // Extract and quote col names from the array keys
        $cols = [];
        $vals = [];
        $params = [];
        foreach ($bind as $col => $value) {
            $cols[] = $this->quoteIdentifier($col);
            if ($value instanceof \Maho\Db\Expr) {
                $vals[] = $value->__toString();
            } else {
                $vals[] = '?';
                $params[] = $value;
            }
        }

        // Get the primary key column for RETURNING clause
        $tableName = is_array($table) ? reset($table) : (string) $table;
        $primaryKey = $this->_getPrimaryKeyColumns($tableName);
        $returningColumn = empty($primaryKey) ? null : $primaryKey[0];

        // Build the statement
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES(%s)',
            $this->quoteIdentifier($table),
            implode(', ', $cols),
            implode(', ', $vals),
        );

        // Add RETURNING clause if we have a primary key
        if ($returningColumn !== null) {
            $sql .= sprintf(' RETURNING %s', $this->quoteIdentifier($returningColumn));
        }

        // Execute the statement
        $stmt = $this->query($sql, $params);

        // Capture the returned ID if available
        if ($returningColumn !== null) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && isset($row[$returningColumn])) {
                $this->_lastInsertedId = $row[$returningColumn];
            }
        }

        return 1; // INSERT always affects 1 row on success
    }

    /**
     * Inserts a table row with ON CONFLICT DO UPDATE (PostgreSQL equivalent of ON DUPLICATE KEY UPDATE)
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

        // Get the columns to use for ON CONFLICT clause
        // First, try to find a unique constraint that matches the non-update columns
        $conflictColumns = $this->_getConflictColumns($table, $cols, $fields);
        if (empty($conflictColumns)) {
            // Fall back to primary key
            $conflictColumns = $this->_getPrimaryKeyColumns($table);
        }
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
                    $value = sprintf('EXCLUDED.%s', $this->quoteIdentifier($v));
                } elseif (is_numeric($v)) {
                    $value = $this->quoteInto('?', $v);
                }
            } elseif (is_string($v)) {
                $value = sprintf('EXCLUDED.%s', $this->quoteIdentifier($v));
                $field = $this->quoteIdentifier($v);
            }

            if ($field && $value) {
                $updateFields[] = sprintf('%s = %s', $field, $value);
            }
        }

        // Reset last inserted ID
        $this->_lastInsertedId = null;

        // Get the primary key column for RETURNING clause
        $tableName = is_array($table) ? reset($table) : (string) $table;
        $primaryKey = $this->_getPrimaryKeyColumns($tableName);
        $returningColumn = empty($primaryKey) ? null : $primaryKey[0];

        $insertSql = $this->_getInsertSqlQuery($table, $cols, $values);

        if ($updateFields) {
            $conflictCols = array_map([$this, 'quoteIdentifier'], $conflictColumns);
            $insertSql .= sprintf(
                ' ON CONFLICT (%s) DO UPDATE SET %s',
                implode(', ', $conflictCols),
                implode(', ', $updateFields),
            );
        }

        // Add RETURNING clause if we have a primary key
        if ($returningColumn !== null) {
            $insertSql .= sprintf(' RETURNING %s', $this->quoteIdentifier($returningColumn));
        }

        $stmt = $this->query($insertSql, array_values($bind));

        // Capture the returned ID if available
        if ($returningColumn !== null) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && isset($row[$returningColumn])) {
                $this->_lastInsertedId = $row[$returningColumn];
            }
        }

        return $stmt->rowCount() ?: 1;
    }

    /**
     * Get primary key columns for a table
     */
    protected function _getPrimaryKeyColumns(string|array|\Maho\Db\Select $table): array
    {
        $tableName = is_array($table) ? reset($table) : (string) $table;
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
     * Get columns to use for ON CONFLICT clause
     *
     * Finds a unique constraint that covers the "key" columns (inserted columns minus update columns)
     *
     * @param array $insertCols Columns being inserted
     * @param array $updateFields Fields that will be updated on conflict
     * @return array Columns to use for ON CONFLICT, empty if none found
     */
    protected function _getConflictColumns(string|array|\Maho\Db\Select $table, array $insertCols, array $updateFields): array
    {
        $tableName = is_array($table) ? reset($table) : (string) $table;

        // Determine which columns are being updated
        $updateColNames = [];
        foreach ($updateFields as $k => $v) {
            if (!is_numeric($k)) {
                $updateColNames[] = $k;
            } elseif (is_string($v)) {
                $updateColNames[] = $v;
            }
        }

        // Get all unique indexes for the table
        $indexes = $this->getIndexList($tableName);

        // Find unique indexes whose columns are ALL present in the INSERT columns
        $candidateIndexes = [];
        foreach ($indexes as $indexName => $index) {
            $indexType = $index['INDEX_TYPE'] ?? $index['type'] ?? '';
            if ($indexType !== \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE) {
                continue;
            }

            $indexColumns = $index['COLUMNS_LIST'] ?? $index['fields'] ?? [];
            if (empty($indexColumns)) {
                continue;
            }

            // Check if ALL index columns are present in the INSERT columns
            $missingCols = array_diff($indexColumns, $insertCols);
            if (empty($missingCols)) {
                // Count how many index columns overlap with update columns
                $updateOverlap = count(array_intersect($indexColumns, $updateColNames));
                $candidateIndexes[$indexName] = [
                    'columns' => $indexColumns,
                    'overlap' => $updateOverlap,
                    'size' => count($indexColumns),
                ];
            }
        }

        if (empty($candidateIndexes)) {
            return [];
        }

        // Prefer indexes with NO overlap with update columns (these are natural "key" columns)
        // Then prefer smaller indexes (fewer columns)
        uasort($candidateIndexes, function ($a, $b) {
            if ($a['overlap'] !== $b['overlap']) {
                return $a['overlap'] - $b['overlap']; // Less overlap is better
            }
            return $a['size'] - $b['size']; // Smaller is better
        });

        $best = reset($candidateIndexes);
        return $best['columns'];
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
     * Inserts a table row with ON CONFLICT DO NOTHING (PostgreSQL equivalent of INSERT IGNORE)
     *
     * Uses RETURNING clause to capture the inserted ID if the row is actually inserted.
     * If the row conflicts and is ignored, no ID is returned.
     */
    #[\Override]
    public function insertIgnore(string|array|\Maho\Db\Select $table, array $bind): int
    {
        // Reset last inserted ID
        $this->_lastInsertedId = null;

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

        // Get the primary key column for RETURNING clause
        $tableName = is_array($table) ? reset($table) : (string) $table;
        $primaryKey = $this->_getPrimaryKeyColumns($tableName);
        $returningColumn = empty($primaryKey) ? null : $primaryKey[0];

        $sql = 'INSERT INTO '
            . $this->quoteIdentifier($table, true)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES (' . implode(', ', $vals) . ') '
            . 'ON CONFLICT DO NOTHING';

        // Add RETURNING clause if we have a primary key
        if ($returningColumn !== null) {
            $sql .= sprintf(' RETURNING %s', $this->quoteIdentifier($returningColumn));
        }

        $bind = array_values($bind);
        $stmt = $this->query($sql, $bind);

        // Capture the returned ID if available (only returns a row if insert succeeded)
        if ($returningColumn !== null) {
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && isset($row[$returningColumn])) {
                $this->_lastInsertedId = $row[$returningColumn];
            }
        }

        return $stmt->rowCount();
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * Uses the cached value from INSERT ... RETURNING if available,
     * otherwise falls back to sequence query for compatibility.
     */
    #[\Override]
    public function lastInsertId(?string $tableName = null, ?string $primaryKey = null): string|int
    {
        // Return cached value from RETURNING clause if available
        if ($this->_lastInsertedId !== null) {
            $id = $this->_lastInsertedId;
            $this->_lastInsertedId = null; // Clear after retrieval
            return $id;
        }

        $this->_connect();

        if ($tableName !== null && $primaryKey !== null) {
            // Fallback: PostgreSQL requires sequence name - query it directly
            $sequenceName = sprintf('%s_%s_seq', $tableName, $primaryKey);
            $result = $this->fetchOne(sprintf('SELECT currval(%s)', $this->quote($sequenceName)));
            return $result ?: 0;
        }

        return $this->_connection->lastInsertId();
    }

    /**
     * Acquire a named lock using PostgreSQL advisory locks
     *
     * PostgreSQL advisory locks use bigint keys, so we convert the lock name
     * to a hash. We use pg_try_advisory_lock with a loop for timeout support.
     */
    #[\Override]
    public function getLock(string $lockName, int $timeout = 0): bool
    {
        $this->_connect();
        $lockKey = crc32($lockName);

        // If no timeout, try once and return immediately
        if ($timeout <= 0) {
            return (bool) $this->fetchOne('SELECT pg_try_advisory_lock(?)', [$lockKey]);
        }

        // With timeout, poll until we get the lock or timeout expires
        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            if ($this->fetchOne('SELECT pg_try_advisory_lock(?)', [$lockKey])) {
                return true;
            }
            usleep(100000); // Wait 100ms before retrying
        }

        return false;
    }

    /**
     * Release a named lock using PostgreSQL advisory locks
     */
    #[\Override]
    public function releaseLock(string $lockName): bool
    {
        $this->_connect();
        $lockKey = crc32($lockName);
        return (bool) $this->fetchOne('SELECT pg_advisory_unlock(?)', [$lockKey]);
    }

    /**
     * Check if a named lock is currently held using PostgreSQL advisory locks
     *
     * Note: PostgreSQL advisory locks don't have a direct equivalent to MySQL's IS_USED_LOCK.
     * We check if the lock exists in pg_locks for the current database.
     */
    #[\Override]
    public function isLocked(string $lockName): bool
    {
        $this->_connect();
        $lockKey = crc32($lockName);
        // Check if an advisory lock with this key exists in pg_locks
        $result = $this->fetchOne(
            'SELECT 1 FROM pg_locks WHERE locktype = ? AND objid = ? LIMIT 1',
            ['advisory', $lockKey],
        );
        return (bool) $result;
    }

    // =========================================================================
    // DDL Methods - PostgreSQL-specific implementations
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
     */
    #[\Override]
    public function dropForeignKey(string $tableName, string $fkName, ?string $schemaName = null): self
    {
        // PostgreSQL max identifier length is 63 characters - truncate for comparison
        $pgMaxIdentLen = 63;

        $foreignKeys = $this->getForeignKeys($tableName, $schemaName);

        // Find the FK by comparing names case-insensitively
        // This handles PostgreSQL's lowercase identifier storage and truncation
        $fkNameLower = strtolower(substr($fkName, 0, $pgMaxIdentLen));
        $foundKey = null;
        foreach ($foreignKeys as $key => $fkData) {
            // Compare truncated names (PostgreSQL silently truncates identifiers)
            $actualName = strtolower(substr($fkData['FK_NAME'], 0, $pgMaxIdentLen));
            if ($actualName === $fkNameLower) {
                $foundKey = $key;
                break;
            }
        }

        if ($foundKey !== null) {
            $sql = sprintf(
                'ALTER TABLE %s DROP CONSTRAINT %s',
                $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
                $this->quoteIdentifier($foreignKeys[$foundKey]['FK_NAME']),
            );
            $this->resetDdlCache($tableName, $schemaName);
            $this->raw_query($sql);
        }

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
            if ($column['COLUMN_NAME'] == $columnName) {
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
     */
    #[\Override]
    public function dropColumn(string $tableName, string $columnName, ?string $schemaName = null): bool
    {
        if (!$this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return true;
        }

        $sql = sprintf(
            'ALTER TABLE %s DROP COLUMN %s CASCADE',
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

        // PostgreSQL requires separate commands for rename and alter
        if ($oldColumnName !== $newColumnName) {
            $sql = sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $quotedTable,
                $this->quoteIdentifier($oldColumnName),
                $this->quoteIdentifier($newColumnName),
            );
            $this->raw_query($sql);
            // Reset cache after rename so modifyColumn can find the new column name
            $this->resetDdlCache($tableName, $schemaName);
        }

        // Use modifyColumn to handle the type/nullability/default changes properly
        if ($definition) {
            $this->modifyColumn($tableName, $newColumnName, $definition, $flushData, $schemaName);
        }

        return $this;
    }

    /**
     * Modify the column definition
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function modifyColumn(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self
    {
        if (!$this->tableColumnExists($tableName, $columnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Column "%s" does not exist in table "%s".', $columnName, $tableName));
        }

        $qualifiedTable = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $quotedColumn = $this->quoteIdentifier($columnName);

        // If definition is an array, we can handle type, nullable, and default separately
        if (is_array($definition)) {
            $definition = array_change_key_case($definition, CASE_UPPER);
            $ddlType = $this->_getDdlType($definition);

            // Get the type-only definition (without NULL/NOT NULL and DEFAULT)
            $typeOnly = $this->_getColumnTypeOnly($definition, $ddlType);

            // Change the column type
            $this->raw_query(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s USING %s::%s',
                $qualifiedTable,
                $quotedColumn,
                $typeOnly,
                $quotedColumn,
                $typeOnly,
            ));

            // Handle nullability
            $nullable = !isset($definition['NULLABLE']) || (bool) $definition['NULLABLE'];
            if ($nullable) {
                $this->raw_query(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL',
                    $qualifiedTable,
                    $quotedColumn,
                ));
            } else {
                $this->raw_query(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s SET NOT NULL',
                    $qualifiedTable,
                    $quotedColumn,
                ));
            }

            // Handle default value
            if (array_key_exists('DEFAULT', $definition)) {
                $default = $definition['DEFAULT'];
                if ($default === null || $default === '') {
                    $this->raw_query(sprintf(
                        'ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT',
                        $qualifiedTable,
                        $quotedColumn,
                    ));
                } else {
                    $this->raw_query(sprintf(
                        'ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s',
                        $qualifiedTable,
                        $quotedColumn,
                        $this->quote($default),
                    ));
                }
            }
        } else {
            // String definition - parse out the type only (strip NULL/NOT NULL/DEFAULT/COMMENT)
            // Handle various MySQL patterns like:
            // - "VARCHAR(255) default NULL COMMENT 'Remote Ip'"
            // - "VARCHAR(255) NOT NULL DEFAULT ''"
            // - "INT(11) UNSIGNED NOT NULL"
            $typeOnly = $definition;

            // Remove COMMENT clause (MySQL-specific)
            $typeOnly = preg_replace('/\s+COMMENT\s+[\'"].*?[\'"]\s*$/i', '', $typeOnly);

            // Remove DEFAULT clause
            $typeOnly = preg_replace('/\s+DEFAULT\s+(NULL|[\'"].*?[\'"]|[\d.]+)\s*/i', ' ', $typeOnly);

            // Remove NULL / NOT NULL
            $typeOnly = preg_replace('/\s+(NOT\s+)?NULL\s*/i', ' ', $typeOnly);

            // Remove UNSIGNED (PostgreSQL doesn't support it but we can ignore it)
            $typeOnly = preg_replace('/\s+UNSIGNED\s*/i', ' ', $typeOnly);

            $typeOnly = trim($typeOnly);

            $this->raw_query(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s USING %s::%s',
                $qualifiedTable,
                $quotedColumn,
                $typeOnly,
                $quotedColumn,
                $typeOnly,
            ));
        }

        $this->resetDdlCache($tableName, $schemaName);

        return $this;
    }

    /**
     * Get column type definition only (without NULL/NOT NULL and DEFAULT)
     */
    protected function _getColumnTypeOnly(array $options, ?string $ddlType = null): string
    {
        if ($ddlType === null) {
            $ddlType = $this->_getDdlType($options);
        }

        if (empty($ddlType) || !isset($this->_ddlColumnTypes[$ddlType])) {
            throw new \Maho\Db\Exception('Invalid column definition data');
        }

        $cType = $this->_ddlColumnTypes[$ddlType];

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
                if (empty($options['LENGTH'])) {
                    $length = \Maho\Db\Ddl\Table::DEFAULT_TEXT_SIZE;
                } else {
                    $length = $this->_parseTextSize($options['LENGTH']);
                }
                if ($length <= 65535) {
                    $cType = sprintf('varchar(%d)', $length);
                } else {
                    $cType = 'text';
                }
                break;
        }

        return $cType;
    }

    /**
     * Show table status (PostgreSQL implementation)
     */
    #[\Override]
    public function showTableStatus(string $tableName, ?string $schemaName = null): array|false
    {
        $schema = $schemaName ?? 'public';
        $query = sprintf(
            "SELECT
                c.relname AS \"Name\",
                pg_total_relation_size(c.oid) AS \"Data_length\",
                pg_indexes_size(c.oid) AS \"Index_length\",
                s.n_live_tup AS \"Rows\"
            FROM pg_class c
            LEFT JOIN pg_stat_user_tables s ON c.relname = s.relname
            WHERE c.relkind = 'r' AND c.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = %s)
            AND c.relname = %s",
            $this->quote($schema),
            $this->quote($tableName),
        );

        return $this->raw_fetchRow($query);
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
            case \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE:
                $prefix = 'unq_';
                $shortPrefix = 'u_';
                break;
            case \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT:
                $prefix = 'fti_';
                $shortPrefix = 'f_';
                break;
            case \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX:
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
     * PostgreSQL does not have an equivalent to MySQL's `ALTER TABLE ... DISABLE KEYS`.
     * This method is a no-op for PostgreSQL.
     *
     * **MySQL behavior:** `DISABLE KEYS` tells MySQL to stop updating non-unique indexes
     * for MyISAM tables, which speeds up bulk inserts. Indexes are rebuilt when
     * `ENABLE KEYS` is called.
     *
     * **PostgreSQL alternative strategies for bulk inserts:**
     * - Drop indexes before bulk insert, recreate after (manual)
     * - Use COPY instead of INSERT for large data loads
     * - Increase `maintenance_work_mem` temporarily
     * - Disable triggers with `ALTER TABLE ... DISABLE TRIGGER ALL`
     * - Use unlogged tables for temporary bulk operations
     *
     * Since these alternatives require different approaches and have different
     * trade-offs, this method intentionally does nothing rather than making
     * assumptions about the desired optimization strategy.
     *
     * @see https://www.postgresql.org/docs/current/populate.html
     */
    #[\Override]
    public function disableTableKeys(string $tableName, ?string $schemaName = null): self
    {
        // No-op for PostgreSQL - see docblock for alternative strategies
        return $this;
    }

    /**
     * Re-create missing indexes
     *
     * PostgreSQL does not have an equivalent to MySQL's `ALTER TABLE ... ENABLE KEYS`.
     * This method is a no-op for PostgreSQL.
     *
     * **MySQL behavior:** `ENABLE KEYS` rebuilds all non-unique indexes that were
     * disabled with `DISABLE KEYS`. This is faster than updating indexes row-by-row
     * during bulk inserts.
     *
     * **PostgreSQL notes:**
     * - PostgreSQL indexes are always kept up-to-date (no disable/enable mechanism)
     * - For bulk insert optimization, indexes should be dropped and recreated manually
     * - Use `REINDEX TABLE tablename` to rebuild corrupted or bloated indexes
     * - Consider `CREATE INDEX CONCURRENTLY` for production environments
     *
     * @see https://www.postgresql.org/docs/current/sql-reindex.html
     */
    #[\Override]
    public function enableTableKeys(string $tableName, ?string $schemaName = null): self
    {
        // No-op for PostgreSQL - see docblock for alternative strategies
        return $this;
    }

    /**
     * Get insert from Select object query
     */
    #[\Override]
    public function insertFromSelect(\Maho\Db\Select $select, string $table, array $fields = [], bool|int $mode = false): string
    {
        $query = sprintf('INSERT INTO %s', $this->quoteIdentifier($table));
        if ($fields) {
            $columns = array_map([$this, 'quoteIdentifier'], $fields);
            $query = sprintf('%s (%s)', $query, implode(', ', $columns));
        }

        $query = sprintf('%s %s', $query, $select->assemble());

        if ($mode == self::INSERT_IGNORE) {
            $query .= ' ON CONFLICT DO NOTHING';
        } elseif ($mode == self::INSERT_ON_DUPLICATE) {
            if (!$fields) {
                $describe = $this->describeTable($table);
                foreach ($describe as $column) {
                    if ($column['PRIMARY'] === false) {
                        $fields[] = $column['COLUMN_NAME'];
                    }
                }
            }

            $primaryKeys = $this->_getPrimaryKeyColumns($table);
            if (!empty($primaryKeys)) {
                $update = [];
                foreach ($fields as $k => $v) {
                    $field = $value = null;
                    if (!is_numeric($k)) {
                        $field = $this->quoteIdentifier($k);
                        if ($v instanceof \Maho\Db\Expr) {
                            $value = $v->__toString();
                        } elseif (is_string($v)) {
                            $value = sprintf('EXCLUDED.%s', $this->quoteIdentifier($v));
                        }
                    } elseif (is_string($v)) {
                        $field = $this->quoteIdentifier($v);
                        $value = sprintf('EXCLUDED.%s', $this->quoteIdentifier($v));
                    }

                    if ($field && $value) {
                        $update[] = sprintf('%s = %s', $field, $value);
                    }
                }

                if ($update) {
                    $conflictCols = array_map([$this, 'quoteIdentifier'], $primaryKeys);
                    $query .= sprintf(
                        ' ON CONFLICT (%s) DO UPDATE SET %s',
                        implode(', ', $conflictCols),
                        implode(', ', $update),
                    );
                }
            }
        }

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

        // PostgreSQL UPDATE ... FROM syntax
        $query = sprintf('UPDATE %s', $this->quoteIdentifier($tableName));

        if ($tableAlias !== $tableName) {
            $query .= sprintf(' AS %s', $this->quoteIdentifier($tableAlias));
        }

        // Get target table column types for proper casting in PostgreSQL
        // PostgreSQL requires explicit type casting for CASE expressions and other dynamic values
        $tableColumns = $this->describeTable($tableName);
        $columnTypes = [];
        foreach ($tableColumns as $colName => $colInfo) {
            $columnTypes[strtolower($colName)] = $colInfo['DATA_TYPE'] ?? null;
        }

        // Build SET clause from select columns
        $columns = $select->getPart(\Maho\Db\Select::COLUMNS);
        $setClauses = [];
        foreach ($columns as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if ($alias) {
                $targetType = $columnTypes[strtolower($alias)] ?? null;

                // Handle Expr objects - they contain the full expression already
                if ($column instanceof \Maho\Db\Expr) {
                    $valueExpr = $column->__toString();
                    // PostgreSQL needs explicit casting for CASE expressions to numeric types
                    $valueExpr = $this->_castExpressionToColumnType($valueExpr, $targetType);
                    $setClauses[] = sprintf(
                        '%s = %s',
                        $this->quoteIdentifier($alias),
                        $valueExpr,
                    );
                } elseif ($correlationName) {
                    // Qualified column reference (table.column)
                    $setClauses[] = sprintf(
                        '%s = %s.%s',
                        $this->quoteIdentifier($alias),
                        $this->quoteIdentifier($correlationName),
                        $this->quoteIdentifier($column),
                    );
                } else {
                    // Unqualified column reference
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

        // Add FROM clause from select's join and collect join conditions for WHERE
        $from = $select->getPart(\Maho\Db\Select::FROM);
        $fromTables = [];
        $joinConditions = [];
        foreach ($from as $alias => $tableInfo) {
            if ($alias !== $tableAlias) {
                // Handle subqueries (Select objects) as table names
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
                // In PostgreSQL UPDATE ... FROM, join conditions go in WHERE clause
                if (!empty($tableInfo['joinCondition'])) {
                    $joinConditions[] = $tableInfo['joinCondition'];
                }
            }
        }

        if ($fromTables) {
            $query .= sprintf(' FROM %s', implode(', ', $fromTables));
        }

        // Build WHERE clause from join conditions and select's WHERE parts
        $whereClauses = $joinConditions; // Start with join conditions

        $where = $select->getPart(\Maho\Db\Select::WHERE);
        if ($where) {
            foreach ($where as $wherePart) {
                if (is_array($wherePart)) {
                    // WHERE part is ['AND' => '(condition)'] or ['OR' => '(condition)']
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
        // Resolve table alias to actual table name if needed
        $actualTableName = $table;
        $tableAlias = $table;
        $from = $select->getPart(\Maho\Db\Select::FROM);
        if (isset($from[$table]) && isset($from[$table]['tableName'])) {
            $actualTableName = $from[$table]['tableName'];
        }

        // Get primary key column - try explicit PK first, then common column names
        $pkColumns = $this->_getPrimaryKeyColumns($actualTableName);
        if (!empty($pkColumns)) {
            $pkColumn = $pkColumns[0];
        } else {
            // No primary key found - check for common identifier columns
            $tableColumns = array_keys($this->describeTable($actualTableName));
            if (in_array('entity_id', $tableColumns)) {
                $pkColumn = 'entity_id';
            } elseif (in_array('id', $tableColumns)) {
                $pkColumn = 'id';
            } else {
                // Last resort: use first column
                $pkColumn = $tableColumns[0] ?? 'id';
            }
        }

        // Clone select and reset columns to only select the primary key
        $subSelect = clone $select;
        $subSelect->reset(\Maho\Db\Select::COLUMNS);
        $subSelect->columns([$tableAlias . '.' . $pkColumn]);

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
            // PostgreSQL doesn't have a built-in CHECKSUM TABLE
            // We use a hash of the table content instead
            $sql = sprintf(
                "SELECT MD5(STRING_AGG(t::text, '')) AS checksum FROM %s t",
                $this->quoteIdentifier($tableName),
            );
            $row = $this->raw_fetchRow($sql);
            $result[$tableName] = $row['checksum'] ?? null;
        }

        return $result;
    }

    /**
     * Check if the database support STRAIGHT JOIN
     */
    #[\Override]
    public function supportStraightJoin(): bool
    {
        // PostgreSQL doesn't support STRAIGHT_JOIN
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
     */
    #[\Override]
    public function forUpdate(string $sql): string
    {
        return $sql . ' FOR UPDATE';
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
     *
     * PostgreSQL requires the table name to drop a trigger. This implementation
     * queries the system catalog to find the table associated with the trigger.
     */
    #[\Override]
    public function dropTrigger(string $triggerName): self
    {
        // PostgreSQL requires table name to drop trigger - query system catalog to find it
        $sql = 'SELECT c.relname AS table_name
                FROM pg_trigger t
                JOIN pg_class c ON t.tgrelid = c.oid
                WHERE t.tgname = ' . $this->quote($triggerName) . '
                AND NOT t.tgisinternal';

        $result = $this->raw_fetchRow($sql);

        if ($result && !empty($result['table_name'])) {
            $dropSql = sprintf(
                'DROP TRIGGER IF EXISTS %s ON %s',
                $this->quoteIdentifier($triggerName),
                $this->quoteIdentifier($result['table_name']),
            );
            $this->raw_query($dropSql);
        }

        return $this;
    }

    /**
     * Change table auto increment value (PostgreSQL uses sequences)
     */
    #[\Override]
    public function changeTableAutoIncrement(string $tableName, int $increment, ?string $schemaName = null): \Maho\Db\Statement\Pdo\Pgsql
    {
        // Find the sequence name
        $primaryKeys = $this->_getPrimaryKeyColumns($tableName);
        $primaryKey = $primaryKeys[0] ?? 'id';
        $sequenceName = sprintf('%s_%s_seq', $tableName, $primaryKey);

        $sql = sprintf(
            'ALTER SEQUENCE %s RESTART WITH %d',
            $this->quoteIdentifier($sequenceName),
            $increment,
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
     * Retrieve column definition fragment for PostgreSQL
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

        // Detect and validate column type
        if ($ddlType === null) {
            $ddlType = $this->_getDdlType($options);
        }

        if (empty($ddlType) || !isset($this->_ddlColumnTypes[$ddlType])) {
            throw new \Maho\Db\Exception('Invalid column definition data');
        }

        $cType = $this->_ddlColumnTypes[$ddlType];

        // Column size/precision handling
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
                if (empty($options['LENGTH'])) {
                    $length = \Maho\Db\Ddl\Table::DEFAULT_TEXT_SIZE;
                } else {
                    $length = $this->_parseTextSize($options['LENGTH']);
                }
                // PostgreSQL doesn't have different text types like MySQL, just use varchar or text
                if ($length <= 65535) {
                    $cType = sprintf('varchar(%d)', $length);
                } else {
                    $cType = 'text';
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

        // For identity columns in PostgreSQL, use SERIAL/BIGSERIAL
        if ($cIdentity) {
            if ($ddlType == \Maho\Db\Ddl\Table::TYPE_BIGINT) {
                $cType = 'BIGSERIAL';
            } elseif ($ddlType == \Maho\Db\Ddl\Table::TYPE_SMALLINT) {
                $cType = 'SMALLSERIAL';
            } else {
                $cType = 'SERIAL';
            }
            // SERIAL types cannot have NOT NULL or DEFAULT
            return $cType;
        }

        return sprintf(
            '%s%s%s',
            $cType,
            $cNullable ? ' NULL' : ' NOT NULL',
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

        if (empty($columns)) {
            throw new \Maho\Db\Exception('Table columns are not defined');
        }

        foreach ($columns as $columnData) {
            $columnDefinition = $this->_getColumnDefinition($columnData);
            if ($columnData['PRIMARY']) {
                $primary[$columnData['COLUMN_NAME']] = $columnData['PRIMARY_POSITION'];
            }

            $definition[] = sprintf(
                '  %s %s',
                $this->quoteIdentifier($columnData['COLUMN_NAME']),
                $columnDefinition,
            );
        }

        // PRIMARY KEY
        if (!empty($primary)) {
            asort($primary, SORT_NUMERIC);
            $primary = array_map([$this, 'quoteIdentifier'], array_keys($primary));
            $definition[] = sprintf('  PRIMARY KEY (%s)', implode(', ', $primary));
        }

        return $definition;
    }

    /**
     * Retrieve table indexes definition array for create table
     */
    protected function _getIndexesDefinition(\Maho\Db\Ddl\Table $table): array
    {
        $definition = [];
        $indexes = $table->getIndexes();

        if (!empty($indexes)) {
            foreach ($indexes as $indexData) {
                if (empty($indexData['TYPE']) || $indexData['TYPE'] === 'primary') {
                    continue; // Primary key is handled in columns definition
                }

                $columns = [];
                foreach ($indexData['COLUMNS'] as $columnData) {
                    $columns[] = $this->quoteIdentifier($columnData['NAME']);
                }

                $indexType = strtoupper($indexData['TYPE'] ?? 'INDEX');
                if ($indexType === 'UNIQUE') {
                    $definition[] = sprintf(
                        '  UNIQUE (%s)',
                        implode(', ', $columns),
                    );
                }
                // Note: Regular indexes in PostgreSQL must be created after table creation
            }
        }

        return $definition;
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
                    '  CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
                    $this->quoteIdentifier($fkData['FK_NAME']),
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
     * Retrieve table options definition array for create table (PostgreSQL version)
     */
    protected function _getOptionsDefinition(\Maho\Db\Ddl\Table $table): array
    {
        // PostgreSQL doesn't support MySQL-style table options like ENGINE, CHARSET
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
    public function createTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Pgsql
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

        // Add table comment if provided
        $comment = $table->getComment();
        if (!empty($comment)) {
            $this->raw_query(sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->quoteIdentifier($table->getName()),
                $this->quote($comment),
            ));
        }

        // Create regular indexes (non-unique, non-primary) after table creation
        // PostgreSQL doesn't support inline KEY() syntax like MySQL
        $indexes = $table->getIndexes();
        foreach ($indexes as $indexData) {
            $indexType = strtoupper($indexData['TYPE'] ?? 'INDEX');
            if ($indexType === 'UNIQUE' || $indexType === 'PRIMARY') {
                continue; // Already handled inline
            }

            $indexColumns = [];
            foreach ($indexData['COLUMNS'] as $columnData) {
                $indexColumns[] = $this->quoteIdentifier($columnData['NAME']);
            }

            $this->raw_query(sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->quoteIdentifier($indexData['INDEX_NAME']),
                $this->quoteIdentifier($table->getName()),
                implode(', ', $indexColumns),
            ));
        }

        // Reset DDL cache for this table
        $this->resetDdlCache($table->getName());

        return $result;
    }

    /**
     * Create temporary table from DDL object
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function createTemporaryTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Pgsql
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
                || ($indexData['INDEX_TYPE'] == \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY)
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
        $query = 'DROP TABLE IF EXISTS ' . $table . ' CASCADE';
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
        $query = 'TRUNCATE TABLE ' . $table . ' RESTART IDENTITY CASCADE';
        $this->query($query);

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

        // PostgreSQL doesn't have RENAME TABLE ... TO ..., ... syntax
        // Execute each rename individually
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
        string $indexType = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ?string $schemaName = null,
    ): \Maho\Db\Statement\Pdo\Pgsql {
        $columns = $this->describeTable($tableName, $schemaName);
        $keyList = $this->getIndexList($tableName, $schemaName);

        // For PRIMARY KEY, check if a primary key already exists and drop it
        if (strtolower($indexType) === \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY) {
            if (isset($keyList['PRIMARY'])) {
                $this->dropIndex($tableName, 'PRIMARY', $schemaName);
            }
        } elseif (isset($keyList[strtoupper($indexName)])) {
            // Drop existing index if it exists
            $this->dropIndex($tableName, $indexName, $schemaName);
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fieldSql = [];
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
            $fieldSql[] = $this->quoteIdentifier($field);
        }
        $fieldSql = implode(', ', $fieldSql);

        $qualifiedTableName = $this->_getTableName($tableName, $schemaName);

        // PostgreSQL uses CREATE INDEX syntax, not ALTER TABLE
        $query = match (strtolower($indexType)) {
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY => sprintf(
                'ALTER TABLE %s ADD PRIMARY KEY (%s)',
                $this->quoteIdentifier($qualifiedTableName),
                $fieldSql,
            ),
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE => sprintf(
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
     * Drop the index from table
     */
    #[\Override]
    public function dropIndex(string $tableName, string $keyName, ?string $schemaName = null): bool|\Maho\Db\Statement\Pdo\Pgsql
    {
        $indexList = $this->getIndexList($tableName, $schemaName);
        $keyName = strtoupper($keyName);

        if (!isset($indexList[$keyName])) {
            return true;
        }

        if ($keyName == 'PRIMARY') {
            $sql = sprintf(
                'ALTER TABLE %s DROP CONSTRAINT %s_pkey',
                $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
                $tableName,
            );
        } else {
            $sql = sprintf(
                'DROP INDEX IF EXISTS %s',
                $this->quoteIdentifier($indexList[$keyName]['KEY_NAME']),
            );
        }

        $this->resetDdlCache($tableName, $schemaName);

        return $this->raw_query($sql);
    }

    /**
     * Add new Foreign Key to table
     */
    #[\Override]
    public function addForeignKey(
        string $fkName,
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
        string $onUpdate = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
        bool $purge = false,
        ?string $schemaName = null,
        ?string $refSchemaName = null,
    ): self {
        $this->dropForeignKey($tableName, $fkName, $schemaName);

        if ($purge) {
            $this->purgeOrphanRecords($tableName, $columnName, $refTableName, $refColumnName, $onDelete);
        }

        $query = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            $this->quoteIdentifier($fkName),
            $this->quoteIdentifier($columnName),
            $this->quoteIdentifier($this->_getTableName($refTableName, $refSchemaName)),
            $this->quoteIdentifier($refColumnName),
        );

        $query .= ' ON DELETE ' . strtoupper($onDelete);
        $query .= ' ON UPDATE ' . strtoupper($onUpdate);

        $this->raw_query($query);
        $this->resetDdlCache($tableName);

        return $this;
    }

    /**
     * Run additional environment before setup
     */
    #[\Override]
    public function startSetup(): self
    {
        // Disable foreign key checks in PostgreSQL session
        $this->raw_query('SET session_replication_role = replica');

        return $this;
    }

    /**
     * Run additional environment after setup
     */
    #[\Override]
    public function endSetup(): self
    {
        // Re-enable foreign key checks
        $this->raw_query('SET session_replication_role = DEFAULT');

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
            'finset'        => '?::text = ANY(STRING_TO_ARRAY({{fieldName}}, \',\'))',  // PostgreSQL equivalent of FIND_IN_SET
            'regexp'        => '{{fieldName}} ~ ?',  // PostgreSQL regex operator
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
            $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['eq'], (string) $condition, $fieldName);
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
                if ($column['NULLABLE'] && ($value === false || $value === '' || $value === null)) {
                    $value = null;
                } else {
                    $value = $this->formatDate($value, true);
                }
                break;
            case 'varchar':
            case 'text':
            case 'character varying':
                $value = str_replace("\0", '', (string) $value);
                if ($column['NULLABLE'] && $value == '') {
                    $value = null;
                }
                break;
            case 'bytea':
                // No special processing for PostgreSQL bytea
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
     *
     * In PostgreSQL, when inserting with an explicit ID value, the sequence is not
     * automatically updated. This can cause conflicts on subsequent inserts that
     * use the sequence. We need to manually advance the sequence to be at least
     * as high as the inserted ID.
     */
    #[\Override]
    public function insertForce(string $table, array $bind): int
    {
        $result = $this->insert($table, $bind);

        // After inserting with explicit IDs, update any sequences to avoid conflicts
        // Find serial/identity columns and update their sequences
        $tableInfo = $this->describeTable($table);
        foreach ($bind as $column => $value) {
            if (!isset($tableInfo[$column])) {
                continue;
            }

            $columnInfo = $tableInfo[$column];

            // Check if this column has an identity/serial (has a sequence default)
            if (isset($columnInfo['DEFAULT']) && is_string($columnInfo['DEFAULT'])
                && str_starts_with($columnInfo['DEFAULT'], 'nextval(')) {
                // Extract sequence name from default like "nextval('tablename_column_seq'::regclass)"
                if (preg_match("/nextval\('([^']+)'/", $columnInfo['DEFAULT'], $matches)) {
                    $sequenceName = $matches[1];
                    // Update sequence to be at least as high as the inserted value
                    // Use setval with is_called=true so next call returns value+1
                    $this->raw_query(sprintf(
                        "SELECT setval('%s', GREATEST((SELECT last_value FROM %s), %d))",
                        $sequenceName,
                        $this->quoteIdentifier($sequenceName),
                        (int) $value,
                    ));
                }
            }
        }

        return $result;
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
     * Uses PostgreSQL-compatible DELETE syntax
     */
    #[\Override]
    public function purgeOrphanRecords(
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
    ): self {
        $onDelete = strtoupper($onDelete);

        if ($onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE
            || $onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_RESTRICT
        ) {
            // PostgreSQL DELETE syntax with subquery instead of JOIN
            $sql = sprintf(
                'DELETE FROM %s WHERE %s NOT IN (SELECT %s FROM %s WHERE %s IS NOT NULL)',
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
                $this->quoteIdentifier($refTableName),
                $this->quoteIdentifier($refColumnName),
            );
            $this->raw_query($sql);
        } elseif ($onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL) {
            // Set NULL for orphan records
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
     * Change table comment (PostgreSQL uses COMMENT ON syntax)
     */
    #[\Override]
    public function changeTableComment(string $tableName, string $comment, ?string $schemaName = null): mixed
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql = sprintf('COMMENT ON TABLE %s IS %s', $table, $this->quote($comment));

        return $this->raw_query($sql);
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

                    if ($onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL
                        || $onUpdate == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL
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
                        $onDelete ?: \Maho\Db\Adapter\AdapterInterface::FK_ACTION_NO_ACTION,
                        $onUpdate ?: \Maho\Db\Adapter\AdapterInterface::FK_ACTION_NO_ACTION,
                    );
                }
            }

            if (!empty($tableData['comment'])) {
                $this->changeTableComment($table, $tableData['comment']);
            }
        }

        return $this;
    }

    /**
     * Cast an expression to the appropriate PostgreSQL type based on target column type
     *
     * PostgreSQL is strict about types and won't implicitly cast text to numeric types.
     * CASE expressions often return text type which needs to be explicitly cast when
     * updating numeric columns like smallint, integer, bigint, etc.
     *
     * @param string $expression The SQL expression
     * @param string|null $targetType The target column's Doctrine DBAL type name
     * @return string The expression with appropriate cast if needed
     */
    protected function _castExpressionToColumnType(string $expression, ?string $targetType): string
    {
        if ($targetType === null) {
            return $expression;
        }

        // Map Doctrine DBAL type names to PostgreSQL cast types
        $castType = match (strtolower($targetType)) {
            'smallint' => 'smallint',
            'integer' => 'integer',
            'bigint' => 'bigint',
            'decimal', 'numeric' => 'numeric',
            'float', 'real' => 'real',
            'boolean', 'bool' => 'boolean',
            default => null,
        };

        // Only wrap expressions that likely need casting (CASE, COALESCE, etc.)
        // Direct column references and simple values usually work fine
        if ($castType !== null) {
            // Wrap the entire expression with a cast
            return sprintf('(%s)::%s', $expression, $castType);
        }

        return $expression;
    }
}
