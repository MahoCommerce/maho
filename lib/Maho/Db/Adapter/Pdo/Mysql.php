<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter\Pdo;

use Maho\Db\Adapter\AbstractPdoAdapter;
use Maho\Db\Helper;

class Mysql extends AbstractPdoAdapter
{
    // MySQL-specific constants
    public const DDL_CACHE_PREFIX      = 'DB_PDO_MYSQL_DDL';
    public const DDL_CACHE_TAG         = 'DB_PDO_MYSQL_DDL';

    /**
     * MEMORY engine type for MySQL tables
     */
    public const ENGINE_MEMORY = 'MEMORY';

    /**
     * Default class name for a DB statement.
     */
    protected string $_defaultStmtClass = \Maho\Db\Statement\Pdo\Mysql::class;

    /**
     * Log file name for SQL debug data (override parent's default)
     */
    protected string $_debugFile = 'pdo_mysql.log';

    /**
     * MySQL column - Table DDL type pairs
     */
    protected array $_ddlColumnTypes = [
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
        \Maho\Db\Ddl\Table::TYPE_VARCHAR       => 'varchar',
        \Maho\Db\Ddl\Table::TYPE_BLOB          => 'blob',
        \Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'blob',
    ];

    // =========================================================================
    // MySQL-Specific Helper Methods
    // =========================================================================

    /**
     * Parse a source hostname and generate a host info
     */
    protected function _getHostInfo(string $hostName): \Maho\DataObject
    {
        $hostInfo = new \Maho\DataObject();
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

    // =========================================================================
    // Abstract Method Implementations from AbstractPdoAdapter
    // =========================================================================

    /**
     * Get the driver name for this adapter
     */
    #[\Override]
    protected function getDriverName(): string
    {
        return 'pdo_mysql';
    }

    /**
     * Get the identifier quote character for MySQL (backtick)
     */
    #[\Override]
    protected function getIdentifierQuoteChar(): string
    {
        return '`';
    }

    /**
     * Run MySQL-specific initialization statements after connection
     */
    #[\Override]
    protected function _initConnection(): void
    {
        /** @link http://bugs.mysql.com/bug.php?id=18551 */
        $this->_connection->executeStatement("SET SQL_MODE=''");
    }

    /**
     * Get DDL cache prefix for MySQL
     */
    #[\Override]
    protected function getDdlCachePrefix(): string
    {
        return self::DDL_CACHE_PREFIX;
    }

    /**
     * Get DDL cache tag for MySQL
     */
    #[\Override]
    protected function getDdlCacheTag(): string
    {
        return self::DDL_CACHE_TAG;
    }

    // =========================================================================
    // MySQL-Specific Connection Methods
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
            // Use Pdo\Mysql class constants (PHP 8.4+) or fallback to PDO constants (PHP 8.3)
            $driverOptions[class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
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
     * @throws \PDOException
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function raw_query(string $sql): \Maho\Db\Statement\Pdo\Mysql
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
     * All bind parameter names must begin with ':'.
     *
     * @throws \RuntimeException To re-throw PDOException.
     */
    #[\Override]
    public function query(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): \Maho\Db\Statement\Pdo\Mysql
    {
        // OpenTelemetry: Start span if tracer available (fast null check)
        $span = \Mage::startSpan('db.query', [
            'db.system' => 'mysql',
            'db.name' => $this->_config['dbname'] ?? '',
        ]);

        $this->_debugTimer();
        try {
            $this->_checkDdlTransaction($sql);
            $this->_prepareQuery($sql, $bind);

            // OpenTelemetry: Add SQL operation type after _prepareQuery() converts to string
            if ($span) {
                /** @var string $sql _prepareQuery() converts Select to string via (string) cast */
                $span->setAttribute('db.operation', $this->_getOperationType($sql));
            }

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

            // OpenTelemetry: Mark span as successful
            $span?->setStatus('ok');
        } catch (\Exception $e) {
            $this->_debugStat(self::DEBUG_QUERY, $sql, $bind);

            // OpenTelemetry: Record exception in span
            $span?->recordException($e);
            $span?->setStatus('error', $e->getMessage());

            // Detect implicit rollback - MySQL SQLSTATE: ER_LOCK_WAIT_TIMEOUT or ER_LOCK_DEADLOCK
            $previous = $e->getPrevious();
            if ($this->_transactionLevel > 0
                && $previous instanceof \PDOException && isset($previous->errorInfo[1])
                && in_array($previous->errorInfo[1], [1205, 1213])
            ) {
                if ($this->_debug) {
                    $this->_debugWriteToFile('IMPLICIT ROLLBACK AFTER SQLSTATE: ' . $previous->errorInfo[1]);
                }
                $this->_transactionLevel = 1; // Deadlock rolls back entire transaction
                $this->rollBack();
            }

            $this->_debugException($e);
        } finally {
            // OpenTelemetry: End span
            $span?->end();
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

        // Convert DateTime and Expr objects to strings for database compatibility
        // This ensures values are properly formatted before being bound to queries
        foreach ($bind as $k => $v) {
            if ($v instanceof \DateTime) {
                $bind[$k] = $v->format('Y-m-d H:i:s');
            } elseif ($v instanceof \Maho\Db\Expr) {
                // Expr objects should NOT be in bind parameters - they should be in the SQL itself
                // But if they are here, extract the expression and remove quotes (PDO will add them)
                $exprValue = (string) $v;
                // Remove surrounding quotes if present
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
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function multiQuery(string $sql): array
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
     */
    #[\Override]
    public function dropForeignKey(string $tableName, string $fkName, ?string $schemaName = null): self
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
     * Generally $defintion must be array with column data to keep this call cross-DB compatible.
     * Using string as $definition is allowed only for concrete DB adapter.
     * Adds primary key if needed
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function addColumn(string $tableName, string $columnName, array|string $definition, ?string $schemaName = null): self
    {
        if ($this->tableColumnExists($tableName, $columnName, $schemaName)) {
            return $this;
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

        $this->raw_query($sql);
        $this->resetDdlCache($tableName, $schemaName);

        return true;
    }

    /**
     * Change the column name and definition
     *
     * For change definition of column - use modifyColumn
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
     */
    #[\Override]
    public function showTableStatus(string $tableName, ?string $schemaName = null): array|false
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
     */
    public function getCreateTable(string $tableName, ?string $schemaName = null): string
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
     * @return string[] List of table names
     */
    #[\Override]
    public function listTables(?string $schemaName = null): array
    {
        $this->_connect();
        $schemaManager = $this->_connection->createSchemaManager();

        if ($schemaName !== null) {
            // Switch to specified schema
            $originalDb = $this->_connection->getDatabase();
            try {
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($schemaName));
                $tableNames = $schemaManager->introspectTableNames();
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($originalDb));
            } catch (\Exception $e) {
                // Restore original database even on error
                $this->_connection->executeStatement('USE ' . $this->quoteIdentifier($originalDb));
                throw $e;
            }
        } else {
            $tableNames = $schemaManager->introspectTableNames();
        }

        return array_map(
            fn($name) => $name->getUnqualifiedName()->getValue(),
            $tableNames,
        );
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
     */
    #[\Override]
    public function getForeignKeys(string $tableName, ?string $schemaName = null): array
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
     * @return array<string, array<string, array<string, mixed>>>
     */
    #[\Override]
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
     * Modify tables, used for upgrade process
     * Change columns definitions, reset foreign keys, change tables comments and engines.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * columns => array; list of columns definitions
     * comment => string; table comment
     * engine  => string; table engine
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
     */
    #[\Override]
    public function getIndexList(string $tableName, ?string $schemaName = null): array
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
     */
    #[\Override]
    public function select(): \Maho\Db\Select
    {
        return new \Maho\Db\Select($this);
    }

    /**
     * Logging debug information (MySQL-specific format with PID and row count)
     */
    #[\Override]
    protected function _debugStat(int $type, string $sql, array $bind = [], mixed $result = null): static
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
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quote
     * and then returned as a comma-separated string.
     */
    #[\Override]
    public function quote(\Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value, null|string|int $type = null): string
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
            && $this->_numericDataTypes[$type] == self::FLOAT_TYPE) {
            $value = $this->_convertFloat($value);
            $quoteValue = sprintf('%F', $value);
            return $quoteValue;
        }
        if (is_float($value)) {
            return (string) $this->_quote($value);
        }

        if (is_int($value)) {
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
     */
    #[\Override]
    public function quoteInto(string $text, \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value, null|string|int $type = null, ?int $count = null): string
    {
        if (is_array($value) && empty($value)) {
            $value = new \Maho\Db\Expr('NULL');
        }

        if ($count === null) {
            return str_replace('?', (string) $this->quote($value, $type), $text, $count);
        }
        while ($count > 0) {
            $text = substr_replace($text, (string) $this->quote($value, $type), strpos($text, '?'), 1);
            --$count;
        }
        return $text;
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
     */
    #[\Override]
    public function loadDdlCache(string $tableCacheKey, int $ddlType): string|array|int|false
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
     */
    #[\Override]
    public function saveDdlCache(string $tableCacheKey, int $ddlType, mixed $data): static
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
     */
    #[\Override]
    public function resetDdlCache(?string $tableName = null, ?string $schemaName = null): static
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
     */
    #[\Override]
    public function disallowDdlCache(): static
    {
        $this->_isDdlCacheAllowed = false;
        return $this;
    }

    /**
     * Allow DDL caching
     */
    #[\Override]
    public function allowDdlCache(): static
    {
        $this->_isDdlCacheAllowed = true;
        return $this;
    }

    /**
     * Decorate a table info by detecting and parsing the binary/varbinary fields
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
     */
    #[\Override]
    public function describeTable(string $tableName, ?string $schemaName = null): array
    {
        $cacheKey = $this->_getTableName($tableName, $schemaName);
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_DESCRIBE);
        if ($ddl === false) {
            $this->_connect();

            // Get the full table name with schema if provided
            $fullTableName = $this->_getTableName($tableName, $schemaName);

            // Use Doctrine DBAL SchemaManager for table introspection
            $schemaManager = $this->_connection->createSchemaManager();
            $table = $schemaManager->introspectTableByUnquotedName($fullTableName);
            $platform = $this->_connection->getDatabasePlatform();

            // Get primary key information
            $primaryKey = [];
            $primaryKeyPositions = [];
            $pkConstraint = $table->getPrimaryKeyConstraint();
            if ($pkConstraint) {
                $pkColumns = $pkConstraint->getColumnNames();
                foreach ($pkColumns as $index => $columnNameObj) {
                    $columnName = $columnNameObj->getIdentifier()->getValue();
                    $primaryKey[] = $columnName;
                    $primaryKeyPositions[$columnName] = $index + 1;
                }
            }

            $ddl = [];
            $position = 1;

            foreach ($table->getColumns() as $column) {
                $columnName = $column->getObjectName()->getIdentifier()->getValue();

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
     * @return array{name: string, type: ?string, length: ?int, options: array<string, mixed>, comment: string}
     */
    #[\Override]
    public function getColumnCreateByDescribe(array $columnData): array
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
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function modifyColumnByDdl(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self
    {
        if (is_string($definition)) {
            // If definition is a string, pass it directly to modifyColumn
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
     * Retrieve column data type by data from describe table
     */
    #[\Override]
    protected function _getColumnTypeByDdl(array $column): ?string
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
     */
    #[\Override]
    public function changeTableComment(string $tableName, string $comment, ?string $schemaName = null): mixed
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql   = sprintf("ALTER TABLE %s COMMENT='%s'", $table, $comment);

        return $this->raw_query($sql);
    }

    /**
     * Change table auto increment value
     */
    #[\Override]
    public function changeTableAutoIncrement(string $tableName, int $increment, ?string $schemaName = null): \Maho\Db\Statement\Pdo\Mysql
    {
        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $sql = sprintf('ALTER TABLE %s AUTO_INCREMENT=%d', $table, $increment);
        return $this->raw_query($sql);
    }

    /**
     * Inserts a table row with specified data
     * Special for Zero values to identity column
     */
    #[\Override]
    public function insertForce(string $table, array $bind): int
    {
        $this->raw_query("SET @OLD_INSERT_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        $result = $this->insert($table, $bind);
        $this->raw_query("SET SQL_MODE=IFNULL(@OLD_INSERT_SQL_MODE,'')");

        return $result;
    }

    /**
     * Inserts a table row with specified data.
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertOnDuplicate(string|array|\Maho\Db\Select $table, array $data, array $fields = []): int
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
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertMultiple(string|array|\Maho\Db\Select $table, array $data): int
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
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function insertArray(string $table, array $columns, array $data): int
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
     * @throws \RuntimeException
     */
    #[\Override]
    public function insertIgnore(string|array|\Maho\Db\Select $table, array $bind): int
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
     */
    #[\Override]
    public function lastInsertId(?string $tableName = null, ?string $primaryKey = null): string|int
    {
        $this->_connect();
        return $this->_connection->lastInsertId();
    }

    /**
     * Acquire a named lock using MySQL GET_LOCK()
     */
    #[\Override]
    public function getLock(string $lockName, int $timeout = 0): bool
    {
        $this->_connect();
        return (bool) $this->fetchOne('SELECT GET_LOCK(?, ?)', [$lockName, $timeout]);
    }

    /**
     * Release a named lock using MySQL RELEASE_LOCK()
     */
    #[\Override]
    public function releaseLock(string $lockName): bool
    {
        $this->_connect();
        return (bool) $this->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }

    /**
     * Check if a named lock is currently held using MySQL IS_USED_LOCK()
     */
    #[\Override]
    public function isLocked(string $lockName): bool
    {
        $this->_connect();
        return (bool) $this->fetchOne('SELECT IS_USED_LOCK(?)', [$lockName]);
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

    /**
     * Return new DDL Table object
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
     * Create table
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function createTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Mysql
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
     */
    #[\Override]
    public function createTemporaryTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\Pdo\Mysql
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
     * @return string
     * @throws \Maho\Exception
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
            case \Maho\Db\Ddl\Table::TYPE_VARCHAR:
            case \Maho\Db\Ddl\Table::TYPE_BLOB:
            case \Maho\Db\Ddl\Table::TYPE_VARBINARY:
                if (empty($options['LENGTH'])) {
                    $length = \Maho\Db\Ddl\Table::DEFAULT_TEXT_SIZE;
                } else {
                    $length = $this->_parseTextSize($options['LENGTH']);
                }
                if ($length <= 255) {
                    $cType = ($ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT || $ddlType == \Maho\Db\Ddl\Table::TYPE_VARCHAR) ? 'varchar' : 'varbinary';
                    $cType = sprintf('%s(%d)', $cType, $length);
                } elseif ($length <= 65536) {
                    $cType = ($ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT || $ddlType == \Maho\Db\Ddl\Table::TYPE_VARCHAR) ? 'text' : 'blob';
                } elseif ($length <= 16777216) {
                    $cType = ($ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT || $ddlType == \Maho\Db\Ddl\Table::TYPE_VARCHAR) ? 'mediumtext' : 'mediumblob';
                } else {
                    $cType = ($ddlType == \Maho\Db\Ddl\Table::TYPE_TEXT || $ddlType == \Maho\Db\Ddl\Table::TYPE_VARCHAR) ? 'longtext' : 'longblob';
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
        $query = 'DROP TEMPORARY TABLE IF EXISTS ' . $table;
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
            throw new \Maho\Db\Exception(sprintf('Table "%s" is not exists', $tableName));
        }

        $table = $this->quoteIdentifier($this->_getTableName($tableName, $schemaName));
        $query = 'TRUNCATE TABLE ' . $table;
        $this->query($query);

        return $this;
    }

    /**
     * Check is a table exists
     */
    #[\Override]
    public function isTableExists(string $tableName, ?string $schemaName = null): bool
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
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTable(string $oldTableName, string $newTableName, ?string $schemaName = null): bool
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
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function renameTablesBatch(array $tablePairs): bool
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
     * @throws \Maho\Db\Exception|\Exception
     */
    #[\Override]
    public function addIndex(
        string $tableName,
        string $indexName,
        string|array $fields,
        string $indexType = \Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_INDEX,
        ?string $schemaName = null,
    ): \Maho\Db\Statement\Pdo\Mysql {
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
     */
    #[\Override]
    public function dropIndex(string $tableName, string $keyName, ?string $schemaName = null): bool|\Maho\Db\Statement\Pdo\Mysql
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
     * Format Date to internal database date format
     */
    #[\Override]
    public function formatDate(int|string|\DateTime $date, bool $includeTime = true): \Maho\Db\Expr
    {
        if ($date instanceof \DateTime) {
            $format = $includeTime ? \Mage_Core_Model_Locale::DATETIME_FORMAT : \Mage_Core_Model_Locale::DATE_FORMAT;
            $date = $date->format($format);
        } elseif (empty($date)) {
            return new \Maho\Db\Expr('NULL');
        } else {
            if (!is_numeric($date)) {
                $date = strtotime($date);
            }
            $format = $includeTime ? \Mage_Core_Model_Locale::DATETIME_FORMAT : \Mage_Core_Model_Locale::DATE_FORMAT;
            $date = date($format, $date);
        }

        if ($date === null) {
            return new \Maho\Db\Expr('NULL');
        }

        return new \Maho\Db\Expr($this->quote($date));
    }

    /**
     * Run additional environment before setup
     */
    #[\Override]
    public function startSetup(): self
    {
        $this->raw_query("SET SQL_MODE=''");
        $this->raw_query('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
        $this->raw_query("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

        return $this;
    }

    /**
     * Run additional environment after setup
     */
    #[\Override]
    public function endSetup(): self
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
            // Handle NULL values - generate IS NULL condition
            $query = str_replace('{{fieldName}}', (string) $fieldName, $conditionKeyMap['null']);
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
        }
        return ($conditionKey == 'seq') ? 'eq' : 'neq';
    }

    /**
     * Prepare value for save in column
     * Return converted to column data type value
     *
     * @param array $column     the column describe array
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
                if ($column['NULLABLE'] && ($value === false || $value === '' || $value === null)) {
                    $value = null;
                } else {
                    $value = $this->formatDate($value, false);
                }
                break;
            case 'datetime':
            case 'timestamp':
                if ($column['NULLABLE'] && ($value === false || $value === '' || $value === null)) {
                    $value = null;
                } else {
                    $value = $this->formatDate($value, true);
                }
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
     * @param string $true  true value
     * @param string $false false value
     */
    #[\Override]
    public function getCheckSql(\Maho\Db\Expr|\Maho\Db\Select|string $expression, \Maho\Db\Expr|string $true, \Maho\Db\Expr|string $false): \Maho\Db\Expr
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
     * @param string|int $value OPTIONAL. Applies when $expression is NULL
     */
    #[\Override]
    public function getIfNullSql(\Maho\Db\Expr|\Maho\Db\Select|string $expression, string|int $value = '0'): \Maho\Db\Expr
    {
        if ($expression instanceof \Maho\Db\Expr || $expression instanceof \Maho\Db\Select) {
            $expression = sprintf('IFNULL((%s), %s)', $expression, $value);
        } else {
            $expression = sprintf('IFNULL(%s, %s)', $expression, $value);
        }

        return new \Maho\Db\Expr($expression);
    }

    /**
     * Generate fragment of SQL for rounding a numeric value to specified precision.
     *
     * @param string $expression Expression to round
     * @param int $precision Number of decimal places
     */
    #[\Override]
    public function getRoundSql(string $expression, int $precision = 0): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('ROUND(%s, %d)', $expression, $precision));
    }

    /**
     * Generate fragment of SQL to cast a value to text/varchar for comparison.
     * MySQL performs implicit casting, so we just return the expression as-is.
     *
     * @param string $expression Expression to cast
     */
    #[\Override]
    public function getCastToTextSql(string $expression): \Maho\Db\Expr
    {
        // MySQL handles implicit type conversion well
        return new \Maho\Db\Expr($expression);
    }

    /**
     * Generate fragment of SQL, that check value against multiple condition cases
     * and return different result depends on them
     *
     * @param string $valueName Name of value to check
     * @param array $casesResults Cases and results
     * @param string $defaultValue value to use if value doesn't conform to any cases
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
     * Generate fragment of SQL, that combine together (concatenate) the results from data array
     * All arguments in data must be quoted
     *
     * @param string $separator concatenate with separator
     */
    #[\Override]
    protected function getConcatWithSeparatorSql(array $data, string $separator): \Maho\Db\Expr
    {
        // MySQL uses CONCAT_WS (concat with separator)
        return new \Maho\Db\Expr(sprintf("CONCAT_WS('%s', %s)", $separator, implode(', ', $data)));
    }

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the smallest
     * (minimum-valued) argument
     * All arguments in data must be quoted
     */
    #[\Override]
    public function getLeastSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('LEAST(%s)', implode(', ', $data)));
    }

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the largest
     * (maximum-valued) argument
     * All arguments in data must be quoted
     */
    #[\Override]
    public function getGreatestSql(array $data): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('GREATEST(%s)', implode(', ', $data)));
    }

    /**
     * Get Interval Unit SQL fragment
     *
     * @param int $interval
     * @throws \Maho\Db\Exception
     */
    protected function _getIntervalUnitSql(int|string $interval, string $unit): string
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
     */
    #[\Override]
    public function getDateAddSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
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
     */
    #[\Override]
    public function getDateSubSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr
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
     */
    #[\Override]
    public function getDateFormatSql(\Maho\Db\Expr|string $date, string $format): \Maho\Db\Expr
    {
        $expr = sprintf("DATE_FORMAT(%s, '%s')", $date, $format);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Get SQL expression for days until next annual occurrence of a date
     *
     * Uses MySQL's DATEDIFF, DATE_FORMAT, YEAR, and DAYOFYEAR functions.
     *
     * @param \Maho\Db\Expr|string $dateField The date field containing the anniversary
     * @param string $referenceDate The reference date (usually today)
     */
    #[\Override]
    public function getDaysUntilAnniversarySql(\Maho\Db\Expr|string $dateField, string $referenceDate): \Maho\Db\Expr
    {
        $refDate = $this->quote($referenceDate);

        // Calculate next anniversary by properly handling year differences
        // This handles cases where birth year > current year (test data) or birth year < current year (real data)
        $sql = "CASE
            WHEN YEAR({$dateField}) > YEAR({$refDate}) THEN
                DATEDIFF(DATE_FORMAT({$dateField}, CONCAT(YEAR({$refDate}), '-%m-%d')), {$refDate})
            ELSE
                DATEDIFF(
                    CASE
                        WHEN DAYOFYEAR({$refDate}) > DAYOFYEAR({$dateField}) THEN
                            DATE_FORMAT({$dateField}, CONCAT(YEAR({$refDate}) + 1, '-%m-%d'))
                        ELSE
                            DATE_FORMAT({$dateField}, CONCAT(YEAR({$refDate}), '-%m-%d'))
                    END,
                    {$refDate}
                )
            END";

        return new \Maho\Db\Expr($sql);
    }

    /**
     * Extract the date part of a date or datetime expression
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    #[\Override]
    public function getDatePartSql(\Maho\Db\Expr|string $date): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('DATE(%s)', $date));
    }

    /**
     * Prepare standard deviation sql function
     *
     * @param \Maho\Db\Expr|string $expressionField   quoted field name or SQL statement
     */
    #[\Override]
    public function getStandardDeviationSql(\Maho\Db\Expr|string $expressionField): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('STDDEV_SAMP(%s)', $expressionField));
    }

    /**
     * Extract part of a date
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function getDateExtractSql(\Maho\Db\Expr|string $date, string $unit): \Maho\Db\Expr
    {
        if (!isset($this->_intervalUnits[$unit])) {
            throw new \Maho\Db\Exception(sprintf('Undefined interval unit "%s" specified', $unit));
        }

        $expr = sprintf('EXTRACT(%s FROM %s)', $this->_intervalUnits[$unit], $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Retrieve valid table name
     * Check table name length and allowed symbols
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
     * Check index name length and allowed symbols
     *
     * @param string|array $fields  the columns list
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

        return strtoupper($hash);
    }

    /**
     * Retrieve valid foreign key name
     * Check foreign key name length and allowed symbols
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

        return strtoupper($hash);
    }

    /**
     * Stop updating indexes
     *
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function disableTableKeys(string $tableName, ?string $schemaName = null): self
    {
        $tableName = $this->_getTableName($tableName, $schemaName);
        $query     = sprintf('ALTER TABLE %s DISABLE KEYS', $this->quoteIdentifier($tableName));
        $this->query($query);

        return $this;
    }

    /**
     * Re-create missing indexes
     *
     * @param string $schemaName
     * @return $this
     */
    #[\Override]
    public function enableTableKeys(string $tableName, ?string $schemaName = null): self
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
     */
    #[\Override]
    public function insertFromSelect(\Maho\Db\Select $select, string $table, array $fields = [], bool|int $mode = false): string
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
     * Convert date format to unix time
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function getUnixTimestamp(string|\Maho\Db\Expr $date): \Maho\Db\Expr
    {
        $expr = sprintf('UNIX_TIMESTAMP(%s)', $date);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Convert unix time to date format
     */
    #[\Override]
    public function fromUnixtime(int|\Maho\Db\Expr $timestamp): \Maho\Db\Expr
    {
        $expr = sprintf('FROM_UNIXTIME(%s)', $timestamp);
        return new \Maho\Db\Expr($expr);
    }

    /**
     * Get SQL expression for timestamp difference in seconds
     *
     * Returns the difference between two timestamps in seconds (end - start).
     */
    #[\Override]
    public function getTimestampDiffExpr(string $startTimestamp, string $endTimestamp): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('TIMESTAMPDIFF(SECOND, %s, %s)', $startTimestamp, $endTimestamp));
    }

    /**
     * Get SQL expression for concatenating grouped values
     */
    #[\Override]
    public function getGroupConcatExpr(string $expression, string $separator = ','): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf("GROUP_CONCAT(%s SEPARATOR '%s')", $expression, $separator));
    }

    /**
     * Get SQL expression for FIND_IN_SET functionality
     */
    #[\Override]
    public function getFindInSetExpr(string $needle, string $haystack): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf('FIND_IN_SET(%s, %s)', $needle, $haystack));
    }

    /**
     * Get SQL expression for Unix timestamp
     */
    #[\Override]
    public function getUnixTimestampExpr(?string $timestamp = null): \Maho\Db\Expr
    {
        if ($timestamp === null) {
            return new \Maho\Db\Expr('UNIX_TIMESTAMP()');
        }
        return new \Maho\Db\Expr(sprintf('UNIX_TIMESTAMP(%s)', $timestamp));
    }

    /**
     * Extract a scalar value from a JSON column at a given path (MySQL)
     */
    #[\Override]
    public function getJsonExtractExpr(string $column, string $path): \Maho\Db\Expr
    {
        return new \Maho\Db\Expr(sprintf(
            'JSON_UNQUOTE(JSON_EXTRACT(%s, %s))',
            $column,
            $this->quote($path),
        ));
    }

    /**
     * Search for a string value within a JSON column (MySQL)
     */
    #[\Override]
    public function getJsonSearchExpr(string $column, string $value, string $path): \Maho\Db\Expr
    {
        if (str_contains($path, '$**')) {
            // JSON_SEARCH uses LIKE-style matching; escape special LIKE chars for literal match
            $escapedValue = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
            return new \Maho\Db\Expr(sprintf(
                'JSON_SEARCH(%s, %s, %s, NULL, %s) IS NOT NULL',
                $column,
                $this->quote('one'),
                $this->quote($escapedValue),
                $this->quote($path),
            ));
        }

        $extractExpr = $this->getJsonExtractExpr($column, $path);
        return new \Maho\Db\Expr(sprintf('%s = %s', $extractExpr, $this->quote($value)));
    }

    /**
     * Check if a JSON column contains a specific JSON value (MySQL)
     */
    #[\Override]
    public function getJsonContainsExpr(string $column, string $value, ?string $path = null): \Maho\Db\Expr
    {
        if ($path !== null && str_contains($path, '$**')) {
            throw new \InvalidArgumentException('Wildcard paths are not supported in getJsonContainsExpr(). Use getJsonSearchExpr() instead.');
        }

        if ($path !== null) {
            return new \Maho\Db\Expr(sprintf(
                'JSON_CONTAINS(%s, %s, %s)',
                $column,
                $this->quote($value),
                $this->quote($path),
            ));
        }

        return new \Maho\Db\Expr(sprintf(
            'JSON_CONTAINS(%s, %s)',
            $column,
            $this->quote($value),
        ));
    }

    /**
     * Get update table query using select object for join and update
     *
     * @throws \Maho\Db\Exception
     */
    #[\Override]
    public function updateFromSelect(\Maho\Db\Select $select, string|array $table): string
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
     * @param string|array|\Maho\Db\Select $table The table to update.
     * @param string|array $where DELETE WHERE clause(s).
     * @return int The number of affected rows.
     */
    #[\Override]
    public function delete(string|array|\Maho\Db\Select $table, string|array $where = ''): int
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
     */
    #[\Override]
    public function deleteFromSelect(\Maho\Db\Select $select, string|array $table): string
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
     */
    #[\Override]
    public function getTablesChecksum(array|string $tableNames, ?string $schemaName = null): array
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
     */
    #[\Override]
    public function supportStraightJoin(): bool
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
    public function orderRand(\Maho\Db\Select $select, ?string $field = null): self
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
     */
    #[\Override]
    public function forUpdate(string $sql): string
    {
        return sprintf('%s FOR UPDATE', $sql);
    }

    /**
     * Prepare insert data
     */
    #[\Override]
    protected function _prepareInsertData(mixed $row, array &$bind): string
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
     */
    #[\Override]
    protected function _getInsertSqlQuery(string $tableName, array $columns, array $values): string
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
     */
    #[\Override]
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
     * Return DDL action
     */
    #[\Override]
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
     * Prepare sql date condition
     */
    #[\Override]
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
     * Try to find installed primary key name, if not - format new one.
     *
     * @param string $tableName Table name
     * @param string $schemaName OPTIONAL
     * @return string Primary Key name
     */
    #[\Override]
    public function getPrimaryKeyName(string $tableName, ?string $schemaName = null): string
    {
        $indexes = $this->getIndexList($tableName, $schemaName);
        if (isset($indexes['PRIMARY'])) {
            return $indexes['PRIMARY']['KEY_NAME'];
        }
        return 'PK_' . strtoupper($tableName);
    }

    /**
     * Parse text size
     * Returns max allowed size if value great it
     */
    #[\Override]
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
     * Converts fetched blob into raw binary PHP data.
     * The MySQL drivers do it nice, no processing required.
     *
     * @mixed $value
     */
    #[\Override]
    public function decodeVarbinary(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Returns date that fits into TYPE_DATETIME range and is suggested to act as default 'zero' value
     * for a column for current RDBMS.
     */
    #[\Override]
    public function getSuggestedZeroDate(): string
    {
        return '0000-00-00 00:00:00';
    }

    /**
     * Drop trigger
     */
    #[\Override]
    public function dropTrigger(string $triggerName): self
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
     */
    #[\Override]
    public function createTableFromSelect(string $tableName, \Maho\Db\Select $select, bool $temporary = false): void
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
     */
    protected function _convertFloat(float $value): float
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
     * Get SQL operation type (SELECT, INSERT, UPDATE, DELETE, etc.)
     *
     * @param string $sql SQL query string
     * @return string Operation type in uppercase
     */
    protected function _getOperationType(string $sql): string
    {
        $sql = trim($sql);
        $firstSpace = strpos($sql, ' ');
        return $firstSpace !== false ? strtoupper(substr($sql, 0, $firstSpace)) : 'UNKNOWN';
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
