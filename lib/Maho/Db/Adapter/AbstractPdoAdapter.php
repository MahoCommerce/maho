<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Maho\Db\Ddl\Table;
use Maho\Db\Exception;
use Maho\Db\Expr;
use Maho\Db\Helper;
use Maho\Db\Select;
use Maho\Db\Statement\Pdo\Mysql as Statement;

/**
 * Abstract PDO database adapter using Doctrine DBAL
 *
 * This class provides a platform-agnostic base for database adapters.
 * Platform-specific functionality is implemented in concrete adapter classes.
 */
abstract class AbstractPdoAdapter implements AdapterInterface
{
    // Numeric data type constants
    public const INT_TYPE = 0;
    public const BIGINT_TYPE = 5;
    public const FLOAT_TYPE = 2;

    public const DEBUG_CONNECT         = 0;
    public const DEBUG_TRANSACTION     = 1;
    public const DEBUG_QUERY           = 2;

    public const TIMESTAMP_FORMAT      = 'Y-m-d H:i:s';
    public const DATETIME_FORMAT       = 'Y-m-d H:i:s';
    public const DATE_FORMAT           = 'Y-m-d';

    public const DDL_DESCRIBE          = 1;
    public const DDL_CREATE            = 2;
    public const DDL_INDEX             = 3;
    public const DDL_FOREIGN_KEY       = 4;

    public const LENGTH_TABLE_NAME     = 64;
    public const LENGTH_INDEX_NAME     = 64;
    public const LENGTH_FOREIGN_NAME   = 64;

    /**
     * Address type constants for host parsing
     */
    public const ADDRESS_TYPE_HOSTNAME     = 'hostname';
    public const ADDRESS_TYPE_UNIX_SOCKET  = 'unix_socket';
    public const ADDRESS_TYPE_IPV4_ADDRESS = 'ipv4';
    public const ADDRESS_TYPE_IPV6_ADDRESS = 'ipv6';

    /**
     * Doctrine DBAL Connection
     */
    protected ?Connection $_connection = null;

    /**
     * Adapter configuration
     */
    protected array $_config = [];

    /**
     * Fetch mode
     */
    protected int $_fetchMode = Statement::FETCH_ASSOC;

    /**
     * Numeric data types mapping
     */
    protected array $_numericDataTypes = [
        'INT' => self::INT_TYPE,
        'SMALLINT' => self::INT_TYPE,
        'BIGINT' => self::BIGINT_TYPE,
        'FLOAT' => self::FLOAT_TYPE,
        'DECIMAL' => self::FLOAT_TYPE,
        'NUMERIC' => self::FLOAT_TYPE,
        'DOUBLE' => self::FLOAT_TYPE,
        'REAL' => self::FLOAT_TYPE,
    ];

    /**
     * Current Transaction Level
     */
    protected int $_transactionLevel = 0;

    /**
     * Set attribute to connection flag
     */
    protected bool $_connectionFlagsSet = false;

    /**
     * Tables DDL cache
     */
    protected array $_ddlCache = [];

    /**
     * SQL bind params. Used temporarily by regexp callback.
     */
    protected array $_bindParams = [];

    /**
     * Autoincrement for bind value. Used by regexp callback.
     */
    protected int $_bindIncrement = 0;

    /**
     * Write SQL debug data to file
     */
    protected bool $_debug = false;

    /**
     * Minimum query duration time to be logged
     */
    protected float $_logQueryTime = 0.05;

    /**
     * Log all queries (ignored minimum query duration time)
     */
    protected bool $_logAllQueries = false;

    /**
     * Log file name for SQL debug data
     */
    protected string $_debugFile = 'db_adapter.log';

    /**
     * Debug timer start value
     */
    protected float $_debugTimer = 0;

    /**
     * Cache frontend adapter instance
     */
    protected ?\Mage_Core_Model_Cache $_cacheAdapter = null;

    /**
     * DDL cache allowing flag
     */
    protected bool $_isDdlCacheAllowed = true;

    /**
     * DDL column types mapping (abstract - defined in concrete adapters)
     */
    protected array $_ddlColumnTypes = [];

    /**
     * All possible DDL statements - First 3 symbols for each statement
     */
    protected array $_ddlRoutines = ['alt', 'cre', 'ren', 'dro', 'tru'];

    /**
     * DDL statements for temporary tables pattern
     */
    protected string $_tempRoutines = '#^\w+\s+temporary\s#im';

    /**
     * Allowed interval units array
     */
    protected array $_intervalUnits = [
        self::INTERVAL_YEAR     => 'YEAR',
        self::INTERVAL_MONTH    => 'MONTH',
        self::INTERVAL_DAY      => 'DAY',
        self::INTERVAL_HOUR     => 'HOUR',
        self::INTERVAL_MINUTE   => 'MINUTE',
        self::INTERVAL_SECOND   => 'SECOND',
    ];

    /**
     * Hook callback to modify queries
     */
    protected ?array $_queryHook = null;

    /**
     * Whether to automatically quote identifiers
     */
    protected bool $_autoQuoteIdentifiers = true;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->_config = $config;

        if (isset($config['profiler']) && $config['profiler'] === true) {
            $this->_debug = true;
        }
    }

    /**
     * Returns the configuration variables in this adapter.
     */
    #[\Override]
    public function getConfig(): array
    {
        return $this->_config;
    }

    /**
     * Returns the underlying Doctrine DBAL Connection instance.
     */
    #[\Override]
    public function getConnection(): Connection
    {
        $this->_connect();
        return $this->_connection;
    }

    /**
     * Returns the Doctrine DBAL Platform for this connection.
     * Used for platform-agnostic SQL expression generation.
     */
    protected function getPlatform(): \Doctrine\DBAL\Platforms\AbstractPlatform
    {
        $this->_connect();
        return $this->_connection->getDatabasePlatform();
    }

    /**
     * Check if currently in a transaction
     */
    public function isTransaction(): bool
    {
        return (bool) $this->_transactionLevel;
    }

    /**
     * Creates a connection to the database.
     * Platform-specific - must be implemented by concrete adapters.
     */
    abstract protected function _connect(): void;

    /**
     * Get the driver name for this adapter (e.g., 'pdo_mysql', 'pdo_pgsql')
     */
    abstract protected function getDriverName(): string;

    /**
     * Get the identifier quote character for this platform
     */
    abstract protected function getIdentifierQuoteChar(): string;

    /**
     * Run platform-specific initialization statements after connection
     */
    abstract protected function _initConnection(): void;

    // =========================================================================
    // Transaction Management (Platform-Agnostic)
    // =========================================================================

    #[\Override]
    public function beginTransaction(): static
    {
        if ($this->_transactionLevel === 0) {
            $this->_debugTimer();
            $this->_connect();
            $this->_connection->beginTransaction();
            $this->_debugStat(self::DEBUG_TRANSACTION, 'BEGIN');
        }
        ++$this->_transactionLevel;
        return $this;
    }

    #[\Override]
    public function commit(): static
    {
        if ($this->_transactionLevel === 1) {
            $this->_debugTimer();
            $this->_connection->commit();
            $this->_debugStat(self::DEBUG_TRANSACTION, 'COMMIT');
        }
        if ($this->_transactionLevel > 0) {
            --$this->_transactionLevel;
        }
        return $this;
    }

    #[\Override]
    public function rollBack(): static
    {
        if ($this->_transactionLevel === 1) {
            $this->_debugTimer();
            $this->_connection->rollBack();
            $this->_debugStat(self::DEBUG_TRANSACTION, 'ROLLBACK');
        }
        if ($this->_transactionLevel > 0) {
            --$this->_transactionLevel;
        }
        return $this;
    }

    #[\Override]
    public function getTransactionLevel(): int
    {
        return $this->_transactionLevel;
    }

    // =========================================================================
    // Fetch Methods (Platform-Agnostic)
    // =========================================================================

    #[\Override]
    public function fetchAll(string|Select $sql, array|int|string|float $bind = [], ?int $fetchMode = null): array
    {
        $stmt = $this->query($sql, $bind);
        if ($fetchMode === null) {
            $fetchMode = $this->_fetchMode;
        }
        return $stmt->fetchAll($fetchMode);
    }

    #[\Override]
    public function fetchRow(string|Select $sql, array|int|string|float $bind = [], ?int $fetchMode = null): array|false
    {
        $stmt = $this->query($sql, $bind);
        if ($fetchMode === null) {
            $fetchMode = $this->_fetchMode;
        }
        return $stmt->fetch($fetchMode);
    }

    #[\Override]
    public function fetchCol(string|Select $sql, array|int|string|float $bind = []): array
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->getResult()->fetchFirstColumn();
    }

    #[\Override]
    public function fetchPairs(string|Select $sql, array|int|string|float $bind = []): array
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->getResult()->fetchAllKeyValue();
    }

    #[\Override]
    public function fetchOne(string|Select $sql, array|int|string|float $bind = []): mixed
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->fetchColumn(0);
    }

    #[\Override]
    public function fetchAssoc(string|Select $sql, array|int|string|float $bind = []): array
    {
        $stmt = $this->query($sql, $bind);
        $data = [];
        while ($row = $stmt->getResult()->fetchAssociative()) {
            $data[current($row)] = $row;
        }
        return $data;
    }

    // =========================================================================
    // Quoting Methods
    // =========================================================================

    #[\Override]
    public function quoteIdentifier(string|array|Expr $ident, bool $auto = false): string
    {
        return $this->_quoteIdentifierAs($ident, null, $auto);
    }

    #[\Override]
    public function quoteColumnAs(string|array|Expr $ident, ?string $alias, bool $auto = false): string
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    #[\Override]
    public function quoteTableAs(string|array|Expr|Select $ident, ?string $alias = null, bool $auto = false): string
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     */
    protected function _quoteIdentifierAs(string|array|Expr|Select $ident, ?string $alias = null, bool $auto = false, string $as = ' AS '): string
    {
        if ($ident instanceof Expr) {
            $quoted = $ident->__toString();
        } elseif ($ident instanceof Select) {
            $quoted = '(' . $ident->assemble() . ')';
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            $segments = [];
            foreach ($ident as $segment) {
                if ($segment === '' || $segment === null) {
                    continue;
                }
                if ($segment instanceof Expr) {
                    $segments[] = $segment->__toString();
                } else {
                    $segments[] = $this->_quoteIdentifier($segment, $auto);
                }
            }
            if (empty($segments)) {
                $quoted = '*';
            } else {
                // Note: We intentionally do NOT strip the alias when it matches the column name.
                // While `column AS column` is redundant, it's necessary for SQLite UNION queries
                // where ORDER BY needs explicit aliases to match result set columns.
                $quoted = implode('.', $segments);
            }
        }
        if ($alias !== null) {
            $quoted .= $as . $this->_quoteIdentifier($alias, $auto);
        }
        return $quoted;
    }

    /**
     * Quote a single identifier using the platform-specific quote character.
     */
    protected function _quoteIdentifier(string $value, bool $auto = false): string
    {
        if ($auto === false || $this->_autoQuoteIdentifiers === true) {
            $q = $this->getIdentifierQuoteChar();
            return ($q . str_replace($q, $q . $q, $value) . $q);
        }
        return $value;
    }

    #[\Override]
    public function quote(Select|Expr|array|null|int|string|float|bool $value, null|string|int $type = null): string
    {
        $this->_connect();

        if ($value instanceof Select) {
            return '(' . $value->assemble() . ')';
        }

        if ($value instanceof Expr) {
            return $value->__toString();
        }

        if (is_array($value)) {
            if (empty($value)) {
                return 'NULL'; // Makes IN(NULL) which is always false
            }
            foreach ($value as &$val) {
                $val = $this->quote($val, $type);
            }
            return implode(', ', $value);
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($type !== null && ($type == self::INT_TYPE || $type == 'INT' || $type == self::BIGINT_TYPE || $type == 'BIGINT')) {
            return (string) (int) $value;
        }

        if ($type !== null && ($type == self::FLOAT_TYPE || $type == 'FLOAT')) {
            return (string) (float) $value;
        }

        return $this->_connection->quote((string) $value);
    }

    #[\Override]
    public function quoteInto(string $text, Select|Expr|array|null|int|string|float|bool $value, null|string|int $type = null, ?int $count = null): string
    {
        if ($count === null) {
            return str_replace('?', $this->quote($value, $type), $text);
        }

        return implode($this->quote($value, $type), explode('?', $text, $count + 1));
    }

    // =========================================================================
    // Basic CRUD Operations (Platform-Agnostic SQL)
    // =========================================================================

    #[\Override]
    public function insert(string|array|Select $table, array $bind): int
    {
        $cols = [];
        $vals = [];
        $params = [];
        foreach ($bind as $col => $value) {
            $cols[] = $this->quoteIdentifier($col);
            if ($value instanceof Expr) {
                $vals[] = $value->__toString();
            } else {
                $vals[] = '?';
                $params[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES(%s)',
            $this->quoteIdentifier($table),
            implode(', ', $cols),
            implode(', ', $vals),
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    #[\Override]
    public function update(string|array|Select $table, array $bind, string|array $where = ''): int
    {
        $set = [];
        $params = [];
        foreach ($bind as $col => $value) {
            if ($value instanceof Expr) {
                // Expr values are included directly in SQL, not as bound parameters
                $set[] = $this->quoteIdentifier($col) . ' = ' . $value->__toString();
            } else {
                $set[] = $this->quoteIdentifier($col) . ' = ?';
                $params[] = $value;
            }
        }

        $where = $this->_whereExpr($where);

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            ($where) ? " WHERE $where" : '',
        );

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    #[\Override]
    public function delete(string|array|Select $table, string|array $where = ''): int
    {
        $where = $this->_whereExpr($where);

        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->quoteIdentifier($table),
            ($where) ? " WHERE $where" : '',
        );

        $stmt = $this->query($sql);
        return $stmt->rowCount();
    }

    /**
     * Convert an array, string, or Expr object into a WHERE clause string.
     */
    protected function _whereExpr(mixed $where): string
    {
        if (empty($where)) {
            return '';
        }
        if (!is_array($where)) {
            $where = [$where];
        }
        foreach ($where as $cond => &$term) {
            if (is_int($cond)) {
                if ($term instanceof Expr) {
                    $term = $term->__toString();
                }
            } else {
                $term = $this->quoteInto($cond, $term);
            }
        }

        return implode(' AND ', $where);
    }

    // =========================================================================
    // Platform-Specific Insert Operations (Abstract)
    // =========================================================================

    /**
     * Insert with ON DUPLICATE KEY UPDATE (MySQL) / ON CONFLICT (PostgreSQL)
     */
    #[\Override]
    abstract public function insertOnDuplicate(string|array|Select $table, array $data, array $fields = []): int;

    /**
     * Insert ignoring duplicates
     */
    #[\Override]
    abstract public function insertIgnore(string|array|Select $table, array $bind): int;

    /**
     * Insert with special handling for zero auto-increment values
     */
    #[\Override]
    abstract public function insertForce(string $table, array $bind): int;

    // =========================================================================
    // SQL Helper Methods (Platform-Agnostic)
    // =========================================================================

    /**
     * Generate CASE WHEN SQL expression (standard SQL)
     */
    #[\Override]
    public function getCaseSql(string $valueName, array $casesResults, ?string $defaultValue = null): Expr
    {
        $expression = 'CASE ' . $valueName;
        foreach ($casesResults as $case => $result) {
            $expression .= ' WHEN ' . $case . ' THEN ' . $result;
        }
        if ($defaultValue !== null) {
            $expression .= ' ELSE ' . $defaultValue;
        }
        $expression .= ' END';

        return new Expr($expression);
    }

    /**
     * Generate CONCAT SQL expression using DBAL Platform
     * Delegates to platform-specific getConcatExpression() for cross-database support
     */
    #[\Override]
    public function getConcatSql(array $data, ?string $separator = null): Expr
    {
        if (empty($separator)) {
            // Use DBAL Platform for platform-agnostic concatenation
            // MySQL uses CONCAT(), PostgreSQL uses ||
            // Convert Expr objects to strings as DBAL expects string arguments
            $strings = array_map(fn($item) => (string) $item, $data);
            return new Expr($this->getPlatform()->getConcatExpression(...$strings));
        }
        // CONCAT_WS not in DBAL - use platform-specific implementation
        return $this->getConcatWithSeparatorSql($data, $separator);
    }

    /**
     * Generate CONCAT with separator SQL expression (platform-specific)
     * MySQL uses CONCAT_WS(), PostgreSQL uses || with separator
     */
    abstract protected function getConcatWithSeparatorSql(array $data, string $separator): Expr;

    /**
     * Generate LENGTH SQL expression using DBAL Platform
     * MySQL uses CHAR_LENGTH(), PostgreSQL uses LENGTH()
     */
    #[\Override]
    public function getLengthSql(string $string): Expr
    {
        return new Expr($this->getPlatform()->getLengthExpression($string));
    }

    /**
     * Generate LEAST SQL expression (standard SQL)
     */
    #[\Override]
    public function getLeastSql(array $data): Expr
    {
        return new Expr(sprintf('LEAST(%s)', implode(', ', $data)));
    }

    /**
     * Generate GREATEST SQL expression (standard SQL)
     */
    #[\Override]
    public function getGreatestSql(array $data): Expr
    {
        return new Expr(sprintf('GREATEST(%s)', implode(', ', $data)));
    }

    /**
     * Generate SUBSTRING SQL expression using DBAL Platform
     * Platform handles syntax differences (SUBSTRING FROM FOR vs positional args)
     */
    #[\Override]
    public function getSubstringSql(Expr|string $stringExpression, int|string|Expr $pos, int|string|Expr|null $len = null): Expr
    {
        $expr = (string) $stringExpression;
        $start = (string) $pos;
        $length = $len !== null ? (string) $len : null;

        return new Expr($this->getPlatform()->getSubstringExpression($expr, $start, $length));
    }

    /**
     * Generate EXTRACT SQL expression (standard SQL)
     */
    #[\Override]
    public function getDateExtractSql(Expr|string $date, string $unit): Expr
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        $expr = sprintf('EXTRACT(%s FROM %s)', $this->_intervalUnits[$unit], $date);
        return new Expr($expr);
    }

    /**
     * Generate date difference SQL expression using DBAL Platform
     * Returns difference in days (date1 - date2)
     * MySQL uses DATEDIFF(), PostgreSQL uses (DATE(date1)-DATE(date2))
     */
    #[\Override]
    public function getDateDiffSql(Expr|string $date1, Expr|string $date2): Expr
    {
        return new Expr($this->getPlatform()->getDateDiffExpression((string) $date1, (string) $date2));
    }

    /**
     * Get SQL expression for days until next annual occurrence of a date
     *
     * This calculates the number of days from a reference date until the next
     * occurrence of an anniversary (e.g., birthday). Handles:
     * - Dates where the year is in the future (returns days to that date in current year)
     * - Dates where the anniversary has passed this year (returns days to next year)
     * - Leap year birthdays (Feb 29) in non-leap years (uses Feb 28)
     *
     * @param Expr|string $dateField The date field containing the anniversary (e.g., birth date)
     * @param string $referenceDate The reference date in 'Y-m-d' or 'Y-m-d H:i:s' format (usually today)
     * @return Expr SQL expression that returns days until next anniversary
     */
    #[\Override]
    abstract public function getDaysUntilAnniversarySql(Expr|string $dateField, string $referenceDate): Expr;

    // =========================================================================
    // Platform-Specific SQL Helper Methods (Abstract)
    // =========================================================================

    /**
     * Generate conditional check SQL (IF in MySQL, CASE WHEN in PostgreSQL)
     */
    #[\Override]
    abstract public function getCheckSql(string $condition, string|Expr $true, string|Expr $false): Expr;

    /**
     * Generate IFNULL/COALESCE SQL expression
     */
    #[\Override]
    abstract public function getIfNullSql(string $expression, string|int $value = '0'): Expr;

    /**
     * Generate date add SQL expression
     */
    #[\Override]
    abstract public function getDateAddSql(Expr|string $date, int|string $interval, string $unit): Expr;

    /**
     * Generate date subtract SQL expression
     */
    #[\Override]
    abstract public function getDateSubSql(Expr|string $date, int|string $interval, string $unit): Expr;

    /**
     * Generate date format SQL expression
     */
    #[\Override]
    abstract public function getDateFormatSql(Expr|string $date, string $format): Expr;

    /**
     * Generate date part extraction SQL expression
     */
    #[\Override]
    abstract public function getDatePartSql(Expr|string $date): Expr;

    /**
     * Generate standard deviation SQL expression
     */
    #[\Override]
    abstract public function getStandardDeviationSql(Expr|string $expressionField): Expr;

    /**
     * Generate UNIX timestamp SQL expression
     */
    #[\Override]
    abstract public function getUnixTimestamp(string|Expr $date): Expr;

    /**
     * Convert UNIX timestamp to date SQL expression
     */
    #[\Override]
    abstract public function fromUnixtime(int|Expr $timestamp): Expr;

    // =========================================================================
    // Naming Helpers
    // =========================================================================

    /**
     * Retrieve valid table name, checking length and allowed symbols
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
        $prefix = match ($indexType) {
            self::INDEX_TYPE_UNIQUE => 'UNQ_',
            self::INDEX_TYPE_FULLTEXT => 'FTI_',
            self::INDEX_TYPE_PRIMARY => '',
            default => 'IDX_',
        };

        if (is_array($fields)) {
            $fields = implode('_', $fields);
        }

        $hash = $tableName . '_' . $fields;
        $indexName = $prefix . strtoupper($hash);

        if (strlen($indexName) > self::LENGTH_INDEX_NAME) {
            $indexName = $prefix . strtoupper(Helper::shortName($hash));
            if (strlen($indexName) > self::LENGTH_INDEX_NAME) {
                $hash = md5($hash);
                if (strlen($prefix . $hash) > self::LENGTH_INDEX_NAME) {
                    $indexName = strtoupper($this->_minusSuperfluous($hash, $prefix, self::LENGTH_INDEX_NAME));
                } else {
                    $indexName = strtoupper($prefix . $hash);
                }
            }
        }
        return $indexName;
    }

    /**
     * Retrieve valid foreign key name
     */
    #[\Override]
    public function getForeignKeyName(string $priTableName, string $priColumnName, string $refTableName, string $refColumnName): string
    {
        $prefix = 'FK_';
        $hash = sprintf('%s_%s_%s_%s', $priTableName, $priColumnName, $refTableName, $refColumnName);
        $fkName = $prefix . strtoupper($hash);

        if (strlen($fkName) > self::LENGTH_FOREIGN_NAME) {
            $fkName = $prefix . strtoupper(Helper::shortName($hash));
            if (strlen($fkName) > self::LENGTH_FOREIGN_NAME) {
                $hash = md5($hash);
                if (strlen($prefix . $hash) > self::LENGTH_FOREIGN_NAME) {
                    $fkName = strtoupper($this->_minusSuperfluous($hash, $prefix, self::LENGTH_FOREIGN_NAME));
                } else {
                    $fkName = strtoupper($prefix . $hash);
                }
            }
        }
        return $fkName;
    }

    /**
     * Minus superfluous characters from hash.
     */
    protected function _minusSuperfluous(string $hash, string $prefix, int $maxCharacters): string
    {
        $diff = strlen($hash) + strlen($prefix) - $maxCharacters;
        $superfluous = (int) ($diff / 2);
        $odd = $diff % 2;
        return substr($hash, $superfluous, -($superfluous + $odd));
    }

    // =========================================================================
    // Debug Methods
    // =========================================================================

    /**
     * Start debug timer
     */
    protected function _debugTimer(): static
    {
        if ($this->_debug) {
            $this->_debugTimer = microtime(true);
        }
        return $this;
    }

    /**
     * Log debug statistics
     */
    protected function _debugStat(int $type, string $sql, array $bind = [], mixed $result = null): static
    {
        if (!$this->_debug) {
            return $this;
        }

        $elapsed = microtime(true) - $this->_debugTimer;
        if (!$this->_logAllQueries && $elapsed < $this->_logQueryTime) {
            return $this;
        }

        $typeLabel = match ($type) {
            self::DEBUG_CONNECT => 'CONNECT',
            self::DEBUG_TRANSACTION => 'TRANSACTION',
            self::DEBUG_QUERY => 'QUERY',
            default => 'UNKNOWN',
        };

        $message = sprintf(
            "[%s] %s (%0.4fs)\n%s\n",
            date('Y-m-d H:i:s'),
            $typeLabel,
            $elapsed,
            $sql,
        );

        if (!empty($bind)) {
            $message .= 'Bind: ' . print_r($bind, true) . "\n";
        }

        $this->_debugWriteToFile($message);
        return $this;
    }

    /**
     * Write debug message to file
     */
    protected function _debugWriteToFile(string $message): void
    {
        \Mage::log($message, \Mage::LOG_DEBUG, $this->_debugFile);
    }

    /**
     * Log and re-throw exception
     *
     * @throws \Exception
     */
    protected function _debugException(\Exception $e): never
    {
        if ($this->_debug) {
            $this->_debugWriteToFile('EXCEPTION: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        }
        throw $e;
    }

    // =========================================================================
    // Cache Management
    // =========================================================================

    #[\Override]
    public function setCacheAdapter(\Mage_Core_Model_Cache $adapter): static
    {
        $this->_cacheAdapter = $adapter;
        return $this;
    }

    #[\Override]
    public function allowDdlCache(): static
    {
        $this->_isDdlCacheAllowed = true;
        return $this;
    }

    #[\Override]
    public function disallowDdlCache(): static
    {
        $this->_isDdlCacheAllowed = false;
        return $this;
    }

    /**
     * Get DDL cache prefix for this adapter
     */
    abstract protected function getDdlCachePrefix(): string;

    /**
     * Get DDL cache tag for this adapter
     */
    abstract protected function getDdlCacheTag(): string;

    #[\Override]
    public function resetDdlCache(?string $tableName = null, ?string $schemaName = null): static
    {
        if ($tableName === null) {
            $this->_ddlCache = [];
            if ($this->_cacheAdapter instanceof \Mage_Core_Model_Cache) {
                $this->_cacheAdapter->clean([$this->getDdlCacheTag()]);
            }
        } else {
            $cacheKey = $this->_getTableName($tableName, $schemaName);
            // Clear in-memory cache (uses key without prefix)
            unset($this->_ddlCache[$cacheKey]);
            if ($this->_cacheAdapter instanceof \Mage_Core_Model_Cache) {
                // Clear external cache (also uses key without prefix)
                $this->_cacheAdapter->remove($cacheKey);
            }
        }
        return $this;
    }

    #[\Override]
    public function saveDdlCache(string $tableCacheKey, int $ddlType, mixed $data): static
    {
        if (!$this->_isDdlCacheAllowed) {
            return $this;
        }
        $this->_ddlCache[$tableCacheKey][$ddlType] = $data;
        if ($this->_cacheAdapter instanceof \Mage_Core_Model_Cache) {
            $this->_cacheAdapter->save(
                serialize($this->_ddlCache[$tableCacheKey]),
                $tableCacheKey,
                [$this->getDdlCacheTag()],
            );
        }
        return $this;
    }

    #[\Override]
    public function loadDdlCache(string $tableCacheKey, int $ddlType): string|array|int|false
    {
        if (!$this->_isDdlCacheAllowed) {
            return false;
        }
        if (isset($this->_ddlCache[$tableCacheKey][$ddlType])) {
            return $this->_ddlCache[$tableCacheKey][$ddlType];
        }
        if ($this->_cacheAdapter instanceof \Mage_Core_Model_Cache) {
            $data = $this->_cacheAdapter->load($tableCacheKey);
            if ($data !== false) {
                $data = unserialize($data, ['allowed_classes' => false]);
                if (is_array($data) && isset($data[$ddlType])) {
                    $this->_ddlCache[$tableCacheKey] = $data;
                    return $data[$ddlType];
                }
            }
        }
        return false;
    }

    /**
     * Get table name with schema prefix
     */
    protected function _getTableName(string $tableName, ?string $schemaName = null): string
    {
        return ($schemaName ? $schemaName . '.' : '') . $tableName;
    }

    // =========================================================================
    // Select Builder
    // =========================================================================

    #[\Override]
    public function select(): Select
    {
        return new Select($this);
    }

    // =========================================================================
    // Date Formatting
    // =========================================================================

    #[\Override]
    public function formatDate(int|string|\DateTime $date, bool $includeTime = true): Expr
    {
        $dateObj = $date;
        if (!($date instanceof \DateTime)) {
            if (is_int($date)) {
                $dateObj = (new \DateTime())->setTimestamp($date);
            } else {
                $dateObj = new \DateTime($date);
            }
        }

        $format = $includeTime ? self::DATETIME_FORMAT : self::DATE_FORMAT;
        return new Expr($this->quote($dateObj->format($format)));
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Convert date to DB format
     */
    public function convertDate(int|string|\DateTime $date): Expr
    {
        return $this->formatDate($date, false);
    }

    /**
     * Convert date and time to DB format
     */
    public function convertDateTime(int|string|\DateTime $datetime): Expr
    {
        return $this->formatDate($datetime);
    }

    /**
     * Check transaction level in case of DDL query
     */
    protected function _checkDdlTransaction(string|Select $sql): void
    {
        if (is_string($sql) && $this->getTransactionLevel() > 0) {
            $startSql = strtolower(substr(ltrim($sql), 0, 3));
            if (in_array($startSql, $this->_ddlRoutines) && (preg_match($this->_tempRoutines, $sql) !== 1)) {
                throw new Exception(AdapterInterface::ERROR_DDL_MESSAGE);
            }
        }
    }

    /**
     * Returns date that fits into TYPE_DATETIME range and is suggested to act as default 'zero' value
     */
    #[\Override]
    public function getSuggestedZeroDate(): string
    {
        return '1970-01-01 00:00:00';
    }

    /**
     * Converts fetched blob into raw binary PHP data.
     */
    #[\Override]
    public function decodeVarbinary(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Check if the database supports STRAIGHT JOIN
     */
    #[\Override]
    public function supportStraightJoin(): bool
    {
        return false; // Override in MySQL adapter
    }

    /**
     * Render SQL FOR UPDATE clause
     */
    #[\Override]
    public function forUpdate(string $sql): string
    {
        return $sql . ' FOR UPDATE';
    }

    // =========================================================================
    // Insert Helper Methods
    // =========================================================================

    /**
     * Prepare row data for insert
     *
     * @param mixed $row Row data
     * @param array $bind Bind array (passed by reference)
     * @return string Prepared value string
     */
    protected function _prepareInsertData(mixed $row, array &$bind): string
    {
        if (is_array($row)) {
            $line = [];
            foreach ($row as $value) {
                if ($value instanceof Expr) {
                    $line[] = $value->__toString();
                } else {
                    $line[] = '?';
                    $bind[] = $value;
                }
            }
            $line = implode(', ', $line);
        } elseif ($row instanceof Expr) {
            $line = $row->__toString();
        } else {
            $line = '?';
            $bind[] = $row;
        }

        return sprintf('(%s)', $line);
    }

    /**
     * Return insert sql query
     *
     * @param string $tableName Table name
     * @param array $columns Column names
     * @param array $values Value strings
     * @return string SQL query
     */
    protected function _getInsertSqlQuery(string $tableName, array $columns, array $values): string
    {
        $tableName = $this->quoteIdentifier($tableName, true);
        $columns = array_map([$this, 'quoteIdentifier'], $columns);
        $columns = implode(',', $columns);
        $values = implode(', ', $values);

        return sprintf('INSERT INTO %s (%s) VALUES %s', $tableName, $columns, $values);
    }

    // =========================================================================
    // Schema Introspection Methods
    // =========================================================================

    /**
     * Returns the column descriptions for a table
     */
    #[\Override]
    public function describeTable(string $tableName, ?string $schemaName = null): array
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_DESCRIBE);
        if ($ddl === false) {
            $ddl = $this->_loadTableDescription($tableName, $schemaName);
            $this->saveDdlCache($cacheKey, self::DDL_DESCRIBE, $ddl);
        }

        return $ddl;
    }

    /**
     * Internal method to load table description from database
     */
    protected function _loadTableDescription(string $tableName, ?string $schemaName = null): array
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $columns = $schemaManager->introspectTableColumnsByUnquotedName($tableName);

        // Get primary key columns - DBAL 4.x doesn't include primary key in indexes list
        $primaryColumns = [];
        try {
            $tableNameObj = \Doctrine\DBAL\Schema\Name\OptionallyQualifiedName::unquoted($tableName);
            $primaryKeyConstraint = $schemaManager->introspectTablePrimaryKeyConstraint($tableNameObj);
            if ($primaryKeyConstraint !== null) {
                // getColumnNames() returns array of UnqualifiedName objects
                $primaryColumns = array_map(
                    fn($col) => strtolower($col->getIdentifier()->getValue()),
                    $primaryKeyConstraint->getColumnNames(),
                );
            }
        } catch (\Doctrine\DBAL\Exception $e) {
            // Table might not have a primary key
        }

        $result = [];
        $position = 1;
        $primaryPosition = 1;

        foreach ($columns as $column) {
            // Get column name from the Column object itself (not from array key which is numeric)
            // Use getIdentifier()->getValue() to get the raw name without quotes
            $columnNameStr = $column->getObjectName()->getIdentifier()->getValue();
            $isPrimary = in_array(strtolower($columnNameStr), $primaryColumns);
            $typeName = $column->getType()::class;
            // Extract short type name from class
            $typeName = substr($typeName, strrpos($typeName, '\\') + 1);
            $typeName = strtolower(preg_replace('/Type$/', '', $typeName));

            $result[$columnNameStr] = [
                'SCHEMA_NAME'      => $schemaName ?? '',
                'TABLE_NAME'       => $tableName,
                'COLUMN_NAME'      => $columnNameStr,
                'COLUMN_POSITION'  => $position++,
                'DATA_TYPE'        => $typeName,
                'DEFAULT'          => $column->getDefault(),
                'NULLABLE'         => !$column->getNotnull(),
                'LENGTH'           => $column->getLength(),
                'SCALE'            => $column->getScale(),
                'PRECISION'        => $column->getPrecision(),
                'UNSIGNED'         => $column->getUnsigned(),
                'PRIMARY'          => $isPrimary,
                'PRIMARY_POSITION' => $isPrimary ? $primaryPosition++ : null,
                'IDENTITY'         => $column->getAutoincrement(),
            ];
        }

        return $result;
    }

    /**
     * Returns the table index information
     */
    #[\Override]
    public function getIndexList(string $tableName, ?string $schemaName = null): array
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_INDEX);
        if ($ddl === false) {
            $ddl = $this->_loadIndexList($tableName, $schemaName);
            $this->saveDdlCache($cacheKey, self::DDL_INDEX, $ddl);
        }

        return $ddl;
    }

    /**
     * Internal method to load index list from database
     */
    protected function _loadIndexList(string $tableName, ?string $schemaName = null): array
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $indexes = $schemaManager->introspectTableIndexesByUnquotedName($tableName);

        $result = [];

        // First, check for primary key constraint (DBAL returns this separately)
        $tableNameObj = \Doctrine\DBAL\Schema\Name\OptionallyQualifiedName::unquoted($tableName);
        $primaryKey = $schemaManager->introspectTablePrimaryKeyConstraint($tableNameObj);
        if ($primaryKey !== null) {
            $pkName = $primaryKey->getObjectName();
            $pkNameStr = $pkName ? $pkName->getIdentifier()->getValue() : $tableName . '_pkey';
            $pkColumns = array_map(
                fn($col) => $col->getIdentifier()->getValue(),
                $primaryKey->getColumnNames(),
            );
            $result['PRIMARY'] = [
                'SCHEMA_NAME'   => $schemaName ?? '',
                'TABLE_NAME'    => $tableName,
                'KEY_NAME'      => $pkNameStr,
                'COLUMNS_LIST'  => $pkColumns,
                'INDEX_TYPE'    => AdapterInterface::INDEX_TYPE_PRIMARY,
                'INDEX_METHOD'  => '',
                'type'          => AdapterInterface::INDEX_TYPE_PRIMARY,
                'fields'        => $pkColumns,
            ];
        }

        // Then process regular indexes
        foreach ($indexes as $index) {
            // Get index name from the Index object itself (not from array key which is numeric)
            $indexNameStr = $index->getObjectName()->getIdentifier()->getValue();

            // Determine index type based on index class or type
            $indexType = AdapterInterface::INDEX_TYPE_INDEX;

            /** @var \Doctrine\DBAL\Schema\Index $index */
            if ($index->getType() === \Doctrine\DBAL\Schema\Index\IndexType::UNIQUE) {
                $indexType = AdapterInterface::INDEX_TYPE_UNIQUE;
            }

            $keyName = strtoupper($indexNameStr);
            $columns = array_map(
                fn($col) => $col->getColumnName()->getIdentifier()->getValue(),
                $index->getIndexedColumns(),
            );

            $result[$keyName] = [
                'SCHEMA_NAME'   => $schemaName ?? '',
                'TABLE_NAME'    => $tableName,
                'KEY_NAME'      => $indexNameStr,
                'COLUMNS_LIST'  => $columns,
                'INDEX_TYPE'    => $indexType,
                'INDEX_METHOD'  => '', // DBAL doesn't expose this
                'type'          => $indexType,
                'fields'        => $columns,
            ];
        }

        return $result;
    }

    /**
     * Retrieve the foreign keys descriptions for a table
     */
    #[\Override]
    public function getForeignKeys(string $tableName, ?string $schemaName = null): array
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_FOREIGN_KEY);
        if ($ddl === false) {
            $ddl = $this->_loadForeignKeys($tableName, $schemaName);
            $this->saveDdlCache($cacheKey, self::DDL_FOREIGN_KEY, $ddl);
        }

        return $ddl;
    }

    /**
     * Internal method to load foreign keys from database
     */
    protected function _loadForeignKeys(string $tableName, ?string $schemaName = null): array
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $foreignKeys = $schemaManager->introspectTableForeignKeyConstraintsByUnquotedName($tableName);

        $result = [];
        foreach ($foreignKeys as $fk) {
            // Get FK name from the ForeignKeyConstraint object itself (not from array key which is numeric)
            $fkName = $fk->getObjectName();
            $fkNameStr = $fkName ? $fkName->getIdentifier()->getValue() : '';

            // Get column names as strings (DBAL returns UnqualifiedName objects)
            $localColumns = $fk->getReferencingColumnNames();
            $localColumnStr = empty($localColumns) ? '' : $localColumns[0]->getIdentifier()->getValue();

            $foreignColumns = $fk->getReferencedColumnNames();
            $foreignColumnStr = empty($foreignColumns) ? '' : $foreignColumns[0]->getIdentifier()->getValue();

            // Get referenced table name as string (DBAL returns OptionallyQualifiedName object)
            $refTableName = $fk->getReferencedTableName();
            $refTableNameStr = $refTableName->getUnqualifiedName()->getValue();

            $result[strtoupper($fkNameStr) ?: 'FK_' . count($result)] = [
                'FK_NAME'         => $fkNameStr,
                'SCHEMA_NAME'     => $schemaName ?? '',
                'TABLE_NAME'      => $tableName,
                'COLUMN_NAME'     => $localColumnStr,
                'REF_SCHEMA_NAME' => $schemaName ?? '',
                'REF_TABLE_NAME'  => $refTableNameStr,
                'REF_COLUMN_NAME' => $foreignColumnStr,
                'ON_DELETE'       => $fk->getOnDeleteAction()->value,
                'ON_UPDATE'       => $fk->getOnUpdateAction()->value,
            ];
        }

        return $result;
    }

    /**
     * Checks if table exists
     */
    #[\Override]
    public function isTableExists(string $tableName, ?string $schemaName = null): bool
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        return $schemaManager->tablesExist([$tableName]);
    }

    /**
     * Returns list of tables in the database
     *
     * @return string[] List of table names
     */
    #[\Override]
    public function listTables(?string $schemaName = null): array
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();
        $names = $schemaManager->introspectTableNames();
        // Use getUnqualifiedName()->getValue() to get unquoted table name
        return array_map(fn($name) => $name->getUnqualifiedName()->getValue(), $names);
    }

    /**
     * Try to find installed primary key name
     */
    #[\Override]
    public function getPrimaryKeyName(string $tableName, ?string $schemaName = null): string
    {
        $indexes = $this->getIndexList($tableName, $schemaName);
        if (isset($indexes['PRIMARY'])) {
            return $indexes['PRIMARY']['KEY_NAME'];
        }

        // Generate default name based on database conventions
        return $tableName . '_pkey';
    }

    // =========================================================================
    // Common Helper Methods
    // =========================================================================

    /**
     * Return DDL type from column options
     */
    protected function _getDdlType(array $options): ?string
    {
        $ddlType = null;
        if (isset($options['TYPE'])) {
            $ddlType = $options['TYPE'];
        } elseif (isset($options['COLUMN_TYPE'])) {
            $ddlType = $options['COLUMN_TYPE'];
        }

        return $ddlType;
    }

    /**
     * Return DDL action for foreign key
     */
    protected function _getDdlAction(string $action): string
    {
        return match ($action) {
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE => \Maho\Db\Ddl\Table::ACTION_CASCADE,
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL => \Maho\Db\Ddl\Table::ACTION_SET_NULL,
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_RESTRICT => \Maho\Db\Ddl\Table::ACTION_RESTRICT,
            default => \Maho\Db\Ddl\Table::ACTION_NO_ACTION,
        };
    }

    /**
     * Prepare SQL date condition
     */
    protected function _prepareSqlDateCondition(array $condition, string $key): \Maho\Db\Expr|int|string
    {
        if (empty($condition['date'])) {
            if (empty($condition['datetime'])) {
                $result = $condition[$key];
            } else {
                $result = $this->formatDate($condition[$key]);
            }
        } else {
            $result = $this->formatDate($condition[$key]);
        }

        return $result;
    }

    /**
     * Parse text size
     * Returns max allowed size if value is greater than max
     */
    protected function _parseTextSize(string|int $size): int
    {
        $size = trim((string) $size);
        $last = strtolower(substr($size, -1));

        switch ($last) {
            case 'k':
                $size = (int) $size * 1024;
                break;
            case 'm':
                $size = (int) $size * 1024 * 1024;
                break;
            case 'g':
                $size = (int) $size * 1024 * 1024 * 1024;
                break;
        }

        if (empty($size)) {
            return \Maho\Db\Ddl\Table::DEFAULT_TEXT_SIZE;
        }
        if ($size >= \Maho\Db\Ddl\Table::MAX_TEXT_SIZE) {
            return \Maho\Db\Ddl\Table::MAX_TEXT_SIZE;
        }

        return (int) $size;
    }

    /**
     * Retrieve the foreign keys tree for all tables
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getForeignKeysTree(): array
    {
        $tree = [];
        foreach ($this->listTables() as $table) {
            foreach ($this->getForeignKeys($table) as $key) {
                $tree[$table][$key['COLUMN_NAME']] = $key;
            }
        }

        return $tree;
    }

    /**
     * Convert column data from describe table to column create options
     *
     * @return array{name: string, type: ?string, length: ?int, options: array<string, mixed>, comment: string}
     */
    public function getColumnCreateByDescribe(array $columnData): array
    {
        $type = $this->_getColumnTypeByDdl($columnData);
        $options = [];

        if ($columnData['IDENTITY'] === true) {
            $options['identity'] = true;
        }
        if (($columnData['UNSIGNED'] ?? false) === true) {
            $options['unsigned'] = true;
        }
        if ($columnData['NULLABLE'] === false
            && !($type == \Maho\Db\Ddl\Table::TYPE_TEXT && isset($columnData['DEFAULT']) && strlen((string) $columnData['DEFAULT']) != 0)
        ) {
            $options['nullable'] = false;
        }
        if ($columnData['PRIMARY'] === true) {
            $options['primary'] = true;
        }
        // Skip default for identity columns (they use sequences automatically)
        // Also skip if the default contains nextval() which is a PostgreSQL sequence function
        if (!is_null($columnData['DEFAULT'])
            && $type != \Maho\Db\Ddl\Table::TYPE_TEXT
            && $columnData['IDENTITY'] !== true
            && !str_contains((string) $columnData['DEFAULT'], 'nextval(')
        ) {
            $options['default'] = $this->quote($columnData['DEFAULT']);
        }
        if (isset($columnData['SCALE']) && (string) $columnData['SCALE'] !== '') {
            $options['scale'] = $columnData['SCALE'];
        }
        if (isset($columnData['PRECISION']) && (string) $columnData['PRECISION'] !== '') {
            $options['precision'] = $columnData['PRECISION'];
        }

        $comment = uc_words($columnData['COLUMN_NAME'], ' ');

        return [
            'name'      => $columnData['COLUMN_NAME'],
            'type'      => $type,
            'length'    => $columnData['LENGTH'],
            'options'   => $options,
            'comment'   => $comment,
        ];
    }

    /**
     * Retrieve column data type by data from describe table
     * This method should be overridden in platform-specific adapters
     */
    protected function _getColumnTypeByDdl(array $column): ?string
    {
        $type = $column['DATA_TYPE'] ?? null;
        if ($type === null) {
            return null;
        }

        // Generic mapping - uses both DBAL type names (from class) and raw database type names
        return match (strtolower($type)) {
            'int', 'integer', 'serial' => \Maho\Db\Ddl\Table::TYPE_INTEGER,
            'smallint', 'smallserial' => \Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'bigint', 'bigserial' => \Maho\Db\Ddl\Table::TYPE_BIGINT,
            'numeric', 'decimal' => \Maho\Db\Ddl\Table::TYPE_DECIMAL,
            'real', 'float', 'double', 'double precision', 'smallfloat' => \Maho\Db\Ddl\Table::TYPE_FLOAT,
            'string', 'varchar', 'character varying' => \Maho\Db\Ddl\Table::TYPE_VARCHAR,
            'text', 'mediumtext', 'longtext' => \Maho\Db\Ddl\Table::TYPE_TEXT,
            'blob', 'mediumblob', 'longblob', 'bytea', 'binary' => \Maho\Db\Ddl\Table::TYPE_BLOB,
            'boolean', 'bool', 'tinyint' => \Maho\Db\Ddl\Table::TYPE_BOOLEAN,
            'date' => \Maho\Db\Ddl\Table::TYPE_DATE,
            'datetime', 'timestamp', 'timestamp without time zone', 'timestamp with time zone', 'datetimetz' => \Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            default => null,
        };
    }

    /**
     * Purge orphan records - must be implemented by platform-specific adapter
     * as the DELETE ... JOIN syntax varies between databases
     */
    abstract public function purgeOrphanRecords(
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
    ): self;

    /**
     * Change table comment - must be implemented by platform-specific adapter
     * as the syntax varies between databases
     */
    abstract public function changeTableComment(string $tableName, string $comment, ?string $schemaName = null): mixed;

    /**
     * Start an OpenTelemetry span for a database query
     *
     * Call after _prepareQuery() so $sql is a string and $bind is normalized.
     */
    protected function _startQuerySpan(string $sql, array $bind): ?\Maho_OpenTelemetry_Model_Span
    {
        $span = \Mage::startSpan('db.query', [
            'db.system' => $this->_getDbSystem(),
            'db.name' => $this->_config['dbname'] ?? '',
            'db.statement' => $this->_interpolateQuery($sql, $bind),
            'db.operation' => $this->_getOperationType($sql),
        ]);

        if ($span) {
            $table = $this->_getTargetTable($sql);
            if ($table) {
                $span->setAttribute('db.sql.table', $table);
            }
        }

        return $span;
    }

    /**
     * Get the OTel db.system identifier for this adapter
     */
    protected function _getDbSystem(): string
    {
        return 'other_sql';
    }

    /**
     * Interpolate bind values into the SQL query for tracing
     *
     * Produces a human-readable query with actual values. This is only used
     * for the trace span attribute, never for execution.
     */
    protected function _interpolateQuery(string $sql, array $bind): string
    {
        if (empty($bind)) {
            return $sql;
        }

        $position = 0;
        return preg_replace_callback('/\?/', function () use ($bind, &$position) {
            if (!array_key_exists($position, $bind)) {
                $position++;
                return '?';
            }
            $value = $bind[$position];
            $position++;
            if ($value === null) {
                return 'NULL';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
            // Truncate long values to keep spans manageable
            $str = (string) $value;
            if (strlen($str) > 100) {
                $str = substr($str, 0, 100) . '...';
            }
            return "'" . addslashes($str) . "'";
        }, $sql);
    }

    /**
     * Get SQL operation type (SELECT, INSERT, UPDATE, DELETE, etc.)
     */
    protected function _getOperationType(string $sql): string
    {
        $sql = trim($sql);
        $firstSpace = strpos($sql, ' ');
        return $firstSpace !== false ? strtoupper(substr($sql, 0, $firstSpace)) : 'UNKNOWN';
    }

    /**
     * Extract the primary target table from a SQL query
     */
    protected function _getTargetTable(string $sql): string
    {
        if (preg_match('/\bFROM\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if (preg_match('/\bINTO\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        if (preg_match('/\bUPDATE\s+[`"]?(\w+)[`"]?/i', $sql, $m)) {
            return $m[1];
        }
        return '';
    }
}
