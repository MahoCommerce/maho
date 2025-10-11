<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter\Pdo;

use Maho\Db\Helper;

class Mysql implements \Maho\Db\Adapter\AdapterInterface
{
    /**
     * Doctrine DBAL Connection
     *
     * @var \Doctrine\DBAL\Connection|null
     */
    protected $_connection = null;

    /**
     * Adapter configuration
     *
     * @var array
     */
    protected $_config = [];

    /**
     * Fetch mode
     *
     * @var int
     */
    protected $_fetchMode = \PDO::FETCH_ASSOC;

    /**
     * Numeric data types
     *
     * @var array
     */
    protected $_numericDataTypes = [
        'INT' => self::INT_TYPE,
        'SMALLINT' => self::INT_TYPE,
        'BIGINT' => self::BIGINT_TYPE,
        'FLOAT' => self::FLOAT_TYPE,
        'DECIMAL' => self::FLOAT_TYPE,
        'NUMERIC' => self::FLOAT_TYPE,
        'DOUBLE' => self::FLOAT_TYPE,
        'REAL' => self::FLOAT_TYPE,
    ];

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
    public const DDL_CACHE_PREFIX      = 'DB_PDO_MYSQL_DDL';
    public const DDL_CACHE_TAG         = 'DB_PDO_MYSQL_DDL';

    public const LENGTH_TABLE_NAME     = 64;
    public const LENGTH_INDEX_NAME     = 64;
    public const LENGTH_FOREIGN_NAME   = 64;

    /**
     * Those constants are defining the possible address types
     */
    public const ADDRESS_TYPE_HOSTNAME     = 'hostname';
    public const ADDRESS_TYPE_UNIX_SOCKET  = 'unix_socket';
    public const ADDRESS_TYPE_IPV4_ADDRESS = 'ipv4';
    public const ADDRESS_TYPE_IPV6_ADDRESS = 'ipv6';

    /**
     * MEMORY engine type for MySQL tables
     */
    public const ENGINE_MEMORY = 'MEMORY';

    /**
     * Default class name for a DB statement.
     *
     * @var string
     */
    protected $_defaultStmtClass = \Maho\Db\Statement\Pdo\Mysql::class;

    /**
     * Current Transaction Level
     *
     * @var int
     */
    protected $_transactionLevel    = 0;

    /**
     * Set attribute to connection flag
     *
     * @var bool
     */
    protected $_connectionFlagsSet  = false;

    /**
     * Tables DDL cache
     *
     * @var array
     */
    protected $_ddlCache            = [];

    /**
     * SQL bind params. Used temporarily by regexp callback.
     *
     * @var array
     */
    protected $_bindParams          = [];

    /**
     * Autoincrement for bind value. Used by regexp callback.
     *
     * @var int
     */
    protected $_bindIncrement       = 0;

    /**
     * Write SQL debug data to file
     *
     * @var bool
     */
    protected $_debug               = false;

    /**
     * Minimum query duration time to be logged
     *
     * @var float
     */
    protected $_logQueryTime        = 0.05;

    /**
     * Log all queries (ignored minimum query duration time)
     *
     * @var bool
     */
    protected $_logAllQueries       = false;

    /**
     * Path to SQL debug data log
     *
     * @var string
     */
    protected $_debugFile           = 'var/debug/pdo_mysql.log';

    /**
     * Io File Adapter
     *
     * @var \Varien_Io_File
     */
    protected $_debugIoAdapter;

    /**
     * Debug timer start value
     *
     * @var float
     */
    protected $_debugTimer          = 0;

    /**
     * Cache frontend adapter instance
     *
     * @var \Mage_Core_Model_Cache
     */
    protected $_cacheAdapter;

    /**
     * DDL cache allowing flag
     * @var bool
     */
    protected $_isDdlCacheAllowed = true;

    /**
     * MySQL column - Table DDL type pairs
     *
     * @var array
     */
    protected $_ddlColumnTypes      = [
        \Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'bool',
        \Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'smallint',
        \Maho\Db\Ddl\Table::TYPE_INTEGER       => 'int',
        \Maho\Db\Ddl\Table::TYPE_BIGINT        => 'bigint',
        \Maho\Db\Ddl\Table::TYPE_FLOAT         => 'float',
        \Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'decimal',
        \Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'decimal',
        \Maho\Db\Ddl\Table::TYPE_DATE          => 'date',
        \Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'timestamp',
        \Maho\Db\Ddl\Table::TYPE_DATETIME      => 'datetime',
        \Maho\Db\Ddl\Table::TYPE_TEXT          => 'text',
        \Maho\Db\Ddl\Table::TYPE_BLOB          => 'blob',
        \Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'blob',
    ];

    /**
     * All possible DDL statements
     * First 3 symbols for each statement
     *
     * @var array
     */
    protected $_ddlRoutines = ['alt', 'cre', 'ren', 'dro', 'tru'];

    /**
     * DDL statements for temporary tables
     *
     * @var string
     */
    protected $_tempRoutines =  '#^\w+\s+temporary\s#im';

    /**
     * Allowed interval units array
     *
     * @var array
     */
    protected $_intervalUnits = [
        self::INTERVAL_YEAR     => 'YEAR',
        self::INTERVAL_MONTH    => 'MONTH',
        self::INTERVAL_DAY      => 'DAY',
        self::INTERVAL_HOUR     => 'HOUR',
        self::INTERVAL_MINUTE   => 'MINUTE',
        self::INTERVAL_SECOND   => 'SECOND',
    ];

    /**
     * Hook callback to modify queries. Mysql specific property, designed only for backwards compatibility.
     *
     * @var array|null
     */
    protected $_queryHook = null;

    /**
     * Whether to automatically quote identifiers
     *
     * @var bool
     */
    protected $_autoQuoteIdentifiers = true;

    /**
     * Constructor
     *
     * @param array $config
     * @throws \Maho\Db\Exception
     */
    public function __construct($config)
    {
        if (!is_array($config)) {
            throw new \Maho\Db\Exception('Adapter configuration must be an array');
        }

        $this->_config = $config;

        // Set debug mode if configured
        if (isset($config['profiler']) && $config['profiler'] === true) {
            $this->_debug = true;
        }
    }

    /**
     * Returns the configuration variables in this adapter.
     *
     * @return array
     */
    #[\Override]
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Returns flag is transaction now?
     *
     * @return bool
     */
    public function isTransaction()
    {
        return (bool) $this->_transactionLevel;
    }

    /**
     * Begin new DB transaction for connection
     *
     * @return $this
     */
    #[\Override]
    public function beginTransaction()
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

    /**
     * Commit DB transaction
     *
     * @return $this
     */
    #[\Override]
    public function commit()
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

    /**
     * Rollback DB transaction
     *
     * @return $this
     */
    #[\Override]
    public function rollBack()
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

    /**
     * Get adapter transaction level state. Return 0 if all transactions are complete
     *
     * @return int
     */
    #[\Override]
    public function getTransactionLevel()
    {
        return $this->_transactionLevel;
    }

    /**
     * Fetches all SQL result rows as an array.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @param mixed $fetchMode Fetch mode.
     * @return array
     */
    #[\Override]
    public function fetchAll($sql, $bind = [], $fetchMode = null)
    {
        $stmt = $this->query($sql, $bind);
        if ($fetchMode === null) {
            $fetchMode = $this->_fetchMode;
        }
        return $stmt->fetchAll($fetchMode);
    }

    /**
     * Fetches the first row of the SQL result.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @param mixed $fetchMode Fetch mode.
     * @return array|false
     */
    #[\Override]
    public function fetchRow($sql, $bind = [], $fetchMode = null)
    {
        $stmt = $this->query($sql, $bind);
        if ($fetchMode === null) {
            $fetchMode = $this->_fetchMode;
        }
        return $stmt->fetch($fetchMode);
    }

    /**
     * Fetches the first column of all SQL result rows as an array.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @return array
     */
    #[\Override]
    public function fetchCol($sql, $bind = [])
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetches all SQL result rows as an array of key-value pairs.
     *
     * The first column is the key, the second column is the value.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @return array
     */
    #[\Override]
    public function fetchPairs($sql, $bind = [])
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * Fetches the first column of the first row of the SQL result.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @return mixed|false
     */
    #[\Override]
    public function fetchOne($sql, $bind = [])
    {
        $stmt = $this->query($sql, $bind);
        return $stmt->fetchColumn(0);
    }

    /**
     * Quote an identifier.
     *
     * @param string|array|\Maho\Db\Expr $ident The identifier.
     * @param bool $auto If true, auto-quote identifier. Default: false.
     * @return string The quoted identifier.
     */
    #[\Override]
    public function quoteIdentifier($ident, $auto = false)
    {
        return $this->_quoteIdentifierAs($ident, null, $auto);
    }

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array|\Maho\Db\Expr $ident The column identifier or expression.
     * @param string|null $alias An alias for the column.
     * @param bool $auto If true, auto-quote identifiers. Default: false.
     * @return string The quoted identifier and alias.
     */
    #[\Override]
    public function quoteColumnAs($ident, $alias, $auto = false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array|\Maho\Db\Expr $ident The table identifier or expression.
     * @param string|null $alias An alias for the table.
     * @param bool $auto If true, auto-quote identifiers. Default: false.
     * @return string The quoted identifier and alias.
     */
    #[\Override]
    public function quoteTableAs($ident, $alias = null, $auto = false)
    {
        return $this->_quoteIdentifierAs($ident, $alias, $auto);
    }

    /**
     * Quote an identifier and an optional alias.
     *
     * @param string|array|\Maho\Db\Expr $ident The identifier or expression.
     * @param string|null $alias An optional alias.
     * @param bool $auto If true, auto-quote identifiers. Default: false.
     * @param string $as The string to use for the AS keyword. Default: ' AS '.
     * @return string The quoted identifier and alias.
     */
    protected function _quoteIdentifierAs($ident, $alias = null, $auto = false, $as = ' AS ')
    {
        if ($ident instanceof \Maho\Db\Expr) {
            $quoted = $ident->__toString();
        } elseif ($ident instanceof \Maho\Db\Select) {
            $quoted = '(' . $ident->assemble() . ')';
        } else {
            if (is_string($ident)) {
                $ident = explode('.', $ident);
            }
            if (is_array($ident)) {
                $segments = [];
                foreach ($ident as $segment) {
                    // Skip empty segments to avoid creating invalid identifiers like 'table.'
                    if ($segment === '' || $segment === null) {
                        continue;
                    }
                    if ($segment instanceof \Maho\Db\Expr) {
                        $segments[] = $segment->__toString();
                    } else {
                        $segments[] = $this->_quoteIdentifier($segment, $auto);
                    }
                }
                // If all segments were empty, return asterisk (SELECT *)
                if (empty($segments)) {
                    $quoted = '*';
                } else {
                    if ($alias !== null && end($ident) == $alias) {
                        $alias = null;
                    }
                    $quoted = implode('.', $segments);
                }
            } else {
                $quoted = $this->_quoteIdentifier($ident, $auto);
            }
        }
        if ($alias !== null) {
            $quoted .= $as . $this->_quoteIdentifier($alias, $auto);
        }
        return $quoted;
    }

    /**
     * Quote an identifier.
     *
     * @param string $value The identifier.
     * @param bool $auto If true, auto-quote only if needed. Default: false.
     * @return string The quoted identifier.
     */
    protected function _quoteIdentifier($value, $auto = false)
    {
        if ($auto === false || $this->_autoQuoteIdentifiers === true) {
            $q = '`';
            return ($q . str_replace("$q", "$q$q", $value) . $q);
        }
        return $value;
    }

    /**
     * Fetches all SQL result rows as an associative array.
     *
     * The first column is the key, the entire row array is the value.
     *
     * @param string|\Maho\Db\Select $sql SQL query.
     * @param mixed $bind Data to bind into SELECT placeholders.
     * @return array
     */
    #[\Override]
    public function fetchAssoc($sql, $bind = [])
    {
        $stmt = $this->query($sql, $bind);
        $data = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[current($row)] = $row;
        }
        return $data;
    }

    /**
     * Convert an array, string, or \Maho\Db\Expr object into a string to put in a WHERE clause.
     *
     * @param mixed $where
     * @return string
     */
    protected function _whereExpr($where)
    {
        if (empty($where)) {
            return '';
        }
        if (!is_array($where)) {
            $where = [$where];
        }
        foreach ($where as $cond => &$term) {
            // is $cond an int? (i.e. Not a condition)
            if (is_int($cond)) {
                // $term is the full condition
                if ($term instanceof \Maho\Db\Expr) {
                    $term = $term->__toString();
                }
            } else {
                // $cond is the condition with placeholder,
                // and $term is quoted into the condition
                $term = $this->quoteInto($cond, $term);
            }
        }

        return implode(' AND ', $where);
    }

    /**
     * Convert date to DB format
     *
     * @param   int|string|\DateTime $date
     * @return  \Maho\Db\Expr
     */
    public function convertDate($date)
    {
        return $this->formatDate($date, false);
    }

    /**
     * Convert date and time to DB format
     *
     * @param   int|string|\DateTime $datetime
     * @return  \Maho\Db\Expr
     */
    public function convertDateTime($datetime)
    {
        return $this->formatDate($datetime);
    }

    /**
     * Parse a source hostname and generate a host info
     * @param string $hostName
     *
     * @return \Varien_Object
     */
    protected function _getHostInfo($hostName)
    {
        $hostInfo = new \Varien_Object();
        $matches = [];
        if (str_contains($hostName, '/')) {
            $hostInfo->setAddressType(self::ADDRESS_TYPE_UNIX_SOCKET)
                ->setUnixSocket($hostName);
        } elseif (preg_match('/^\[(([0-9a-f]{1,4})?(:([0-9a-f]{1,4})?){1,}:([0-9a-f]{1,4}))(%[0-9a-z]+)?\](:([0-9]+))?$/i', $hostName, $matches)) {
            $hostName = $matches[1];
            $hostName .= $matches[6] ?? '';
            $hostInfo->setAddressType(self::ADDRESS_TYPE_IPV6_ADDRESS)
                ->setHostName($hostName)
                ->setPort($matches[8] ?? null);
        } elseif (preg_match('/^(([0-9a-f]{1,4})?(:([0-9a-f]{1,4})?){1,}:([0-9a-f]{1,4}))(%[0-9a-z]+)?$/i', $hostName, $matches)) {
            $hostName = $matches[1];
            $hostName .= $matches[6] ?? '';
            $hostInfo->setAddressType(self::ADDRESS_TYPE_IPV6_ADDRESS)
                ->setHostName($hostName);
        } elseif (str_contains($hostName, ':')) {
            [$hostAddress, $hostPort] = explode(':', $hostName);
            $hostInfo->setAddressType(
                filter_var($hostAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    ? self::ADDRESS_TYPE_IPV4_ADDRESS
                    : self::ADDRESS_TYPE_HOSTNAME,
            )->setHostName($hostAddress)
                ->setPort($hostPort);
        } else {
            $hostInfo->setAddressType(
                filter_var($hostName, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    ? self::ADDRESS_TYPE_IPV4_ADDRESS
                    : self::ADDRESS_TYPE_HOSTNAME,
            )->setHostName($hostName);
        }

        return $hostInfo;
    }

    /**
     * Creates a PDO object and connects to the database.
     *
     * @throws \RuntimeException
     */
    protected function _connect(): void
    {
        if ($this->_connection) {
            return;
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new \RuntimeException('pdo_mysql extension is not installed');
        }

        $hostInfo = $this->_getHostInfo($this->_config['host'] ?? $this->_config['unix_socket'] ?? null);

        switch ($hostInfo->getAddressType()) {
            case self::ADDRESS_TYPE_UNIX_SOCKET:
                $this->_config['unix_socket'] = $hostInfo->getUnixSocket();
                unset($this->_config['host']);
                break;
            case self::ADDRESS_TYPE_IPV6_ADDRESS: // break intentionally omitted
            case self::ADDRESS_TYPE_IPV4_ADDRESS: // break intentionally omitted
            case self::ADDRESS_TYPE_HOSTNAME:
                $this->_config['host'] = $hostInfo->getHostName();
                if ($hostInfo->getPort()) {
                    $this->_config['port'] = $hostInfo->getPort();
                }
                break;
            default:
                break;
        }

        $this->_debugTimer();

        // Create Doctrine DBAL connection
        $params = [
            'driver' => 'pdo_mysql',
            'user' => $this->_config['username'] ?? '',
            'password' => $this->_config['password'] ?? '',
            'dbname' => $this->_config['dbname'] ?? '',
            'charset' => $this->_config['charset'] ?? 'utf8',
        ];

        if (isset($this->_config['unix_socket'])) {
            $params['unix_socket'] = $this->_config['unix_socket'];
        } else {
            $params['host'] = $this->_config['host'] ?? 'localhost';
            if (isset($this->_config['port'])) {
                $params['port'] = $this->_config['port'];
            }
        }

        $driverOptions = [];
        if (!$this->_connectionFlagsSet) {
            $driverOptions[\PDO::ATTR_EMULATE_PREPARES] = true;
            $driverOptions[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        if (!empty($driverOptions)) {
            $params['driverOptions'] = $driverOptions;
        }

        $this->_connection = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_debugStat(self::DEBUG_CONNECT, '');

        /** @link http://bugs.mysql.com/bug.php?id=18551 */
        $this->_connection->executeStatement("SET SQL_MODE=''");

        $this->_connectionFlagsSet = true;
    }

    /**
     * Run RAW Query
     *
     * @param string $sql
     * @return \Maho\Db\Statement\Pdo\Mysql
     * @throws \PDOException
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function raw_query($sql)
    {
        $lostConnectionMessage = 'SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query';
        $tries = 0;
        do {
            $retry = false;
            try {
                $result = $this->query($sql);
            } catch (\Exception $e) {
                // Convert to PDOException to maintain backwards compatibility with usage of MySQL adapter
                if ($e instanceof \RuntimeException) {
                    $e = $e->getPrevious();
                    if (!($e instanceof \PDOException)) {
                        $e = new \PDOException($e->getMessage(), $e->getCode());
                    }
                }
                // Check to reconnect
                if ($tries < 10 && $e->getMessage() == $lostConnectionMessage) {
                    $retry = true;
                    $tries++;
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
     * @param string $sql
     * @param string|int $field
     * @return array|string|false
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function raw_fetchRow($sql, $field = null)
    {
        $result = $this->raw_query($sql);
        if (!$result) {
            return false;
        }

        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        if (empty($field)) {
            return $row;
        } else {
            return $row[$field] ?? false;
        }
    }

    /**
     * Check transaction level in case of DDL query
     *
     * @param string|\Maho\Db\Select $sql
     * @throws \RuntimeException
     * @return void
     */
    protected function _checkDdlTransaction($sql)
    {
        if (is_string($sql) && $this->getTransactionLevel() > 0) {
            $startSql = strtolower(substr(ltrim($sql), 0, 3));
            if (in_array($startSql, $this->_ddlRoutines)
                && (preg_match($this->_tempRoutines, $sql) !== 1)
            ) {
                throw new \Maho\Db\Exception(\Maho\Db\Adapter\AdapterInterface::ERROR_DDL_MESSAGE);
            }
        }
    }

    /**
     * Special handling for PDO query().
     * All bind parameter names must begin with ':'.
     *
     * @param string|\Maho\Db\Select $sql The SQL statement with placeholders.
     * @param mixed $bind An array of data or data itself to bind to the placeholders.
     * @return \Maho\Db\Statement\Pdo\Mysql
     * @throws \RuntimeException To re-throw PDOException.
     */
    #[\Override]
    public function query($sql, $bind = [])
    {
        $this->_debugTimer();
        try {
            $this->_checkDdlTransaction($sql);
            $this->_prepareQuery($sql, $bind);

            // Connect if not already connected
            $this->_connect();

            // $sql is already converted to string by _prepareQuery()
            // Execute query using Doctrine DBAL
            // Doctrine DBAL 4 uses executeQuery() for SELECT and executeStatement() for DML
            $result = $this->_connection->prepare($sql);

            // Execute with parameters
            if (!empty($bind)) {
                $result = $this->_connection->executeQuery($sql, $bind);
            } else {
                $result = $this->_connection->executeQuery($sql);
            }

            // Wrap the result in \Maho\Db\Statement\Pdo\Mysql for compatibility
            $result = new \Maho\Db\Statement\Pdo\Mysql($this, $result);
        } catch (\Exception $e) {
            $this->_debugStat(self::DEBUG_QUERY, $sql, $bind);

            // Detect implicit rollback - MySQL SQLSTATE: ER_LOCK_WAIT_TIMEOUT or ER_LOCK_DEADLOCK
            if ($this->_transactionLevel > 0
                && $e->getPrevious() && isset($e->getPrevious()->errorInfo[1])
                && in_array($e->getPrevious()->errorInfo[1], [1205, 1213])
            ) {
                if ($this->_debug) {
                    $this->_debugWriteToFile('IMPLICIT ROLLBACK AFTER SQLSTATE: ' . $e->getPrevious()->errorInfo[1]);
                }
                $this->_transactionLevel = 1; // Deadlock rolls back entire transaction
                $this->rollBack();
            }

            $this->_debugException($e);
        }
        $this->_debugStat(self::DEBUG_QUERY, $sql, $bind, $result);
        return $result;
    }

    /**
     * Prepares SQL query by moving to bind all special parameters that can be confused with bind placeholders
     * (e.g. "foo:bar"). And also changes named bind to positional one, because underlying library has problems
     * with named binds.
     *
     * @param \Maho\Db\Select|string $sql
     * @param-out string $sql
     * @param mixed $bind
     * @return $this
     */
    protected function _prepareQuery(&$sql, &$bind = [])
    {
        $sql = (string) $sql;
        if (!is_array($bind)) {
            $bind = [$bind];
        }

        // Mixed bind is not supported - so remember whether it is named bind, to normalize later if required
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

        // Doctrine DBAL doesn't support named parameters well, so convert to positional
        if ($isNamedBind) {
            $this->_convertMixedBind($sql, $bind);
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
     * Callback function for preparation of query and bind by regexp.
     * Checks query parameters for special symbols and moves such parameters to bind array as named ones.
     * This method writes to $_bindParams, where query bind parameters are kept.
     * This method requires further normalizing, if bind array is positional.
     *
     * @param array $matches
     * @return string
     */
    public function proccessBindCallback($matches)
    {
        if (isset($matches[6])
            && (
                str_contains($matches[6], "'")
                || str_contains($matches[6], ':')
                || str_contains($matches[6], '?')
            )
        ) {
            $bindName = ':_mage_bind_var_' . (++$this->_bindIncrement);
            $this->_bindParams[$bindName] = $this->_unQuote($matches[6]);
            return ' ' . $bindName;
        }
        return $matches[0];
    }

    /**
     * Unquote raw string (use for auto-bind)
     *
     * @param string $string
     * @return string
     */
    protected function _unQuote($string)
    {
        $translate = [
            '\\000' => "\000",
            '\\n'   => "\n",
            '\\r'   => "\r",
            '\\\\'  => '\\',
            "\'"    => "'",
            '\\"'  => '"',
            '\\032' => "\032",
        ];
        return strtr($string, $translate);
    }

    /**
     * Normalizes mixed positional-named bind to positional bind, and replaces named placeholders in query to
     * '?' placeholders.
     *
     * @param string $sql
     * @param array $bind
     * @return $this
     */
    protected function _convertMixedBind(&$sql, &$bind)
    {
        $positions  = [];
        $offset     = 0;
        // get positions
        while (true) {
            $pos = strpos($sql, '?', $offset);
            if ($pos !== false) {
                $positions[] = $pos;
                $offset      = ++$pos;
            } else {
                break;
            }
        }

        $bindResult = [];
        $map = [];
        foreach ($bind as $k => $v) {
            // positional
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
     * $hook must be either array with 'object' and 'method' entries, or null to remove hook.
     * Previous hook is returned.
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

    /**
     * Executes a SQL statement(s)
     *
     * @param string $sql
     * @throws \Maho\Db\Exception
     * @return array
     */
    #[\Override]
    public function multiQuery($sql)
    {
        return $this->multi_query($sql);
    }

    /**
     * Run Multi Query
     *
     * @param string $sql
     * @return array
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function multi_query($sql)
    {
        ##$result = $this->raw_query($sql);

        #$this->beginTransaction();
        try {
            $stmts = $this->_splitMultiQuery($sql);
            $result = [];
            foreach ($stmts as $stmt) {
                $result[] = $this->raw_query($stmt);
            }
            #$this->commit();
        } catch (\Exception $e) {
            #$this->rollback();
            throw $e;
        }

        $this->resetDdlCache();

        return $result;
    }

    /**
     * Split multi statement query
     *
     * @param string $sql
     * @return array
     */
    protected function _splitMultiQuery($sql)
    {
        $parts = preg_split(
            '#(;|\'|"|\\\\|//|--|\n|/\*|\*/)#',
            $sql,
            -1,
            PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE,
        );

        $q      = false;
        $c      = false;
        $stmts  = [];
        $s      = '';

        foreach ($parts as $i => $part) {
            // strings
            if (($part === "'" || $part === '"') && ($i === 0 || $parts[$i - 1] !== '\\')) {
                if ($q === false) {
                    $q = $part;
                } elseif ($q === $part) {
                    $q = false;
                }
            }

            // single line comments
            if (($part === '//' || $part === '--') && ($i === 0 || $parts[$i - 1] === "\n")) {
                $c = $part;
            } elseif ($part === "\n" && ($c === '//' || $c === '--')) {
                $c = false;
            }

            // multi line comments
            if ($part === '/*' && $c === false) {
                $c = '/*';
            } elseif ($part === '*/' && $c === '/*') {
                $c = false;
            }

            // statements
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
     * @param string $tableName
     * @param string $fkName
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function dropForeignKey($tableName, $fkName, $schemaName = null)
    {
        $foreignKeys = $this->getForeignKeys($tableName, $schemaName);
        $fkName = strtoupper($fkName);
        if (str_starts_with($fkName, 'FK_')) {
            $fkName = substr($fkName, 3);
        }
        foreach ([$fkName, 'FK_' . $fkName] as $key) {
            if (isset($foreignKeys[$key])) {
                $sql = sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
                    $this->quoteIdentifier($foreignKeys[$key]['FK_NAME']),
                );
                $this->resetDdlCache($tableName, $schemaName);
                $this->raw_query($sql);
            }
        }
        return $this;
    }

    /**
     * Prepare table before add constraint foreign key
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $refTableName
     * @param string $refColumnName
     * @param string $onDelete
     * @return $this
     */
    public function purgeOrphanRecords(
        $tableName,
        $columnName,
        $refTableName,
        $refColumnName,
        $onDelete = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
    ) {
        $onDelete = strtoupper($onDelete);
        if ($onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE
            || $onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_RESTRICT
        ) {
            $sql = sprintf(
                'DELETE p.* FROM %s AS p LEFT JOIN %s AS r ON p.%s = r.%s WHERE r.%s IS NULL',
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($refTableName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
                $this->quoteIdentifier($refColumnName),
            );
            $this->raw_query($sql);
        } elseif ($onDelete == \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL) {
            $sql = sprintf(
                'UPDATE %s AS p LEFT JOIN %s AS r ON p.%s = r.%s SET p.%s = NULL WHERE r.%s IS NULL',
                $this->quoteIdentifier($tableName),
                $this->quoteIdentifier($refTableName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
                $this->quoteIdentifier($columnName),
                $this->quoteIdentifier($refColumnName),
            );
            $this->raw_query($sql);
        }

        return $this;
    }

    /**
     * Check does table column exist
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $schemaName
     * @return boolean
     */
    #[\Override]
    public function tableColumnExists($tableName, $columnName, $schemaName = null)
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
     * Generally $defintion must be array with column data to keep this call cross-DB compatible.
     * Using string as $definition is allowed only for concrete DB adapter.
     * Adds primary key if needed
     *
     * @param   string $tableName
     * @param   string $columnName
     * @param   array|string $definition  string specific or universal array DB Server definition
     * @param   string $schemaName
     * @return  int|boolean|\Maho\Db\Statement\Pdo\Mysql
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function addColumn($tableName, $columnName, $definition, $schemaName = null)
    {
        if ($this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return true;
        }

        $primaryKey = '';
        if (is_array($definition)) {
            $definition = array_change_key_case($definition, CASE_UPPER);
            if (empty($definition['COMMENT'])) {
                throw new \Maho\Db\Exception('Impossible to create a column without comment.');
            }
            if (!empty($definition['PRIMARY'])) {
                $primaryKey = sprintf(', ADD PRIMARY KEY (%s)', $this->quoteIdentifier($columnName));
            }
            $definition = $this->_getColumnDefinition($definition);
        }

        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s %s',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            $this->quoteIdentifier($columnName),
            $definition,
            $primaryKey,
        );

        $result = $this->raw_query($sql);

        $this->resetDdlCache($tableName, $schemaName);

        return $result;
    }

    /**
     * Delete table column
     *
     * @param string $tableName
     * @param string $columnName
     * @param string $schemaName
     * @return true|\Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function dropColumn($tableName, $columnName, $schemaName = null)
    {
        if (!$this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return true;
        }

        $alterDrop = [];

        $foreignKeys = $this->getForeignKeys($tableName, $schemaName);
        foreach ($foreignKeys as $fkProp) {
            if ($fkProp['COLUMN_NAME'] == $columnName) {
                $alterDrop[] = 'DROP FOREIGN KEY ' . $this->quoteIdentifier($fkProp['FK_NAME']);
            }
        }

        $alterDrop[] = 'DROP COLUMN ' . $this->quoteIdentifier($columnName);
        $sql = sprintf(
            'ALTER TABLE %s %s',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            implode(', ', $alterDrop),
        );

        $result = $this->raw_query($sql);
        $this->resetDdlCache($tableName, $schemaName);

        return $result;
    }

    /**
     * Change the column name and definition
     *
     * For change definition of column - use modifyColumn
     *
     * @param string $tableName
     * @param string $oldColumnName
     * @param string $newColumnName
     * @param array $definition
     * @param boolean $flushData        flush table statistic
     * @param string $schemaName
     * @return \Maho\Db\Statement\Pdo\Mysql|\Maho\Db\Statement\Pdo\Mysql
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function changeColumn(
        $tableName,
        $oldColumnName,
        $newColumnName,
        $definition,
        $flushData = false,
        $schemaName = null,
    ) {
        if (!$this->tableColumnExists($tableName, $oldColumnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf(
                'Column "%s" does not exist in table "%s".',
                $oldColumnName,
                $tableName,
            ));
        }

        if (is_array($definition)) {
            $definition = $this->_getColumnDefinition($definition);
        }

        $sql = sprintf(
            'ALTER TABLE %s CHANGE COLUMN %s %s %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($oldColumnName),
            $this->quoteIdentifier($newColumnName),
            $definition,
        );

        $result = $this->raw_query($sql);

        if ($flushData) {
            $this->showTableStatus($tableName, $schemaName);
        }
        $this->resetDdlCache($tableName, $schemaName);

        return $result;
    }

    /**
     * Modify the column definition
     *
     * @param string $tableName
     * @param string $columnName
     * @param array|string $definition
     * @param boolean $flushData
     * @param string $schemaName
     * @return $this
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function modifyColumn($tableName, $columnName, $definition, $flushData = false, $schemaName = null)
    {
        if (!$this->tableColumnExists($tableName, $columnName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Column "%s" does not exist in table "%s".', $columnName, $tableName));
        }
        if (is_array($definition)) {
            $definition = $this->_getColumnDefinition($definition);
        }

        $sql = sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($columnName),
            $definition,
        );

        $this->raw_query($sql);
        if ($flushData) {
            $this->showTableStatus($tableName, $schemaName);
        }
        $this->resetDdlCache($tableName, $schemaName);

        return $this;
    }

    /**
     * Show table status
     *
     * @param string $tableName
     * @param string $schemaName
     * @return bool
     */
    #[\Override]
    public function showTableStatus($tableName, $schemaName = null)
    {
        $fromDbName = null;
        if ($schemaName !== null) {
            $fromDbName = ' FROM ' . $this->quoteIdentifier($schemaName);
        }
        $query = sprintf('SHOW TABLE STATUS%s LIKE %s', $fromDbName, $this->quote($tableName));

        return $this->raw_fetchRow($query);
    }

    /**
     * Retrieve Create Table SQL
     *
     * @param string $tableName
     * @param string $schemaName
     * @return string
     */
    public function getCreateTable($tableName, $schemaName = null)
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_CREATE);
        if ($ddl === false) {
            $sql = 'SHOW CREATE TABLE ' . $this->quoteIdentifier($tableName);
            $ddl = $this->raw_fetchRow($sql, 'Create Table');
            $this->saveDdlCache($cacheKey, self::DDL_CREATE, $ddl);
        }

        return $ddl;
    }

    /**
     * Returns list of tables in the database
     *
     * @param string $schemaName OPTIONAL schema name to list tables from
     * @return array
     */
    #[\Override]
    public function listTables($schemaName = null)
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();

        if ($schemaName !== null) {
            // Switch to specified schema
            $originalDb = $this->_connection->getDatabase();
            try {
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($schemaName));
                $tables = $schemaManager->listTableNames();
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($originalDb));
            } catch (\Exception $e) {
                // Restore original database even on error
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($originalDb));
                throw $e;
            }
        } else {
            $tables = $schemaManager->listTableNames();
        }

        return $tables;
    }

    /**
     * Retrieve the foreign keys descriptions for a table.
     *
     * The return value is an associative array keyed by the UPPERCASE foreign key,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * FK_NAME          => string; original foreign key name
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * REF_SCHEMA_NAME  => string; name of reference database or schema
     * REF_TABLE_NAME   => string; reference table name
     * REF_COLUMN_NAME  => string; reference column name
     * ON_DELETE        => string; action type on delete row
     * ON_UPDATE        => string; action type on update row
     *
     * @param string $tableName
     * @param string $schemaName
     * @return array
     */
    #[\Override]
    public function getForeignKeys($tableName, $schemaName = null)
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_FOREIGN_KEY);
        if ($ddl === false) {
            $ddl = [];
            $createSql = $this->getCreateTable($tableName, $schemaName);

            // collect CONSTRAINT
            $regExp  = '#,\s+CONSTRAINT `([^`]*)` FOREIGN KEY \(`([^`]*)`\) '
                . 'REFERENCES (`[^`]*\.)?`([^`]*)` \(`([^`]*)`\)'
                . '( ON DELETE (RESTRICT|CASCADE|SET NULL|NO ACTION))?'
                . '( ON UPDATE (RESTRICT|CASCADE|SET NULL|NO ACTION))?#';
            $matches = [];
            preg_match_all($regExp, $createSql, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $ddl[strtoupper($match[1])] = [
                    'FK_NAME'           => $match[1],
                    'SCHEMA_NAME'       => $schemaName,
                    'TABLE_NAME'        => $tableName,
                    'COLUMN_NAME'       => $match[2],
                    'REF_SHEMA_NAME'    => strlen($match[3]) ? $match[3] : $schemaName,
                    'REF_TABLE_NAME'    => $match[4],
                    'REF_COLUMN_NAME'   => $match[5],
                    'ON_DELETE'         => isset($match[6]) ? $match[7] : '',
                    'ON_UPDATE'         => isset($match[8]) ? $match[9] : '',
                ];
            }

            $this->saveDdlCache($cacheKey, self::DDL_FOREIGN_KEY, $ddl);
        }

        return $ddl;
    }

    /**
     * Retrieve the foreign keys tree for all tables
     *
     * @return array
     */
    public function getForeignKeysTree()
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
     * Modify tables, used for upgrade process
     * Change columns definitions, reset foreign keys, change tables comments and engines.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * columns => array; list of columns definitions
     * comment => string; table comment
     * engine  => string; table engine
     *
     * @return $this
     */
    #[\Override]
    public function modifyTables($tables)
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
            if (!empty($tableData['engine'])) {
                $this->changeTableEngine($table, $tableData['engine']);
            }
        }

        return $this;
    }

    /**
     * Retrieve table index information
     *
     * The return value is an associative array keyed by the UPPERCASE index key (except for primary key,
     * that is always stored under 'PRIMARY' key) as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string; name of the table
     * KEY_NAME         => string; the original index name
     * COLUMNS_LIST     => array; array of index column names
     * INDEX_TYPE       => string; lowercase, create index type
     * INDEX_METHOD     => string; index method using
     * type             => string; see INDEX_TYPE
     * fields           => array; see COLUMNS_LIST
     *
     * @param string $tableName
     * @param string $schemaName
     * @return array
     */
    #[\Override]
    public function getIndexList($tableName, $schemaName = null)
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_INDEX);
        if ($ddl === false) {
            $ddl = [];

            $sql = sprintf(
                'SHOW INDEX FROM %s',
                $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            );
            foreach ($this->fetchAll($sql) as $row) {
                $fieldKeyName   = 'Key_name';
                $fieldNonUnique = 'Non_unique';
                $fieldColumn    = 'Column_name';
                $fieldIndexType = 'Index_type';

                if (strtolower($row[$fieldKeyName]) == \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY) {
                    $indexType  = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY;
                } elseif ($row[$fieldNonUnique] == 0) {
                    $indexType  = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE;
                } elseif (strtolower($row[$fieldIndexType]) == \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT) {
                    $indexType  = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT;
                } else {
                    $indexType  = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX;
                }

                $upperKeyName = strtoupper($row[$fieldKeyName]);
                if (isset($ddl[$upperKeyName])) {
                    $ddl[$upperKeyName]['fields'][] = $row[$fieldColumn]; // for compatible
                    $ddl[$upperKeyName]['COLUMNS_LIST'][] = $row[$fieldColumn];
                } else {
                    $ddl[$upperKeyName] = [
                        'SCHEMA_NAME'   => $schemaName,
                        'TABLE_NAME'    => $tableName,
                        'KEY_NAME'      => $row[$fieldKeyName],
                        'COLUMNS_LIST'  => [$row[$fieldColumn]],
                        'INDEX_TYPE'    => $indexType,
                        'INDEX_METHOD'  => $row[$fieldIndexType],
                        'type'          => strtolower($indexType), // for compatibility
                        'fields'        => [$row[$fieldColumn]], // for compatibility
                    ];
                }
            }
            $this->saveDdlCache($cacheKey, self::DDL_INDEX, $ddl);
        }

        return $ddl;
    }

    /**
     * Creates and returns a new \Maho\Db\Select object for this adapter.
     *
     * @return \Maho\Db\Select
     */
    #[\Override]
    public function select()
    {
        return new \Maho\Db\Select($this);
    }

    /**
     * Start debug timer
     *
     * @return $this
     */
    protected function _debugTimer()
    {
        if ($this->_debug) {
            $this->_debugTimer = microtime(true);
        }

        return $this;
    }

    /**
     * Logging debug information
     *
     * @param int $type
     * @param string $sql
     * @param array $bind
     * @param \Maho\Db\Statement\Pdo\Mysql $result
     * @return $this
     */
    protected function _debugStat($type, $sql, $bind = [], $result = null)
    {
        if (!$this->_debug) {
            return $this;
        }

        $code = '## ' . getmypid() . ' ## ';
        $nl   = "\n";
        $time = sprintf('%.4f', microtime(true) - $this->_debugTimer);

        if (!$this->_logAllQueries && $time < $this->_logQueryTime) {
            return $this;
        }
        switch ($type) {
            case self::DEBUG_CONNECT:
                $code .= 'CONNECT' . $nl;
                break;
            case self::DEBUG_TRANSACTION:
                $code .= 'TRANSACTION ' . $sql . $nl;
                break;
            case self::DEBUG_QUERY:
                $code .= 'QUERY' . $nl;
                $code .= 'SQL: ' . $sql . $nl;
                if ($bind) {
                    $code .= 'BIND: ' . var_export($bind, true) . $nl;
                }
                if ($result instanceof \Maho\Db\Statement\Pdo\Mysql) {
                    $code .= 'AFF: ' . $result->rowCount() . $nl;
                }
                break;
        }
        $code .= 'TIME: ' . $time . $nl;
        $code .= $nl;
        $this->_debugWriteToFile($code);

        return $this;
    }

    /**
     * Write exception and throw
     *
     * @throws \Exception
     */
    protected function _debugException(\Exception $e): never
    {
        if (!$this->_debug) {
            throw $e;
        }

        $nl   = "\n";
        $code = 'EXCEPTION ' . $nl . $e . $nl . $nl;
        $this->_debugWriteToFile($code);

        throw $e;
    }

    /**
     * Debug write to file process
     *
     * @param string $str
     */
    protected function _debugWriteToFile($str): void
    {
        $str = '## ' . date(self::TIMESTAMP_FORMAT) . "\r\n" . $str;
        if (!$this->_debugIoAdapter) {
            $this->_debugIoAdapter = new \Varien_Io_File();
            $dir = \Mage::getBaseDir() . DS . $this->_debugIoAdapter->dirname($this->_debugFile);
            $this->_debugIoAdapter->checkAndCreateFolder($dir);
            $this->_debugIoAdapter->open(['path' => $dir]);
            $this->_debugFile = basename($this->_debugFile);
        }

        $this->_debugIoAdapter->streamOpen($this->_debugFile, 'a');
        $this->_debugIoAdapter->streamLock();
        $this->_debugIoAdapter->streamWrite($str);
        $this->_debugIoAdapter->streamUnlock();
        $this->_debugIoAdapter->streamClose();
    }

    /**
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quote
     * and then returned as a comma-separated string.
     *
     * @param \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float $value OPTIONAL A single value to quote into the condition.
     * @param null|string|int $type  OPTIONAL The type of the given value e.g. Zend_Db::INT_TYPE, "INT"
     * @return string An SQL-safe quoted value (or string of separated values).
     */
    #[\Override]
    public function quote($value, $type = null)
    {
        $this->_connect();

        // Handle null values
        if ($value === null) {
            return 'NULL';
        }

        // Handle \Maho\Db\Expr (raw SQL expressions)
        if ($value instanceof \Maho\Db\Expr) {
            return $value->__toString();
        }

        // Handle arrays - quote each element and return as comma-separated string
        if (is_array($value)) {
            $quoted = [];
            foreach ($value as $v) {
                $quoted[] = $this->quote($v, $type);
            }
            return implode(', ', $quoted);
        }

        // Handle numeric types
        if ($type !== null
            && array_key_exists($type = strtoupper($type), $this->_numericDataTypes)
            && $this->_numericDataTypes[$type] == self::FLOAT_TYPE
        ) {
            $value = $this->_convertFloat($value);
            $quoteValue = sprintf('%F', $value);
            return $quoteValue;
        } elseif (is_float($value)) {
            return $this->_quote($value);
        } elseif (is_int($value)) {
            return (string) $value;
        }

        // Cast to string for Doctrine DBAL compatibility (it only accepts strings)
        // This handles booleans, objects with __toString(), and other scalar types
        return $this->_connection->quote((string) $value);
    }

    /**
     * Quote a raw string.
     *
     * @param string|float $value   Raw string
     * @return string|float         Quoted string
     */
    protected function _quote($value)
    {
        if (is_float($value)) {
            $value = $this->_convertFloat($value);
            return $value;
        }
        // Fix for null-byte injection
        if (is_string($value)) {
            $value = addcslashes($value, "\000\032");
        }
        return $this->_connection->quote($value);
    }

    /**
     * Quotes a value and places into a piece of text at a placeholder.
     *
     * Method revrited for handle empty arrays in value param
     *
     * @param string  $text  The text with a placeholder.
     * @param \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float $value OPTIONAL A single value to quote into the condition.
     * @param null|string|int $type  OPTIONAL The type of the given value e.g. Zend_Db::INT_TYPE, "INT"
     * @param integer $count OPTIONAL count of placeholders to replace
     * @return string An SQL-safe quoted value placed into the original text.
     */
    #[\Override]
    public function quoteInto($text, $value, $type = null, $count = null)
    {
        if (is_array($value) && empty($value)) {
            $value = new \Maho\Db\Expr('NULL');
        }

        if ($count === null) {
            return str_replace('?', (string) $this->quote($value, $type), $text, $count);
        } else {
            while ($count > 0) {
                $text = substr_replace($text, (string) $this->quote($value, $type), strpos($text, '?'), 1);
                --$count;
            }
            return $text;
        }
    }

    /**
     * Retrieve ddl cache name
     *
     * @param string $tableName
     * @param string $schemaName
     */
    protected function _getTableName($tableName, $schemaName = null): string
    {
        return ($schemaName ? $schemaName . '.' : '') . $tableName;
    }

    /**
     * Retrieve Id for cache
     *
     * @param string $tableKey
     * @param int $ddlType
     * @return string
     */
    protected function _getCacheId($tableKey, $ddlType)
    {
        return sprintf('%s_%s_%s', self::DDL_CACHE_PREFIX, $tableKey, $ddlType);
    }

    /**
     * Load DDL data from cache
     * Return false if cache does not exists
     *
     * @param string $tableCacheKey the table cache key
     * @param int $ddlType          the DDL constant
     * @return string|array|int|false
     */
    #[\Override]
    public function loadDdlCache($tableCacheKey, $ddlType)
    {
        if (!$this->_isDdlCacheAllowed) {
            return false;
        }
        if (isset($this->_ddlCache[$ddlType][$tableCacheKey])) {
            return $this->_ddlCache[$ddlType][$tableCacheKey];
        }

        if ($this->_cacheAdapter) {
            $cacheId = $this->_getCacheId($tableCacheKey, $ddlType);
            return $this->_cacheAdapter->load($cacheId);
        }

        return false;
    }

    /**
     * Save DDL data into cache
     *
     * @param string $tableCacheKey
     * @param int $ddlType
     * @return $this
     */
    #[\Override]
    public function saveDdlCache($tableCacheKey, $ddlType, $data)
    {
        if (!$this->_isDdlCacheAllowed) {
            return $this;
        }
        $this->_ddlCache[$ddlType][$tableCacheKey] = $data;

        if ($this->_cacheAdapter) {
            $cacheId = $this->_getCacheId($tableCacheKey, $ddlType);
            $this->_cacheAdapter->save($data, $cacheId, [self::DDL_CACHE_TAG]);
        }

        return $this;
    }

    /**
     * Reset cached DDL data from cache
     * if table name is null - reset all cached DDL data
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     * @return $this
     */
    #[\Override]
    public function resetDdlCache($tableName = null, $schemaName = null)
    {
        if (!$this->_isDdlCacheAllowed) {
            return $this;
        }
        if ($tableName === null) {
            $this->_ddlCache = [];
            if ($this->_cacheAdapter) {
                $this->_cacheAdapter->clean([self::DDL_CACHE_TAG]);
            }
        } else {
            $cacheKey = $this->_getTableName($tableName, $schemaName);

            $ddlTypes = [self::DDL_DESCRIBE, self::DDL_CREATE, self::DDL_INDEX, self::DDL_FOREIGN_KEY];
            foreach ($ddlTypes as $ddlType) {
                unset($this->_ddlCache[$ddlType][$cacheKey]);
            }

            if ($this->_cacheAdapter) {
                foreach ($ddlTypes as $ddlType) {
                    $cacheId = $this->_getCacheId($cacheKey, $ddlType);
                    $this->_cacheAdapter->remove($cacheId);
                }
            }
        }

        return $this;
    }

    /**
     * Disallow DDL caching
     * @return $this
     */
    #[\Override]
    public function disallowDdlCache()
    {
        $this->_isDdlCacheAllowed = false;
        return $this;
    }

    /**
     * Allow DDL caching
     * @return $this
     */
    #[\Override]
    public function allowDdlCache()
    {
        $this->_isDdlCacheAllowed = true;
        return $this;
    }

    /**
     * Decorate a table info by detecting and parsing the binary/varbinary fields
     *
     */
    public function decorateTableInfo(array $tableColumnInfo): array
    {
        $matches = [];
        if (preg_match('/^((?:var)?binary)\((\d+)\)/', $tableColumnInfo['DATA_TYPE'], $matches)) {
            [$fieldFullDescription, $fieldType, $fieldLength] = $matches;
            $tableColumnInfo['DATA_TYPE'] = $fieldType;
            $tableColumnInfo['LENGTH'] = $fieldLength;
        }
        return $tableColumnInfo;
    }

    /**
     * Returns the column descriptions for a table.
     *
     * The return value is an associative array keyed by the column name,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * COLUMN_POSITION  => number; ordinal position of column in table
     * DATA_TYPE        => string; SQL datatype name of column
     * DEFAULT          => string; default expression of column, null if none
     * NULLABLE         => boolean; true if column can have nulls
     * LENGTH           => number; length of CHAR/VARCHAR
     * SCALE            => number; scale of NUMERIC/DECIMAL
     * PRECISION        => number; precision of NUMERIC/DECIMAL
     * UNSIGNED         => boolean; unsigned property of an integer type
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     * @return array
     */
    #[\Override]
    public function describeTable($tableName, $schemaName = null)
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_DESCRIBE);
        if ($ddl === false) {
            $this->_connect();

            // Get the full table name with schema if provided
            $fullTableName = $this->_getTableName($tableName, $schemaName);

            // Use Doctrine DBAL SchemaManager for table introspection
            $schemaManager = $this->_connection->createSchemaManager();
            $table = $schemaManager->introspectTable($fullTableName);
            $platform = $this->_connection->getDatabasePlatform();

            // Get primary key information
            $primaryKey = [];
            $primaryKeyPositions = [];
            $pkConstraint = $table->getPrimaryKeyConstraint();
            if ($pkConstraint) {
                $pkColumns = $pkConstraint->getColumnNames();
                foreach ($pkColumns as $index => $columnNameObj) {
                    $columnName = $columnNameObj->toString();
                    $primaryKey[] = $columnName;
                    $primaryKeyPositions[$columnName] = $index + 1;
                }
            }

            $ddl = [];
            $position = 1;

            foreach ($table->getColumns() as $column) {
                /** @phpstan-ignore method.internalClass */
                $columnName = $column->getName();

                // Get the SQL declaration and parse it to extract MySQL type
                $sqlDeclaration = $column->getType()->getSQLDeclaration($column->toArray(), $platform);
                $typeInfo = $this->_parseMysqlType($sqlDeclaration);

                // Determine if column is in primary key
                $isPrimary = in_array($columnName, $primaryKey);
                $primaryPosition = $isPrimary ? $primaryKeyPositions[$columnName] : null;

                // Build the column description array
                $ddl[$columnName] = [
                    'SCHEMA_NAME' => $schemaName,
                    'TABLE_NAME' => $tableName,
                    'COLUMN_NAME' => $columnName,
                    'COLUMN_POSITION' => $position++,
                    'DATA_TYPE' => $typeInfo['type'],
                    'DEFAULT' => $column->getDefault(),
                    'NULLABLE' => !$column->getNotnull(),
                    'LENGTH' => $typeInfo['length'],
                    'SCALE' => $typeInfo['scale'],
                    'PRECISION' => $typeInfo['precision'],
                    'UNSIGNED' => $typeInfo['unsigned'],
                    'PRIMARY' => $isPrimary,
                    'PRIMARY_POSITION' => $primaryPosition,
                    'IDENTITY' => $column->getAutoincrement(),
                ];
            }

            // Decorate each column with additional info
            $ddl = array_map(
                [
                    $this,
                    'decorateTableInfo',
                ],
                $ddl,
            );

            /**
             * Remove bug in some MySQL versions, when int-column without default value is described as:
             * having default empty string value
             */
            $affected = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'integer'];
            foreach ($ddl as $key => $columnData) {
                if (($columnData['DEFAULT'] === '') && (in_array($columnData['DATA_TYPE'], $affected))) {
                    $ddl[$key]['DEFAULT'] = null;
                }
            }
            $this->saveDdlCache($cacheKey, self::DDL_DESCRIBE, $ddl);
        }

        return $ddl;
    }

    /**
     * Parse MySQL type from SQL declaration
     *
     * Extracts type name, length, precision, scale, and unsigned flag from SQL type declaration.
     * Examples:
     *   "INT UNSIGNED AUTO_INCREMENT" → type: int, unsigned: true
     *   "VARCHAR(255)" → type: varchar, length: 255
     *   "NUMERIC(12, 4)" → type: decimal, precision: 12, scale: 4
     *   "MEDIUMTEXT" → type: mediumtext
     *
     * @param string $sqlDeclaration The SQL type declaration from getSQLDeclaration()
     * @return array{type: string, length: int|null, precision: int|null, scale: int|null, unsigned: bool}
     */
    protected function _parseMysqlType(string $sqlDeclaration): array
    {
        $result = [
            'type' => null,
            'length' => null,
            'precision' => null,
            'scale' => null,
            'unsigned' => false,
        ];

        // Check for UNSIGNED flag
        $result['unsigned'] = stripos($sqlDeclaration, 'UNSIGNED') !== false;

        // Extract base type and parameters
        // Pattern matches: TYPE or TYPE(params) or TYPE(params) UNSIGNED etc.
        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/i', $sqlDeclaration, $matches)) {
            $result['type'] = strtolower($matches[1]);
            $params = $matches[2] ?? null;

            if ($params !== null) {
                if (str_contains($params, ',')) {
                    // DECIMAL/NUMERIC type - has precision and scale
                    $parts = array_map('trim', explode(',', $params));
                    $result['precision'] = (int) $parts[0];
                    $result['scale'] = (int) $parts[1];
                } else {
                    // Regular length parameter
                    $result['length'] = (int) $params;
                }
            }
        }

        // NUMERIC is stored as DECIMAL in MySQL
        if ($result['type'] === 'numeric') {
            $result['type'] = 'decimal';
        }

        return $result;
    }

    /**
     * Format described column to definition, ready to be added to ddl table.
     * Return array with keys: name, type, length, options, comment
     *
     * @param  array $columnData
     * @return array
     */
    public function getColumnCreateByDescribe($columnData)
    {
        $type = $this->_getColumnTypeByDdl($columnData);
        $options = [];

        if ($columnData['IDENTITY'] === true) {
            $options['identity'] = true;
        }
        if ($columnData['UNSIGNED'] === true) {
            $options['unsigned'] = true;
        }
        if ($columnData['NULLABLE'] === false
            && !($type == \Maho\Db\Ddl\Table::TYPE_TEXT && isset($columnData['DEFAULT']) && strlen($columnData['DEFAULT']) != 0)
        ) {
            $options['nullable'] = false;
        }
        if ($columnData['PRIMARY'] === true) {
            $options['primary'] = true;
        }
        if (!is_null($columnData['DEFAULT'])
            && $type != \Maho\Db\Ddl\Table::TYPE_TEXT
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

        $result = [
            'name'      => $columnData['COLUMN_NAME'],
            'type'      => $type,
            'length'    => $columnData['LENGTH'],
            'options'   => $options,
            'comment'   => $comment,
        ];

        return $result;
    }

    /**
     * Create \Maho\Db\Ddl\Table object by data from describe table
     *
     * @param $tableName
     * @param $newTableName
     * @return \Maho\Db\Ddl\Table
     */
    #[\Override]
    public function createTableByDdl($tableName, $newTableName)
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
            /**
             * Do not create primary index - it is created with identity column.
             * For reliability check both name and type, because these values can start to differ in future.
             */
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

        // Set additional options
        $tableData = $this->showTableStatus($tableName);
        if ($tableData !== false && isset($tableData['Engine'])) {
            $table->setOption('type', $tableData['Engine']);
        }

        return $table;
    }

    /**
     * Modify the column definition by data from describe table
     *
     * @param string $tableName
     * @param string $columnName
     * @param array $definition
     * @param boolean $flushData
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function modifyColumnByDdl($tableName, $columnName, $definition, $flushData = false, $schemaName = null)
    {
        $definition = array_change_key_case($definition, CASE_UPPER);
        $definition['COLUMN_TYPE'] = $this->_getColumnTypeByDdl($definition);
        if (array_key_exists('DEFAULT', $definition) && is_null($definition['DEFAULT'])) {
            unset($definition['DEFAULT']);
        }

        return $this->modifyColumn($tableName, $columnName, $definition, $flushData, $schemaName);
    }

    /**
     * Retrieve column data type by data from describe table
     *
     * @param array $column
     * @return string|void
     */
    protected function _getColumnTypeByDdl($column)
    {
        $dataType = strtolower($column['DATA_TYPE'] ?? '');
        switch ($dataType) {
            case 'bool':
            case 'boolean':
                return \Maho\Db\Ddl\Table::TYPE_BOOLEAN;
            case 'tinytext':
            case 'char':
            case 'varchar':
            case 'text':
            case 'string':
            case 'mediumtext':
            case 'longtext':
                return \Maho\Db\Ddl\Table::TYPE_TEXT;
            case 'blob':
            case 'binary':
            case 'mediumblob':
            case 'longblob':
                return \Maho\Db\Ddl\Table::TYPE_BLOB;
            case 'tinyint':
            case 'tinyinteger':
            case 'tinyint unsigned':
            case 'smallint':
            case 'smallinteger':
            case 'smallint unsigned':
                return \Maho\Db\Ddl\Table::TYPE_SMALLINT;
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'int unsigned':
                return \Maho\Db\Ddl\Table::TYPE_INTEGER;
            case 'bigint':
            case 'biginteger':
            case 'bigint unsigned':
                return \Maho\Db\Ddl\Table::TYPE_BIGINT;
            case 'datetime':
                return \Maho\Db\Ddl\Table::TYPE_DATETIME;
            case 'timestamp':
                return \Maho\Db\Ddl\Table::TYPE_TIMESTAMP;
            case 'date':
                return \Maho\Db\Ddl\Table::TYPE_DATE;
            case 'float':
                return \Maho\Db\Ddl\Table::TYPE_FLOAT;
            case 'decimal':
            case 'numeric':
                return \Maho\Db\Ddl\Table::TYPE_DECIMAL;
            case 'varbinary':
                return \Maho\Db\Ddl\Table::TYPE_VARBINARY;
            default:
                // Log unknown type for debugging
                \Mage::log("Unknown column type in _getColumnTypeByDdl: {$dataType}. Column data: " . print_r($column, true), \Mage::LOG_WARNING);
                // Default to TEXT for unknown types to avoid fatal errors
                return \Maho\Db\Ddl\Table::TYPE_TEXT;
        }
    }

    /**
     * Change table storage engine
     *
     * @param string $tableName
     * @param string $engine
     * @param string $schemaName
     * @return mixed
     */
    public function changeTableEngine($tableName, $engine, $schemaName = null)
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql   = sprintf('ALTER TABLE %s ENGINE=%s', $table, $engine);

        return $this->raw_query($sql);
    }

    /**
     * Change table comment
     *
     * @param string $tableName
     * @param string $comment
     * @param string $schemaName
     * @return mixed
     */
    public function changeTableComment($tableName, $comment, $schemaName = null)
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql   = sprintf("ALTER TABLE %s COMMENT='%s'", $table, $comment);

        return $this->raw_query($sql);
    }

    /**
     * Change table auto increment value
     *
     * @param string $tableName
     * @param string $increment
     * @param null|string $schemaName
     * @return \Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function changeTableAutoIncrement($tableName, $increment, $schemaName = null)
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql = sprintf('ALTER TABLE %s AUTO_INCREMENT=%d', $table, $increment);
        return $this->raw_query($sql);
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insert($table, array $bind)
    {
        // Extract and quote col names from the array keys
        $cols = [];
        $vals = [];
        foreach (array_keys($bind) as $col) {
            $cols[] = $this->quoteIdentifier($col);
            $vals[] = '?';
        }

        // Build the statement
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES(%s)',
            $this->quoteIdentifier($table),
            implode(', ', $cols),
            implode(', ', $vals),
        );

        // Execute the statement and return the number of affected rows
        $stmt = $this->query($sql, array_values($bind));
        return $stmt->rowCount();
    }

    /**
     * Inserts a table row with specified data
     * Special for Zero values to identity column
     *
     * @param string $table
     * @return int The number of affected rows.
     */
    #[\Override]
    public function insertForce($table, array $bind)
    {
        $this->raw_query("SET @OLD_INSERT_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        $result = $this->insert($table, $bind);
        $this->raw_query("SET SQL_MODE=IFNULL(@OLD_INSERT_SQL_MODE,'')");

        return $result;
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param mixed $table The table to insert data into.
     * @param array $data Column-value pairs or array of column-value pairs.
     * @param array $fields update fields pairs or values
     * @return int The number of affected rows.
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertOnDuplicate($table, array $data, array $fields = [])
    {
        // extract and quote col names from the array keys
        $row    = reset($data); // get first element from data array
        $bind   = []; // SQL bind array
        $values = [];

        if (is_array($row)) { // Array of column-value pairs
            $cols = array_keys($row);
            foreach ($data as $row) {
                if (array_diff($cols, array_keys($row))) {
                    throw new \Maho\Db\Exception('Invalid data for insert');
                }
                $values[] = $this->_prepareInsertData($row, $bind);
            }
            unset($row);
        } else { // Column-value pairs
            $cols     = array_keys($data);
            $values[] = $this->_prepareInsertData($data, $bind);
        }

        $updateFields = [];
        if (empty($fields)) {
            $fields = $cols;
        }

        // quote column names
        //        $cols = array_map(array($this, 'quoteIdentifier'), $cols);

        // prepare ON DUPLICATE KEY conditions
        foreach ($fields as $k => $v) {
            $field = $value = null;
            if (!is_numeric($k)) {
                $field = $this->quoteIdentifier($k);
                if ($v instanceof \Maho\Db\Expr) {
                    $value = $v->__toString();
                } elseif (is_string($v)) {
                    $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                } elseif (is_numeric($v)) {
                    $value = $this->quoteInto('?', $v);
                }
            } elseif (is_string($v)) {
                $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                $field = $this->quoteIdentifier($v);
            }

            if ($field && $value) {
                $updateFields[] = sprintf('%s = %s', $field, $value);
            }
        }

        $insertSql = $this->_getInsertSqlQuery($table, $cols, $values);
        if ($updateFields) {
            $insertSql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateFields);
        }
        // execute the statement and return the number of affected rows
        $stmt   = $this->query($insertSql, array_values($bind));
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * Inserts a table multiply rows with specified data.
     *
     * @param mixed $table The table to insert data into.
     * @param array $data Column-value pairs or array of Column-value pairs.
     * @return int The number of affected rows.
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertMultiple($table, array $data)
    {
        $row = reset($data);
        // support insert syntaxes
        if (!is_array($row)) {
            return $this->insert($table, $data);
        }

        // validate data array
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
     * @param   string $table
     * @return  int
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertArray($table, array $columns, array $data)
    {
        $values       = [];
        $bind         = [];
        $columnsCount = count($columns);
        foreach ($data as $row) {
            if ($columnsCount != count($row)) {
                throw new \Maho\Db\Exception('Invalid data for insert');
            }
            $values[] = $this->_prepareInsertData($row, $bind);
        }

        $insertQuery = $this->_getInsertSqlQuery($table, $columns, $values);

        // execute the statement and return the number of affected rows
        $stmt   = $this->query($insertQuery, $bind);
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     * @throws \RuntimeException
     */
    #[\Override]
    public function insertIgnore($table, array $bind)
    {
        // extract and quote col names from the array keys
        $cols = [];
        $vals = [];
        $i = 0;
        foreach ($bind as $col => $val) {
            $cols[] = $this->quoteIdentifier($col, true);
            if ($val instanceof \Maho\Db\Expr) {
                $vals[] = $val->__toString();
                unset($bind[$col]);
            } else {
                // Use positional parameters (?)
                $vals[] = '?';
            }
        }

        // build the statement
        $sql = 'INSERT IGNORE INTO '
            . $this->quoteIdentifier($table, true)
            . ' (' . implode(', ', $cols) . ') '
            . 'VALUES (' . implode(', ', $vals) . ')';

        // execute the statement and return the number of affected rows
        // Use positional parameters (array values)
        $bind = array_values($bind);
        $stmt = $this->query($sql, $bind);
        $result = $stmt->rowCount();
        return $result;
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @param string $tableName OPTIONAL name of the table
     * @param string $primaryKey OPTIONAL name of the primary key column
     * @return string
     */
    #[\Override]
    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        $this->_connect();
        return $this->_connection->lastInsertId();
    }

    /**
     * Batched insert of specified select
     *
     * @param string $table
     * @param bool $mode
     * @param int $step
     * @return int
     */
    public function insertBatchFromSelect(
        \Maho\Db\Select $select,
        $table,
        array $fields = [],
        $mode = false,
        $step = 10000,
    ) {
        $limitOffset = 0;
        $totalAffectedRows = 0;

        do {
            $select->limit($step, $limitOffset);
            $result = $this->query(
                $this->insertFromSelect($select, $table, $fields, $mode),
            );

            $affectedRows = $result->rowCount();
            $totalAffectedRows += $affectedRows;
            $limitOffset += $step;
        } while ($affectedRows > 0);

        return $totalAffectedRows;
    }

    /**
     * Retrieve bunch of queries for specified select split by specified step
     *
     * @param string $entityIdField
     * @param int $step
     * @return array
     */
    public function splitSelect(\Maho\Db\Select $select, $entityIdField = '*', $step = 10000)
    {
        $countSelect = clone $select;

        $countSelect->reset(\Maho\Db\Select::COLUMNS);
        $countSelect->reset(\Maho\Db\Select::LIMIT_COUNT);
        $countSelect->reset(\Maho\Db\Select::LIMIT_OFFSET);
        $countSelect->columns('COUNT(' . $entityIdField . ')');

        $row = $this->fetchRow($countSelect);
        $totalRows = array_shift($row);

        $bunches = [];
        for ($i = 0; $i <= $totalRows; $i += $step) {
            $bunchSelect = clone $select;
            $bunches[] = $bunchSelect->limit($step, $i);
        }

        return $bunches;
    }

    #[\Override]
    public function setCacheAdapter(\Mage_Core_Model_Cache $adapter): self
    {
        $this->_cacheAdapter = $adapter;
        return $this;
    }

    /**
     * Return new DDL Table object
     *
     * @param string $tableName the table name
     * @param string $schemaName the database/schema name
     * @return \Maho\Db\Ddl\Table
     */
    #[\Override]
    public function newTable($tableName = null, $schemaName = null)
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
     * Create table
     *
     * @throws \Maho\Db\Exception
     * @return \Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function createTable(\Maho\Db\Ddl\Table $table)
    {
        $columns = $table->getColumns();
        foreach ($columns as $columnEntry) {
            if (empty($columnEntry['COMMENT'])) {
                throw new \Maho\Db\Exception('Cannot create table without columns comments');
            }
        }

        $sqlFragment    = array_merge(
            $this->_getColumnsDefinition($table),
            $this->_getIndexesDefinition($table),
            $this->_getForeignKeysDefinition($table),
        );
        $tableOptions   = $this->_getOptionsDefinition($table);
        $sql = sprintf(
            "CREATE TABLE %s (\n%s\n) %s",
            $this->quoteIdentifier($table->getName()),
            implode(",\n", $sqlFragment),
            implode(' ', $tableOptions),
        );

        return $this->query($sql);
    }

    /**
     * Create temporary table
     *
     * @throws \Maho\Db\Exception
     * @return \Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function createTemporaryTable(\Maho\Db\Ddl\Table $table)
    {
        $sqlFragment    = array_merge(
            $this->_getColumnsDefinition($table),
            $this->_getIndexesDefinition($table),
            $this->_getForeignKeysDefinition($table),
        );
        $tableOptions   = $this->_getOptionsDefinition($table);
        $sql = sprintf(
            "CREATE TEMPORARY TABLE %s (\n%s\n) %s",
            $this->quoteIdentifier($table->getName()),
            implode(",\n", $sqlFragment),
            implode(' ', $tableOptions),
        );

        return $this->query($sql);
    }

    /**
     * Retrieve columns and primary keys definition array for create table
     *
     * @return array
     * @throws \Maho\Db\Exception
     */
    protected function _getColumnsDefinition(\Maho\Db\Ddl\Table $table)
    {
        $definition = [];
        $primary    = [];
        $columns    = $table->getColumns();
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
            $primary      = array_map([$this, 'quoteIdentifier'], array_keys($primary));
            $definition[] = sprintf('  PRIMARY KEY (%s)', implode(', ', $primary));
        }

        return $definition;
    }

    /**
     * Retrieve table indexes definition array for create table
     *
     * @return array
     */
    protected function _getIndexesDefinition(\Maho\Db\Ddl\Table $table)
    {
        $definition = [];
        $indexes    = $table->getIndexes();
        if (!empty($indexes)) {
            foreach ($indexes as $indexData) {
                if (!empty($indexData['TYPE'])) {
                    switch ($indexData['TYPE']) {
                        case 'primary':
                            $indexType = 'PRIMARY KEY';
                            unset($indexData['INDEX_NAME']);
                            break;
                        default:
                            $indexType = strtoupper($indexData['TYPE']);
                            break;
                    }
                } else {
                    $indexType = 'KEY';
                }

                $columns = [];
                foreach ($indexData['COLUMNS'] as $columnData) {
                    $column = $this->quoteIdentifier($columnData['NAME']);
                    if (!empty($columnData['SIZE'])) {
                        $column .= sprintf('(%d)', $columnData['SIZE']);
                    }
                    $columns[] = $column;
                }
                $indexName = isset($indexData['INDEX_NAME']) ? $this->quoteIdentifier($indexData['INDEX_NAME']) : '';
                $definition[] = sprintf(
                    '  %s %s (%s)',
                    $indexType,
                    $indexName,
                    implode(', ', $columns),
                );
            }
        }

        return $definition;
    }

    /**
     * Retrieve table foreign keys definition array for create table
     *
     * @return array
     */
    protected function _getForeignKeysDefinition(\Maho\Db\Ddl\Table $table)
    {
        $definition = [];
        $relations  = $table->getForeignKeys();

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
     * Retrieve table options definition array for create table
     *
     * @return array
     * @throws \Maho\Db\Exception
     */
    protected function _getOptionsDefinition(\Maho\Db\Ddl\Table $table)
    {
        $definition = [];
        $comment    = $table->getComment();
        if (empty($comment)) {
            throw new \Maho\Db\Exception('Comment for table is required and must be defined');
        }
        $definition[] = $this->quoteInto('COMMENT=?', $comment);

        $tableProps = [
            'type'              => 'ENGINE=%s',
            'checksum'          => 'CHECKSUM=%d',
            'auto_increment'    => 'AUTO_INCREMENT=%d',
            'avg_row_length'    => 'AVG_ROW_LENGTH=%d',
            'max_rows'          => 'MAX_ROWS=%d',
            'min_rows'          => 'MIN_ROWS=%d',
            'delay_key_write'   => 'DELAY_KEY_WRITE=%d',
            'row_format'        => 'row_format=%s',
            'charset'           => 'charset=%s',
            'collate'           => 'COLLATE=%s',
        ];
        foreach ($tableProps as $key => $mask) {
            $v = $table->getOption($key);
            if ($v !== null) {
                $definition[] = sprintf($mask, $v);
            }
        }

        return $definition;
    }

    /**
     * Get column definition from description
     *
     * @param  array $options
     * @param  null|string $ddlType
     * @return string
     */
    public function getColumnDefinitionFromDescribe($options, $ddlType = null)
    {
        $columnInfo = $this->getColumnCreateByDescribe($options);
        foreach ($columnInfo['options'] as $key => $value) {
            $columnInfo[$key] = $value;
        }
        return $this->_getColumnDefinition($columnInfo, $ddlType);
    }

    /**
     * Retrieve column definition fragment
     *
     * @param array $options
     * @param string $ddlType Table DDL Column type constant
     * @throws \Varien_Exception
     * @return string
     * @throws \Maho\Db\Exception
     */
    protected function _getColumnDefinition($options, $ddlType = null)
    {
        // convert keys to uppercase
        $options    = array_change_key_case($options, CASE_UPPER);
        $cType      = null;
        $cUnsigned  = false;
        $cNullable  = true;
        $cDefault   = false;
        $cIdentity  = false;

        // detect and validate column type
        if ($ddlType === null) {
            $ddlType = $this->_getDdlType($options);
        }

        if (empty($ddlType) || !isset($this->_ddlColumnTypes[$ddlType])) {
            throw new \Maho\Db\Exception('Invalid column definition data');
        }

        // column size
        $cType = $this->_ddlColumnTypes[$ddlType];
        switch ($ddlType) {
            case \Maho\Db\Ddl\Table::TYPE_SMALLINT:
            case \Maho\Db\Ddl\Table::TYPE_INTEGER:
            case \Maho\Db\Ddl\Table::TYPE_BIGINT:
                if (!empty($options['UNSIGNED'])) {
                    $cUnsigned = true;
                }
                break;
            case \Maho\Db\Ddl\Table::TYPE_DECIMAL:
            case \Maho\Db\Ddl\Table::TYPE_NUMERIC:
                $precision  = 10;
                $scale      = 0;
                $match      = [];
                if (!empty($options['LENGTH']) && preg_match('#^\(?(\d+),(\d+)\)?$#', $options['LENGTH'], $match)) {
                    $precision  = $match[1];
                    $scale      = $match[2];
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
            case \Maho\Db\Ddl\Table::TYPE_BLOB:
            case \Maho\Db\Ddl\Table::TYPE_VARBINARY:
                if (empty($options['LENGTH'])) {
                    $length = \Maho\Db\Ddl\Table::DEFAULT_TEXT_SIZE;
                } else {
                    $length = $this->_parseTextSize($options['LENGTH']);
                }
                if ($length <= 255) {
                    $cType = $ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT ? 'varchar' : 'varbinary';
                    $cType = sprintf('%s(%d)', $cType, $length);
                } elseif ($length > 255 && $length <= 65536) {
                    $cType = $ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT ? 'text' : 'blob';
                } elseif ($length > 65536 && $length <= 16777216) {
                    $cType = $ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT ? 'mediumtext' : 'mediumblob';
                } else {
                    $cType = $ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT ? 'longtext' : 'longblob';
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

        /*  For cases when tables created from createTableByDdl()
         *  where default value can be quoted already.
         *  We need to avoid "double-quoting" here
         */
        if ($cDefault !== null && is_string($cDefault) && strlen($cDefault)) {
            $cDefault = str_replace("'", '', $cDefault);
        }

        // prepare default value string
        if ($ddlType == \Maho\Db\Ddl\Table::TYPE_TIMESTAMP) {
            if ($cDefault === null) {
                $cDefault = new \Maho\Db\Expr('NULL');
            } elseif ($cDefault == \Maho\Db\Ddl\Table::TIMESTAMP_INIT) {
                $cDefault = new \Maho\Db\Expr('CURRENT_TIMESTAMP');
            } elseif ($cDefault == \Maho\Db\Ddl\Table::TIMESTAMP_UPDATE) {
                $cDefault = new \Maho\Db\Expr('0 ON UPDATE CURRENT_TIMESTAMP');
            } elseif ($cDefault == \Maho\Db\Ddl\Table::TIMESTAMP_INIT_UPDATE) {
                $cDefault = new \Maho\Db\Expr('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            } elseif ($cNullable && !$cDefault) {
                $cDefault = new \Maho\Db\Expr('NULL');
            } else {
                $cDefault = new \Maho\Db\Expr('0');
            }
        } elseif (is_null($cDefault) && $cNullable) {
            $cDefault = new \Maho\Db\Expr('NULL');
        }

        if (empty($options['COMMENT'])) {
            $comment = '';
        } else {
            $comment = $options['COMMENT'];
        }

        //set column position
        $after = null;
        if (!empty($options['AFTER'])) {
            $after = $options['AFTER'];
        }

        return sprintf(
            '%s%s%s%s%s COMMENT %s %s',
            $cType,
            $cUnsigned ? ' UNSIGNED' : '',
            $cNullable ? ' NULL' : ' NOT NULL',
            $cDefault !== false ? $this->quoteInto(' default ?', $cDefault) : '',
            $cIdentity ? ' auto_increment' : '',
            $this->quote($comment),
            $after ? 'AFTER ' . $this->quoteIdentifier($after) : '',
        );
    }

    /**
     * Drop table from database
     *
     * @param string $tableName
     * @param string $schemaName
     * @return boolean
     */
    #[\Override]
    public function dropTable($tableName, $schemaName = null)
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'DROP TABLE IF EXISTS ' . $table;
        $this->query($query);

        return true;
    }

    /**
     * Drop temporary table from database
     *
     * @param string $tableName
     * @param string $schemaName
     */
    #[\Override]
    public function dropTemporaryTable($tableName, $schemaName = null): bool
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'DROP TEMPORARY TABLE IF EXISTS ' . $table;
        $this->query($query);

        return true;
    }

    /**
     * Truncate a table
     *
     * @param string $tableName
     * @param string $schemaName
     * @return $this
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function truncateTable($tableName, $schemaName = null)
    {
        if (!$this->isTableExists($tableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" is not exists', $tableName));
        }

        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'TRUNCATE TABLE ' . $table;
        $this->query($query);

        return $this;
    }

    /**
     * Check is a table exists
     *
     * @param string $tableName
     * @param string $schemaName
     * @return boolean
     */
    #[\Override]
    public function isTableExists($tableName, $schemaName = null)
    {
        $fromDbName = 'DATABASE()';
        if ($schemaName !== null) {
            $fromDbName = $this->quote($schemaName);
        }

        $sql = sprintf(
            'SELECT COUNT(1) AS tbl_exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s AND TABLE_SCHEMA = %s',
            $this->quote($tableName),
            $fromDbName,
        );
        $ddl = $this->raw_fetchRow($sql, 'tbl_exists');
        if ($ddl) {
            return true;
        }

        return false;
    }

    /**
     * Rename table
     *
     * @param string $oldTableName
     * @param string $newTableName
     * @param string $schemaName
     * @return boolean
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTable($oldTableName, $newTableName, $schemaName = null)
    {
        if (!$this->isTableExists($oldTableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" is not exists', $oldTableName));
        }
        if ($this->isTableExists($newTableName, $schemaName)) {
            throw new \Maho\Db\Exception(sprintf('Table "%s" already exists', $newTableName));
        }

        $oldTable = $this->_getTableName($oldTableName, $schemaName);
        $newTable = $this->_getTableName($newTableName, $schemaName);

        $query = sprintf('ALTER TABLE %s RENAME TO %s', $oldTable, $newTable);
        $this->query($query);

        $this->resetDdlCache($oldTableName, $schemaName);

        return true;
    }

    /**
     * Rename several tables
     *
     * @param array $tablePairs array('oldName' => 'Name1', 'newName' => 'Name2')
     *
     * @return boolean
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTablesBatch(array $tablePairs)
    {
        if (count($tablePairs) == 0) {
            throw new \Maho\Db\Exception('Please provide tables for rename');
        }

        $renamesList = [];
        $tablesList  = [];
        foreach ($tablePairs as $pair) {
            $oldTableName  = $pair['oldName'];
            $newTableName  = $pair['newName'];
            $renamesList[] = sprintf('%s TO %s', $oldTableName, $newTableName);

            $tablesList[$oldTableName] = $oldTableName;
            $tablesList[$newTableName] = $newTableName;
        }

        $query = sprintf('RENAME TABLE %s', implode(',', $renamesList));
        $this->query($query);

        foreach ($tablesList as $table) {
            $this->resetDdlCache($table);
        }

        return true;
    }

    /**
     * Add new index to table name
     *
     * @param string $tableName
     * @param string $indexName
     * @param string|array $fields  the table column name or array of ones
     * @param string $indexType     the index type
     * @param string $schemaName
     * @return \Maho\Db\Statement\Pdo\Mysql
     * @throws \Maho\Db\Exception|\Exception
     */
    #[\Override]
    public function addIndex(
        $tableName,
        $indexName,
        $fields,
        $indexType = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        $schemaName = null,
    ) {
        $columns = $this->describeTable($tableName, $schemaName);
        $keyList = $this->getIndexList($tableName, $schemaName);

        $query = sprintf('ALTER TABLE %s', $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)));
        if (isset($keyList[strtoupper($indexName)])) {
            if ($keyList[strtoupper($indexName)]['INDEX_TYPE'] == \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY) {
                $query .= ' DROP PRIMARY KEY,';
            } else {
                $query .= sprintf(' DROP INDEX %s,', $this->quoteIdentifier($indexName));
            }
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $fieldSql = [];
        foreach ($fields as $field) {
            if (!isset($columns[$field])) {
                throw new \Maho\Db\Exception(sprintf(
                    'There is no field "%s" that you are trying to create an index on "%s"',
                    $field,
                    $tableName,
                ));
            }
            $fieldSql[] = $this->quoteIdentifier($field);
        }
        $fieldSql = implode(',', $fieldSql);

        $condition = match (strtolower($indexType)) {
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY => 'PRIMARY KEY',
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE => 'UNIQUE ' . $this->quoteIdentifier($indexName),
            \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT => 'FULLTEXT ' . $this->quoteIdentifier($indexName),
            default => 'INDEX ' . $this->quoteIdentifier($indexName),
        };

        $query .= sprintf(' ADD %s (%s)', $condition, $fieldSql);
        $result = $this->raw_query($query);
        $this->resetDdlCache($tableName, $schemaName);

        return $result;
    }

    /**
     * Drop the index from table
     *
     * @param string $tableName
     * @param string $keyName
     * @param string $schemaName
     * @return bool|\Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function dropIndex($tableName, $keyName, $schemaName = null)
    {
        $indexList = $this->getIndexList($tableName, $schemaName);
        $keyName = strtoupper($keyName);
        if (!isset($indexList[$keyName])) {
            return true;
        }

        if ($keyName == 'PRIMARY') {
            $cond = 'DROP PRIMARY KEY';
        } else {
            $cond = 'DROP KEY ' . $this->quoteIdentifier($indexList[$keyName]['KEY_NAME']);
        }
        $sql = sprintf(
            'ALTER TABLE %s %s',
            $this->quoteIdentifier($this->_getTableName($tableName, $schemaName)),
            $cond,
        );

        $this->resetDdlCache($tableName, $schemaName);

        return $this->raw_query($sql);
    }

    /**
     * Add new Foreign Key to table
     * If Foreign Key with same name is exist - it will be deleted
     *
     * @param string $fkName
     * @param string $tableName
     * @param string $columnName
     * @param string $refTableName
     * @param string $refColumnName
     * @param string $onDelete
     * @param string $onUpdate
     * @param boolean $purge            trying remove invalid data
     * @param string $schemaName
     * @param string $refSchemaName
     * @return \Maho\Db\Statement\Pdo\Mysql|\Maho\Db\Statement\Pdo\Mysql
     */
    #[\Override]
    public function addForeignKey(
        $fkName,
        $tableName,
        $columnName,
        $refTableName,
        $refColumnName,
        $onDelete = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
        $onUpdate = \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE,
        $purge = false,
        $schemaName = null,
        $refSchemaName = null,
    ) {
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

        if ($onDelete !== null) {
            $query .= ' ON DELETE ' . strtoupper($onDelete);
        }
        if ($onUpdate  !== null) {
            $query .= ' ON UPDATE ' . strtoupper($onUpdate);
        }

        $result = $this->raw_query($query);
        $this->resetDdlCache($tableName);
        return $result;
    }

    /**
     * Format Date to internal database date format
     *
     * @param int|string|\DateTime $date
     * @param boolean $includeTime
     * @return \Maho\Db\Expr|string|null
     */
    #[\Override]
    public function formatDate($date, $includeTime = true, bool $asExpr = true)
    {
        if ($date === true) {
            $format = $includeTime ? \Mage_Core_Model_Locale::DATETIME_FORMAT : \Mage_Core_Model_Locale::DATE_FORMAT;
            $date = date($format);
        } elseif ($date instanceof \DateTime) {
            $format = $includeTime ? \Mage_Core_Model_Locale::DATETIME_FORMAT : \Mage_Core_Model_Locale::DATE_FORMAT;
            $date = $date->format($format);
        } elseif (empty($date)) {
            return $asExpr ? new \Maho\Db\Expr('NULL') : null;
        } else {
            if (!is_numeric($date)) {
                $date = strtotime($date);
            }
            $format = $includeTime ? \Mage_Core_Model_Locale::DATETIME_FORMAT : \Mage_Core_Model_Locale::DATE_FORMAT;
            $date = date($format, $date);
        }

        if ($date === null) {
            return $asExpr ? new \Maho\Db\Expr('NULL') : null;
        }

        // When used in prepared statements, return the plain string value without quoting
        // When used in raw SQL, wrap in \Maho\Db\Expr with quotes
        return $asExpr ? new \Maho\Db\Expr($this->quote($date)) : $date;
    }

    /**
     * Run additional environment before setup
     *
     * @return $this
     */
    #[\Override]
    public function startSetup()
    {
        $this->raw_query("SET SQL_MODE=''");
        $this->raw_query('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
        $this->raw_query("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

        return $this;
    }

    /**
     * Run additional environment after setup
     *
     * @return $this
     */
    #[\Override]
    public function endSetup()
    {
        $this->raw_query("SET SQL_MODE=IFNULL(@OLD_SQL_MODE,'')");
        $this->raw_query('SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS=0, 0, 1)');

        return $this;
    }

    /**
     * Build SQL statement for condition
     *
     * If $condition integer or string - exact value will be filtered ('eq' condition)
     *
     * If $condition is array is - one of the following structures is expected:
     * - array("from" => $fromValue, "to" => $toValue)
     * - array("eq" => $equalValue)
     * - array("neq" => $notEqualValue)
     * - array("like" => $likeValue)
     * - array("in" => array($inValues))
     * - array("nin" => array($notInValues))
     * - array("notnull" => $valueIsNotNull)
     * - array("null" => $valueIsNull)
     * - array("gt" => $greaterValue)
     * - array("lt" => $lessValue)
     * - array("gteq" => $greaterOrEqualValue)
     * - array("lteq" => $lessOrEqualValue)
     * - array("finset" => $valueInSet)
     * - array("regexp" => $regularExpression)
     * - array("seq" => $stringValue)
     * - array("sneq" => $stringValue)
     *
     * If non matched - sequential array is expected and OR conditions
     * will be built using above mentioned structure
     *
     * @param string|array $fieldName
     * @param integer|string|array $condition
     * @return string
     */
    #[\Override]
    public function prepareSqlCondition($fieldName, $condition)
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
            'finset'        => 'FIND_IN_SET(?, {{fieldName}})',
            'regexp'        => '{{fieldName}} REGEXP ?',
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
                    $from  = $this->_prepareSqlDateCondition($condition, 'from');
                    $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['from'], $from, $fieldName);
                }

                if (isset($condition['to'])) {
                    $query .= empty($query) ? '' : ' AND ';
                    $to     = $this->_prepareSqlDateCondition($condition, 'to');
                    $query = $query . $this->_prepareQuotedSqlCondition($conditionKeyMap['to'], $to, $fieldName);
                }
            } elseif (array_key_exists($key, $conditionKeyMap)) {
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
        } else {
            $query = $this->_prepareQuotedSqlCondition($conditionKeyMap['eq'], (string) $condition, $fieldName);
        }

        return $query;
    }

    /**
     * Prepare Sql condition
     *
     * @param  string $text Condition value
     * @param  mixed $value
     * @param  string $fieldName
     * @return string
     */
    protected function _prepareQuotedSqlCondition($text, $value, $fieldName)
    {
        $value = is_string($value) ? str_replace("\0", '', $value) : $value;
        $sql = $this->quoteInto($text, $value);
        return str_replace('{{fieldName}}', (string) $fieldName, $sql);
    }

    /**
     * Transforms sql condition key 'seq' / 'sneq' that is used for comparing string values to its analog:
     * - 'null' / 'notnull' for empty strings
     * - 'eq' / 'neq' for non-empty strings
     *
     * @param string $conditionKey
     * @param mixed $value
     * @return string
     */
    protected function _transformStringSqlCondition($conditionKey, $value)
    {
        $value = str_replace("\0", '', (string) $value);
        if ($value == '') {
            return ($conditionKey == 'seq') ? 'null' : 'notnull';
        } else {
            return ($conditionKey == 'seq') ? 'eq' : 'neq';
        }
    }

    /**
     * Prepare value for save in column
     * Return converted to column data type value
     *
     * @param array $column     the column describe array
     * @param mixed $value
     * @return mixed
     */
    #[\Override]
    public function prepareColumnValue(array $column, $value)
    {
        if ($value instanceof \Maho\Db\Expr) {
            return $value;
        }
        if ($value instanceof \Maho\Db\Statement\Parameter) {
            return $value;
        }

        // return original value if invalid column describe data
        if (!isset($column['DATA_TYPE'])) {
            return $value;
        }

        // return null
        if (is_null($value) && $column['NULLABLE']) {
            return null;
        }

        switch ($column['DATA_TYPE']) {
            case 'smallint':
            case 'int':
                $value = (int) $value;
                break;
            case 'bigint':
                if (!is_integer($value)) {
                    $value = sprintf('%.0f', (float) $value);
                }
                break;

            case 'decimal':
                $precision  = 10;
                $scale      = 0;
                if (isset($column['SCALE'])) {
                    $scale = $column['SCALE'];
                }
                if (isset($column['PRECISION'])) {
                    $precision = $column['PRECISION'];
                }
                $format = sprintf('%%%d.%dF', $precision - $scale, $scale);
                $value  = (float) sprintf($format, $value);
                break;

            case 'float':
                $value  = (float) sprintf('%F', $value);
                break;

            case 'date':
                $value  = $this->formatDate($value, false, false);
                break;
            case 'datetime':
            case 'timestamp':
                $value  = $this->formatDate($value, true, false);
                break;

            case 'varchar':
            case 'mediumtext':
            case 'text':
            case 'longtext':
                $value  = str_replace("\0", '', (string) $value);
                if ($column['NULLABLE'] && $value == '') {
                    $value = null;
                }
                break;

            case 'varbinary':
            case 'mediumblob':
            case 'blob':
            case 'longblob':
                // No special processing for MySQL is needed
                break;
        }

        return $value;
    }

    /**
     * Generate fragment of SQL, that check condition and return true or false value
     *
     * @param \Maho\Db\Expr|\Maho\Db\Select|string $expression
     * @param string $true  true value
     * @param string $false false value
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getCheckSql($expression, $true, $false)
    {
        if ($expression instanceof \Maho\Db\Expr || $expression instanceof \Maho\Db\Select) {
            $expression = sprintf('IF((%s), %s, %s)', $expression, $true, $false);
        } else {
            $expression = sprintf('IF(%s, %s, %s)', $expression, $true, $false);
        }

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Returns valid IFNULL expression
     *
     * @param \Maho\Db\Expr|\Maho\Db\Select|string $expression
     * @param string|int $value OPTIONAL. Applies when $expression is NULL
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getIfNullSql($expression, $value = '0')
    {
        if ($expression instanceof \Maho\Db\Expr || $expression instanceof \Maho\Db\Select) {
            $expression = sprintf('IFNULL((%s), %s)', $expression, $value);
        } else {
            $expression = sprintf('IFNULL(%s, %s)', $expression, $value);
        }

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Generate fragment of SQL, that check value against multiple condition cases
     * and return different result depends on them
     *
     * @param string $valueName Name of value to check
     * @param array $casesResults Cases and results
     * @param string $defaultValue value to use if value doesn't conform to any cases
     *
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getCaseSql($valueName, $casesResults, $defaultValue = null)
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
     * Generate fragment of SQL, that combine together (concatenate) the results from data array
     * All arguments in data must be quoted
     *
     * @param string $separator concatenate with separator
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getConcatSql(array $data, $separator = null)
    {
        $format = empty($separator) ? 'CONCAT(%s)' : "CONCAT_WS('{$separator}', %s)";
        return new \Maho\Db\Expr(sprintf($format, implode(', ', $data)));
    }

    /**
     * Generate fragment of SQL that returns length of character string
     * The string argument must be quoted
     *
     * @param string $string
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getLengthSql($string)
    {
        return new \Maho\Db\Expr(sprintf('LENGTH(%s)', $string));
    }

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the smallest
     * (minimum-valued) argument
     * All arguments in data must be quoted
     *
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getLeastSql(array $data)
    {
        return new \Maho\Db\Expr(sprintf('LEAST(%s)', implode(', ', $data)));
    }

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the largest
     * (maximum-valued) argument
     * All arguments in data must be quoted
     *
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getGreatestSql(array $data)
    {
        return new \Maho\Db\Expr(sprintf('GREATEST(%s)', implode(', ', $data)));
    }

    /**
     * Get Interval Unit SQL fragment
     *
     * @param int $interval
     * @param string $unit
     * @return string
     * @throws \Maho\Db\Exception
     */
    protected function _getIntervalUnitSql($interval, $unit)
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        return sprintf('INTERVAL %d %s', $interval, $this->_intervalUnits[$unit]);
    }

    /**
     * Add time values (intervals) to a date value
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     * @param int $interval
     * @param string $unit
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getDateAddSql($date, $interval, $unit)
    {
        $expr = sprintf('DATE_ADD(%s, %s)', $date, $this->_getIntervalUnitSql($interval, $unit));
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Subtract time values (intervals) to a date value
     *
     * @see INTERVAL_ constants for $expr
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     * @param int|string $interval
     * @param string $unit
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getDateSubSql($date, $interval, $unit)
    {
        $expr = sprintf('DATE_SUB(%s, %s)', $date, $this->_getIntervalUnitSql($interval, $unit));
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Format date as specified
     *
     * Supported format Specifier
     *
     * %H   Hour (00..23)
     * %i   Minutes, numeric (00..59)
     * %s   Seconds (00..59)
     * %d   Day of the month, numeric (00..31)
     * %m   Month, numeric (00..12)
     * %Y   Year, numeric, four digits
     *
     * @param string $date  quoted date value or non quoted SQL statement(field)
     * @param string $format
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getDateFormatSql($date, $format)
    {
        $expr = sprintf("DATE_FORMAT(%s, '%s')", $date, $format);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Extract the date part of a date or datetime expression
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getDatePartSql($date)
    {
        return new \Maho\Db\Expr(sprintf('DATE(%s)', $date));
    }

    /**
     * Prepare substring sql function
     *
     * @param \Maho\Db\Expr|string $stringExpression quoted field name or SQL statement
     * @param int|string|\Maho\Db\Expr $pos
     * @param int|string|\Maho\Db\Expr|null $len
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getSubstringSql($stringExpression, $pos, $len = null)
    {
        if (is_null($len)) {
            return new \Maho\Db\Expr(sprintf('SUBSTRING(%s, %s)', $stringExpression, $pos));
        }
        return new \Maho\Db\Expr(sprintf('SUBSTRING(%s, %s, %s)', $stringExpression, $pos, $len));
    }

    /**
     * Prepare standard deviation sql function
     *
     * @param \Maho\Db\Expr|string $expressionField   quoted field name or SQL statement
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getStandardDeviationSql($expressionField)
    {
        return new \Maho\Db\Expr(sprintf('STDDEV_SAMP(%s)', $expressionField));
    }

    /**
     * Extract part of a date
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     * @param string $unit
     * @return \Maho\Db\Expr
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function getDateExtractSql($date, $unit)
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        $expr = sprintf('EXTRACT(%s FROM %s)', $this->_intervalUnits[$unit], $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Minus superfluous characters from hash.
     *
     * @param  $hash
     * @param  $prefix
     * @param  $maxCharacters
     */
    protected function _minusSuperfluous(string $hash, string $prefix, int $maxCharacters): string
    {
        $diff        = strlen($hash) + strlen($prefix) -  $maxCharacters;
        $superfluous = $diff / 2;
        $odd         = $diff % 2;
        $hash        = substr($hash, $superfluous, - ($superfluous + $odd));
        return $hash;
    }

    /**
     * Retrieve valid table name
     * Check table name length and allowed symbols
     *
     * @param string $tableName
     * @return string
     */
    #[\Override]
    public function getTableName($tableName)
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
     * Check index name length and allowed symbols
     *
     * @param string $tableName
     * @param string|array $fields  the columns list
     * @param string $indexType
     * @return string
     */
    #[\Override]
    public function getIndexName($tableName, $fields, $indexType = '')
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

        return strtoupper($hash);
    }

    /**
     * Retrieve valid foreign key name
     * Check foreign key name length and allowed symbols
     *
     * @param string $priTableName
     * @param string $priColumnName
     * @param string $refTableName
     * @param string $refColumnName
     * @return string
     */
    #[\Override]
    public function getForeignKeyName($priTableName, $priColumnName, $refTableName, $refColumnName)
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

        return strtoupper($hash);
    }

    /**
     * Stop updating indexes
     *
     * @param string $tableName
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function disableTableKeys($tableName, $schemaName = null)
    {
        $tableName = $this->_getTableName($tableName, $schemaName);
        $query     = sprintf('ALTER TABLE %s DISABLE KEYS', $this->quoteIdentifier($tableName));
        $this->query($query);

        return $this;
    }

    /**
     * Re-create missing indexes
     *
     * @param string $tableName
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function enableTableKeys($tableName, $schemaName = null)
    {
        $tableName = $this->_getTableName($tableName, $schemaName);
        $query     = sprintf('ALTER TABLE %s ENABLE KEYS', $this->quoteIdentifier($tableName));
        $this->query($query);

        return $this;
    }

    /**
     * Get insert from Select object query
     *
     * @param string $table     insert into table
     * @param bool|int $mode
     * @return string
     */
    #[\Override]
    public function insertFromSelect(\Maho\Db\Select $select, $table, array $fields = [], $mode = false)
    {
        $query = 'INSERT';
        if ($mode == self::INSERT_IGNORE) {
            $query .= ' IGNORE';
        }
        $query = sprintf('%s INTO %s', $query, $this->quoteIdentifier($table));
        if ($fields) {
            $columns = array_map([$this, 'quoteIdentifier'], $fields);
            $query = sprintf('%s (%s)', $query, implode(', ', $columns));
        }

        $query = sprintf('%s %s', $query, $select->assemble());

        if ($mode == self::INSERT_ON_DUPLICATE) {
            if (!$fields) {
                $describe = $this->describeTable($table);
                foreach ($describe as $column) {
                    if ($column['PRIMARY'] === false) {
                        $fields[] = $column['COLUMN_NAME'];
                    }
                }
            }

            $update = [];
            foreach ($fields as $k => $v) {
                $field = $value = null;
                if (!is_numeric($k)) {
                    $field = $this->quoteIdentifier($k);
                    if ($v instanceof \Maho\Db\Expr) {
                        $value = $v->__toString();
                    } elseif (is_string($v)) {
                        $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                    } elseif (is_numeric($v)) {
                        $value = $this->quoteInto('?', $v);
                    }
                } elseif (is_string($v)) {
                    $value = sprintf('VALUES(%s)', $this->quoteIdentifier($v));
                    $field = $this->quoteIdentifier($v);
                }

                if ($field && $value) {
                    $update[] = sprintf('%s = %s', $field, $value);
                }
            }
            if ($update) {
                $query = sprintf('%s ON DUPLICATE KEY UPDATE %s', $query, implode(', ', $update));
            }
        }

        return $query;
    }

    /**
     * Get insert queries in array for insert by range with step parameter
     *
     * @param string $rangeField
     * @param int $stepCount
     * @return array
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function selectsByRange($rangeField, \Maho\Db\Select $select, $stepCount = 100)
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
     * Convert date format to unix time
     *
     * @param string|\Maho\Db\Expr $date
     * @throws \Maho\Db\Exception
     * @return \Maho\Db\Expr
     */
    #[\Override]
    public function getUnixTimestamp($date)
    {
        $expr = sprintf('UNIX_TIMESTAMP(%s)', $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Convert unix time to date format
     *
     * @param int|\Maho\Db\Expr $timestamp
     * @return mixed
     */
    #[\Override]
    public function fromUnixtime($timestamp)
    {
        $expr = sprintf('FROM_UNIXTIME(%s)', $timestamp);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Updates table rows with specified data based on a WHERE clause.
     *
     * @param mixed $table The table to update.
     * @param array $bind Column-value pairs.
     * @param mixed $where UPDATE WHERE clause(s).
     * @return int The number of affected rows.
     */
    #[\Override]
    public function update($table, array $bind, $where = '')
    {
        $set = [];
        foreach (array_keys($bind) as $col) {
            $set[] = $this->quoteIdentifier($col) . ' = ?';
        }

        $where = $this->_whereExpr($where);

        // Build the UPDATE statement
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            ($where) ? " WHERE $where" : '',
        );

        // Execute the statement and return the number of affected rows
        $stmt = $this->query($sql, array_values($bind));
        return $stmt->rowCount();
    }

    /**
     * Get update table query using select object for join and update
     *
     * @param string|array $table
     * @throws \Maho\Db\Exception
     * @return string
     */
    #[\Override]
    public function updateFromSelect(\Maho\Db\Select $select, $table)
    {
        if (!is_array($table)) {
            $table = [$table => $table];
        }

        // get table name and alias
        $keys       = array_keys($table);
        $tableAlias = $keys[0];
        $tableName  = $table[$keys[0]];

        $query = sprintf('UPDATE %s', $this->quoteTableAs($tableName, $tableAlias));

        // render JOIN conditions (FROM Part)
        $joinConds  = [];
        foreach ($select->getPart(\Maho\Db\Select::FROM) as $correlationName => $joinProp) {
            if ($joinProp['joinType'] == \Maho\Db\Select::FROM) {
                $joinType = strtoupper(\Maho\Db\Select::INNER_JOIN);
            } else {
                $joinType = strtoupper($joinProp['joinType']);
            }
            $joinTable = '';
            if ($joinProp['schema'] !== null) {
                $joinTable = sprintf('%s.', $this->quoteIdentifier($joinProp['schema']));
            }
            $joinTable .= $this->quoteTableAs($joinProp['tableName'], $correlationName);

            $join = sprintf(' %s %s', $joinType, $joinTable);

            if (!empty($joinProp['joinCondition'])) {
                $join = sprintf('%s ON %s', $join, $joinProp['joinCondition']);
            }

            $joinConds[] = $join;
        }

        if ($joinConds) {
            $query = sprintf("%s\n%s", $query, implode("\n", $joinConds));
        }

        // render UPDATE SET
        $columns = [];
        foreach ($select->getPart(\Maho\Db\Select::COLUMNS) as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if (empty($alias)) {
                $alias = $column;
            }

            // Handle column value - if it's an expression, use it as-is; otherwise quote it
            if ($column instanceof \Maho\Db\Expr) {
                $columnValue = $column->__toString();
            } elseif (!empty($correlationName)) {
                $columnValue = $this->quoteIdentifier([$correlationName, $column]);
            } else {
                $columnValue = $this->quoteIdentifier($column);
            }

            // Handle alias - if it's an expression or object, can't use it as field name
            if ($alias instanceof \Maho\Db\Expr || is_object($alias)) {
                // Can't update with an expression as the field name - skip this
                continue;
            }

            $columns[] = sprintf('%s = %s', $this->quoteIdentifier([$tableAlias, $alias]), $columnValue);
        }

        if (!$columns) {
            throw new \Maho\Db\Exception('The columns for UPDATE statement are not defined');
        }

        $query = sprintf("%s\nSET %s", $query, implode(', ', $columns));

        // render WHERE - handle array structure correctly
        $wherePart = $select->getPart(\Maho\Db\Select::WHERE);
        if ($wherePart) {
            $where = [];
            foreach ($wherePart as $term) {
                if (is_array($term)) {
                    foreach ($term as $type => $cond) {
                        if (!empty($where)) {
                            $where[] = $type;
                        }
                        $where[] = $cond;
                    }
                } else {
                    if (!empty($where)) {
                        $where[] = 'AND';
                    }
                    $where[] = $term;
                }
            }
            $query = sprintf("%s\nWHERE %s", $query, implode(' ', $where));
        }

        return $query;
    }

    /**
     * Deletes table rows based on a WHERE clause.
     *
     * @param mixed $table The table to update.
     * @param mixed $where DELETE WHERE clause(s).
     * @return int The number of affected rows.
     */
    #[\Override]
    public function delete($table, $where = '')
    {
        $where = $this->_whereExpr($where);

        // Build the DELETE statement
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->quoteIdentifier($table),
            ($where) ? " WHERE $where" : '',
        );

        // Execute the statement and return the number of affected rows
        $stmt = $this->query($sql);
        return $stmt->rowCount();
    }

    /**
     * Get delete from select object query
     *
     * @param string $table the table name or alias used in select
     * @return string|int
     */
    #[\Override]
    public function deleteFromSelect(\Maho\Db\Select $select, $table)
    {
        $select = clone $select;
        $select->reset(\Maho\Db\Select::DISTINCT);
        $select->reset(\Maho\Db\Select::COLUMNS);

        // Build DELETE query: DELETE table_name FROM ... JOIN ... WHERE ...
        $query = sprintf('DELETE %s FROM', $this->quoteIdentifier($table));

        // Add FROM clause
        $fromPart = $select->getPart(\Maho\Db\Select::FROM);
        if ($fromPart) {
            $from = [];
            foreach ($fromPart as $correlationName => $tableInfo) {
                $tmp = '';

                // Add join type for all but the first table
                if (!empty($from)) {
                    $tmp .= ' ' . strtoupper($tableInfo['joinType']) . ' ';
                }

                // Add table name
                $tmp .= $this->quoteTableAs($tableInfo['tableName'], $correlationName, true);

                // Add join condition
                if (!empty($tableInfo['joinCondition']) && !empty($from)) {
                    $tmp .= ' ON ' . $tableInfo['joinCondition'];
                }

                $from[] = $tmp;
            }
            $query .= ' ' . implode('', $from);
        }

        // Add WHERE clause
        $wherePart = $select->getPart(\Maho\Db\Select::WHERE);
        if ($wherePart) {
            $where = [];
            foreach ($wherePart as $term) {
                if (is_array($term)) {
                    foreach ($term as $type => $cond) {
                        if (!empty($where)) {
                            $where[] = $type;
                        }
                        $where[] = $cond;
                    }
                } else {
                    if (!empty($where)) {
                        $where[] = 'AND';
                    }
                    $where[] = $term;
                }
            }
            $query .= ' WHERE ' . implode(' ', $where);
        }

        return $query;
    }

    /**
     * Calculate checksum for table or for group of tables
     *
     * @param array|string $tableNames array of tables names | table name
     * @param string $schemaName schema name
     * @return array
     */
    #[\Override]
    public function getTablesChecksum($tableNames, $schemaName = null)
    {
        $result     = [];
        $tableNames = is_array($tableNames) ? $tableNames : [$tableNames];

        foreach ($tableNames as $tableName) {
            $query = 'CHECKSUM TABLE ' . $this->_getTableName($tableName, $schemaName);
            $checkSumArray      = $this->fetchRow($query);
            $result[$tableName] = $checkSumArray['Checksum'];
        }

        return $result;
    }

    /**
     * Check if the database support STRAIGHT JOIN
     *
     * @return boolean
     */
    #[\Override]
    public function supportStraightJoin()
    {
        return true;
    }

    /**
     * Adds order by random to select object
     * Possible using integer field for optimization
     *
     * @param string $field
     * @return $this
     */
    #[\Override]
    public function orderRand(\Maho\Db\Select $select, $field = null)
    {
        if ($field !== null) {
            $expression = new \Maho\Db\Expr(sprintf('RAND() * %s', $this->quoteIdentifier($field)));
            $select->columns(['mage_rand' => $expression]);
            $spec = new \Maho\Db\Expr('mage_rand');
        } else {
            $spec = new \Maho\Db\Expr('RAND()');
        }
        $select->order($spec);

        return $this;
    }

    /**
     * Render SQL FOR UPDATE clause
     *
     * @param string $sql
     * @return string
     */
    #[\Override]
    public function forUpdate($sql)
    {
        return sprintf('%s FOR UPDATE', $sql);
    }

    /**
     * Prepare insert data
     *
     * @param mixed $row
     * @param array $bind
     * @return string
     */
    protected function _prepareInsertData($row, &$bind)
    {
        if (is_array($row)) {
            $line = [];
            foreach ($row as $value) {
                if ($value instanceof \Maho\Db\Expr) {
                    $line[] = $value->__toString();
                } else {
                    $line[] = '?';
                    $bind[] = $value;
                }
            }
            $line = implode(', ', $line);
        } elseif ($row instanceof \Maho\Db\Expr) {
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
     * @param string $tableName
     * @return string
     */
    protected function _getInsertSqlQuery($tableName, array $columns, array $values)
    {
        $tableName = $this->quoteIdentifier($tableName, true);
        $columns   = array_map([$this, 'quoteIdentifier'], $columns);
        $columns   = implode(',', $columns);
        $values    = implode(', ', $values);

        $insertSql = sprintf('INSERT INTO %s (%s) VALUES %s', $tableName, $columns, $values);

        return $insertSql;
    }

    /**
     * Return ddl type
     *
     * @param array $options
     * @return string
     */
    protected function _getDdlType($options)
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
     * Return DDL action
     *
     * @param string $action
     * @return string
     */
    protected function _getDdlAction($action)
    {
        return match ($action) {
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_CASCADE => \Maho\Db\Ddl\Table::ACTION_CASCADE,
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_SET_NULL => \Maho\Db\Ddl\Table::ACTION_SET_NULL,
            \Maho\Db\Adapter\AdapterInterface::FK_ACTION_RESTRICT => \Maho\Db\Ddl\Table::ACTION_RESTRICT,
            default => \Maho\Db\Ddl\Table::ACTION_NO_ACTION,
        };
    }

    /**
     * Prepare sql date condition
     *
     * @param array $condition
     * @param string $key
     * @return string
     */
    protected function _prepareSqlDateCondition($condition, $key)
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
     * Try to find installed primary key name, if not - format new one.
     *
     * @param string $tableName Table name
     * @param string $schemaName OPTIONAL
     * @return string Primary Key name
     */
    #[\Override]
    public function getPrimaryKeyName($tableName, $schemaName = null)
    {
        $indexes = $this->getIndexList($tableName, $schemaName);
        if (isset($indexes['PRIMARY'])) {
            return $indexes['PRIMARY']['KEY_NAME'];
        } else {
            return 'PK_' . strtoupper($tableName);
        }
    }

    /**
     * Parse text size
     * Returns max allowed size if value great it
     *
     * @param string|int $size
     * @return int
     */
    protected function _parseTextSize($size)
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
     * Converts fetched blob into raw binary PHP data.
     * The MySQL drivers do it nice, no processing required.
     *
     * @mixed $value
     * @return mixed
     */
    #[\Override]
    public function decodeVarbinary($value)
    {
        return $value;
    }

    /**
     * Returns date that fits into TYPE_DATETIME range and is suggested to act as default 'zero' value
     * for a column for current RDBMS.
     *
     * @return string
     */
    #[\Override]
    public function getSuggestedZeroDate()
    {
        return '0000-00-00 00:00:00';
    }

    /**
     * Drop trigger
     *
     * @param string $triggerName
     * @return \Maho\Db\Adapter\AdapterInterface
     */
    #[\Override]
    public function dropTrigger($triggerName)
    {
        $query = sprintf(
            'DROP TRIGGER IF EXISTS %s',
            $this->_getTableName($triggerName),
        );
        $this->query($query);
        return $this;
    }

    /**
     * Create new table from provided select statement
     *
     * @param string $tableName
     * @param bool $temporary
     */
    #[\Override]
    public function createTableFromSelect($tableName, \Maho\Db\Select $select, $temporary = false): void
    {
        $query = sprintf(
            'CREATE' . ($temporary ? ' TEMPORARY' : '') . ' TABLE `%s` AS (%s)',
            $this->_getTableName($tableName),
            (string) $select,
        );
        $this->query($query);
    }

    /**
     * Convert float values that are not supported by MySQL to alternative representation value.
     * Value 99999999.9999 is a maximum value that may be stored in Magento decimal columns in DB.
     *
     * @param float $value
     * @return float
     */
    protected function _convertFloat($value)
    {
        $value = (float) $value;

        if (is_infinite($value)) {
            $value = ($value > 0)
                ? 99999999.9999
                : -99999999.9999;
        } elseif (is_nan($value)) {
            $value = 0.0;
        }

        return $value;
    }

    /**
     * Check if all transactions have been committed
     */
    public function __destruct()
    {
        if ($this->_transactionLevel > 0) {
            throw new \RuntimeException(\Maho\Db\Adapter\AdapterInterface::ERROR_TRANSACTION_NOT_COMMITTED);
        }
    }
}
