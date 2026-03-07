<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db\Adapter;

interface AdapterInterface
{
    public const INDEX_TYPE_PRIMARY    = 'primary';
    public const INDEX_TYPE_UNIQUE     = 'unique';
    public const INDEX_TYPE_INDEX      = 'index';
    public const INDEX_TYPE_FULLTEXT   = 'fulltext';

    public const FK_ACTION_CASCADE     = 'CASCADE';
    public const FK_ACTION_SET_NULL    = 'SET NULL';
    public const FK_ACTION_NO_ACTION   = 'NO ACTION';
    public const FK_ACTION_RESTRICT    = 'RESTRICT';
    public const FK_ACTION_SET_DEFAULT = 'SET DEFAULT';

    public const INSERT_ON_DUPLICATE   = 1;
    public const INSERT_IGNORE         = 2;

    public const ISO_DATE_FORMAT       = 'yyyy-MM-dd';
    public const ISO_DATETIME_FORMAT   = 'yyyy-MM-dd HH-mm-ss';

    public const INTERVAL_SECOND       = 'SECOND';
    public const INTERVAL_MINUTE       = 'MINUTES';
    public const INTERVAL_HOUR         = 'HOURS';
    public const INTERVAL_DAY          = 'DAYS';
    public const INTERVAL_MONTH        = 'MONTHS';
    public const INTERVAL_YEAR         = 'YEARS';

    /**
     * Error message for DDL query in transactions
     */
    public const ERROR_DDL_MESSAGE = 'DDL statements are not allowed in transactions';
    public const ERROR_TRANSACTION_NOT_COMMITTED = 'Some transactions have not been committed or rolled back';

    /**
     * Begin new DB transaction for connection
     */
    public function beginTransaction(): self;

    /**
     * Commit DB transaction
     */
    public function commit(): self;

    /**
     * Roll-back DB transaction
     */
    public function rollBack(): self;

    /**
     * Get the underlying Doctrine DBAL Connection
     */
    public function getConnection(): \Doctrine\DBAL\Connection;

    /**
     * Retrieve DDL object for new table
     *
     * @param string|null $tableName the table name
     * @param string|null $schemaName the database or schema name
     */
    public function newTable(?string $tableName = null, ?string $schemaName = null): \Maho\Db\Ddl\Table;

    /**
     * Create table from DDL object
     *
     * @throws \Maho\Db\Exception
     */
    public function createTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\StatementInterface;

    /**
     * Create temporary table from DDL object
     *
     * @throws \Maho\Db\Exception
     */
    public function createTemporaryTable(\Maho\Db\Ddl\Table $table): \Maho\Db\Statement\StatementInterface;

    /**
     * Drop table from database
     */
    public function dropTable(string $tableName, ?string $schemaName = null): bool;

    /**
     * Drop temporary table from database
     */
    public function dropTemporaryTable(string $tableName, ?string $schemaName = null): bool;

    /**
     * Truncate a table
     */
    public function truncateTable(string $tableName, ?string $schemaName = null): self;

    /**
     * Checks if table exists
     */
    public function isTableExists(string $tableName, ?string $schemaName = null): bool;

    /**
     * Returns short table status array
     */
    public function showTableStatus(string $tableName, ?string $schemaName = null): array|false;

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
    public function describeTable(string $tableName, ?string $schemaName = null): array;

    /**
     * Create \Maho\Db\Ddl\Table object by data from describe table
     */
    public function createTableByDdl(string $tableName, string $newTableName): \Maho\Db\Ddl\Table;

    /**
     * Modify the column definition by data from describe table
     */
    public function modifyColumnByDdl(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self;

    /**
     * Rename table
     */
    public function renameTable(string $oldTableName, string $newTableName, ?string $schemaName = null): bool;

    /**
     * Rename several tables
     *
     * @param array $tablePairs array('oldName' => 'Name1', 'newName' => 'Name2')
     *
     * @throws \Maho\Db\Exception
     */
    public function renameTablesBatch(array $tablePairs): bool;

    /**
     * Adds new column to the table.
     *
     * Generally $defintion must be array with column data to keep this call cross-DB compatible.
     * Using string as $definition is allowed only for concrete DB adapter.
     */
    public function addColumn(string $tableName, string $columnName, array|string $definition, ?string $schemaName = null): self;

    /**
     * Change the column name and definition
     *
     * For change definition of column - use modifyColumn
     */
    public function changeColumn(
        string $tableName,
        string $oldColumnName,
        string $newColumnName,
        array|string $definition,
        bool $flushData = false,
        ?string $schemaName = null,
    ): self;

    /**
     * Modify the column definition
     */
    public function modifyColumn(string $tableName, string $columnName, array|string $definition, bool $flushData = false, ?string $schemaName = null): self;

    /**
     * Drop the column from table
     */
    public function dropColumn(string $tableName, string $columnName, ?string $schemaName = null): bool;

    /**
     * Check is table column exists
     */
    public function tableColumnExists(string $tableName, string $columnName, ?string $schemaName = null): bool;

    /**
     * Add new index to table name
     *
     * @param string|array $fields  the table column name or array of ones
     */
    public function addIndex(string $tableName, string $indexName, string|array $fields, string $indexType = self::INDEX_TYPE_INDEX, ?string $schemaName = null): \Maho\Db\Statement\StatementInterface;

    /**
     * Drop the index from table
     */
    public function dropIndex(string $tableName, string $keyName, ?string $schemaName = null): bool|\Maho\Db\Statement\StatementInterface;

    /**
     * Returns the table index information
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
    public function getIndexList(string $tableName, ?string $schemaName = null): array;

    /**
     * Add new Foreign Key to table
     * If Foreign Key with same name is exist - it will be deleted
     */
    public function addForeignKey(
        string $fkName,
        string $tableName,
        string $columnName,
        string $refTableName,
        string $refColumnName,
        string $onDelete = self::FK_ACTION_CASCADE,
        string $onUpdate = self::FK_ACTION_CASCADE,
        bool $purge = false,
        ?string $schemaName = null,
        ?string $refSchemaName = null,
    ): self;

    /**
     * Drop the Foreign Key from table
     */
    public function dropForeignKey(string $tableName, string $fkName, ?string $schemaName = null): self;

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
    public function getForeignKeys(string $tableName, ?string $schemaName = null): array;

    /**
     * Creates and returns a new \Maho\Db\Select object for this adapter.
     */
    public function select(): \Maho\Db\Select;

    /**
     * Inserts a table row with specified data.
     *
     * @param string|array|\Maho\Db\Select $table The table to insert data into.
     * @param array $data Column-value pairs or array of column-value pairs.
     * @param array $fields update fields pairs or values
     * @return int The number of affected rows.
     */
    public function insertOnDuplicate(string|array|\Maho\Db\Select $table, array $data, array $fields = []): int;

    /**
     * Inserts a table multiply rows with specified data.
     *
     * @param string|array|\Maho\Db\Select $table The table to insert data into.
     * @param array $data Column-value pairs or array of Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insertMultiple(string|array|\Maho\Db\Select $table, array $data): int;

    /**
     * Insert array to table based on columns definition
     *
     * @param array $columns  the data array column map
     */
    public function insertArray(string $table, array $columns, array $data): int;

    /**
     * Inserts a table row with specified data.
     *
     * @param string|array|\Maho\Db\Select $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insert(string|array|\Maho\Db\Select $table, array $bind): int;

    /**
     * Inserts a table row with specified data
     * Special for Zero values to identity column
     *
     * @return int The number of affected rows.
     */
    public function insertForce(string $table, array $bind): int;

    /**
     * Updates table rows with specified data based on a WHERE clause.
     *
     * @param  string|array|\Maho\Db\Select $table The table to update.
     * @param  array        $bind  Column-value pairs.
     * @param  string|array $where UPDATE WHERE clause(s).
     * @return int          The number of affected rows.
     */
    public function update(string|array|\Maho\Db\Select $table, array $bind, string|array $where = ''): int;

    /**
     * Inserts a table row with specified data.
     *
     * @param string|array|\Maho\Db\Select $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insertIgnore(string|array|\Maho\Db\Select $table, array $bind): int;

    /**
     * Deletes table rows based on a WHERE clause.
     *
     * @param  string|array|\Maho\Db\Select $table The table to update.
     * @param  string|array $where DELETE WHERE clause(s).
     * @return int          The number of affected rows.
     */
    public function delete(string|array|\Maho\Db\Select $table, string|array $where = ''): int;

    /**
     * Prepares and executes an SQL statement with bound data.
     *
     * @param  string|\Maho\Db\Select  $sql  The SQL statement with placeholders.
     *                      May be a string or \Maho\Db\Select.
     * @param  array|int|string|float  $bind An array of data or data itself to bind to the placeholders.
     */
    public function query(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): \Maho\Db\Statement\StatementInterface;

    /**
     * Executes a SQL statement(s)
     */
    public function multiQuery(string $sql): array;

    /**
     * Fetches all SQL result rows as a sequential array.
     * Uses the current fetchMode for the adapter.
     *
     * @param string|\Maho\Db\Select $sql  An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     * @param int|null $fetchMode Override current fetch mode.
     */
    public function fetchAll(string|\Maho\Db\Select $sql, array|int|string|float $bind = [], ?int $fetchMode = null): array;

    /**
     * Fetches the first row of the SQL result.
     * Uses the current fetchMode for the adapter.
     *
     * @param string|\Maho\Db\Select $sql An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     * @param int|null $fetchMode Override current fetch mode.
     */
    public function fetchRow(string|\Maho\Db\Select $sql, array|int|string|float $bind = [], ?int $fetchMode = null): array|false;

    /**
     * Fetches all SQL result rows as an associative array.
     *
     * The first column is the key, the entire row array is the
     * value.  You should construct the query to be sure that
     * the first column contains unique values, or else
     * rows with duplicate values in the first column will
     * overwrite previous data.
     *
     * @param string|\Maho\Db\Select $sql An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     */
    public function fetchAssoc(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): array;

    /**
     * Fetches the first column of all SQL result rows as an array.
     *
     * The first column in each row is used as the array key.
     *
     * @param string|\Maho\Db\Select $sql An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     */
    public function fetchCol(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): array;

    /**
     * Fetches all SQL result rows as an array of key-value pairs.
     *
     * The first column is the key, the second column is the
     * value.
     *
     * @param string|\Maho\Db\Select $sql An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     */
    public function fetchPairs(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): array;

    /**
     * Fetches the first column of the first row of the SQL result.
     *
     * @param string|\Maho\Db\Select $sql An SQL SELECT statement.
     * @param array|int|string|float $bind Data to bind into SELECT placeholders.
     */
    public function fetchOne(string|\Maho\Db\Select $sql, array|int|string|float $bind = []): mixed;

    /**
     * Safely quotes a value for an SQL statement.
     *
     * If an array is passed as the value, the array values are quoted
     * and then returned as a comma-separated string.
     *
     * @param \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value A single value to quote into the condition.
     * @param null|string|int $type The type of the given value e.g. Zend_Db::INT_TYPE, "INT"
     * @return string An SQL-safe quoted value (or string of separated values).
     */
    public function quote(\Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value, null|string|int $type = null): string;

    /**
     * Quotes a value and places into a piece of text at a placeholder.
     *
     * The placeholder is a question-mark; all placeholders will be replaced
     * with the quoted value.   For example:
     *
     * <code>
     * $text = "WHERE date < ?";
     * $date = "2005-01-02";
     * $safe = $sql->quoteInto($text, $date);
     * // $safe = "WHERE date < '2005-01-02'"
     * </code>
     *
     * @param \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value A single value to quote into the condition.
     * @param null|string|int $type The type of the given value e.g. Zend_Db::INT_TYPE, "INT"
     * @param int|null $count count of placeholders to replace
     * @return string An SQL-safe quoted value placed into the original text.
     */
    public function quoteInto(string $text, \Maho\Db\Select|\Maho\Db\Expr|array|null|int|string|float|bool $value, null|string|int $type = null, ?int $count = null): string;

    /**
     * Quotes an identifier.
     *
     * Accepts a string representing a qualified identifier. For Example:
     * <code>
     * $adapter->quoteIdentifier('myschema.mytable')
     * </code>
     * Returns: "myschema"."mytable"
     *
     * Or, an array of one or more identifiers that may form a qualified identifier:
     * <code>
     * $adapter->quoteIdentifier(array('myschema','my.table'))
     * </code>
     * Returns: "myschema"."my.table"
     *
     * The actual quote character surrounding the identifiers may vary depending on
     * the adapter.
     *
     * @param string|array|\Maho\Db\Expr $ident The identifier.
     * @param bool $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier(string|array|\Maho\Db\Expr $ident, bool $auto = false): string;

    /**
     * Quote a column identifier and alias.
     *
     * @param string|array|\Maho\Db\Expr $ident The identifier or expression.
     * @param string|null $alias An alias for the column.
     * @param bool $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteColumnAs(string|array|\Maho\Db\Expr $ident, ?string $alias, bool $auto = false): string;

    /**
     * Quote a table identifier and alias.
     *
     * @param string|array|\Maho\Db\Expr|\Maho\Db\Select $ident The identifier or expression.
     * @param string|null $alias An alias for the table.
     * @param bool $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quoteTableAs(string|array|\Maho\Db\Expr|\Maho\Db\Select $ident, ?string $alias = null, bool $auto = false): string;

    /**
     * Format Date to internal database date format
     */
    public function formatDate(int|string|\DateTime $date, bool $includeTime = true): \Maho\Db\Expr;

    /**
     * Run additional environment before setup
     */
    public function startSetup(): self;

    /**
     * Run additional environment after setup
     */
    public function endSetup(): self;

    public function setCacheAdapter(\Mage_Core_Model_Cache $adapter): self;

    /**
     * Allow DDL caching
     */
    public function allowDdlCache(): self;

    /**
     * Disallow DDL caching
     */
    public function disallowDdlCache(): self;

    /**
     * Reset cached DDL data from cache
     * if table name is null - reset all cached DDL data
     */
    public function resetDdlCache(?string $tableName = null, ?string $schemaName = null): self;

    /**
     * Save DDL data into cache
     */
    public function saveDdlCache(string $tableCacheKey, int $ddlType, mixed $data): self;

    /**
     * Load DDL data from cache
     * Return false if cache does not exists
     *
     * @param string $tableCacheKey the table cache key
     * @param int $ddlType          the DDL constant
     */
    public function loadDdlCache(string $tableCacheKey, int $ddlType): string|array|int|false;

    /**
     * Build SQL statement for condition
     *
     * If $condition integer or string - exact value will be filtered ('eq' condition)
     *
     * If $condition is null - IS NULL condition will be generated
     *
     * If $condition is array - one of the following structures is expected:
     * - array("from" => $fromValue, "to" => $toValue)
     * - array("eq" => $equalValue)
     * - array("neq" => $notEqualValue)
     * - array("like" => $likeValue)
     * - array("in" => array($inValues))
     * - array("nin" => array($notInValues))
     * - array("notnull" => $valueIsNotNull)
     * - array("null" => $valueIsNull)
     * - array("moreq" => $moreOrEqualValue)
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
    public function prepareSqlCondition(string $fieldName, int|string|array|null $condition): string;

    /**
     * Prepare value for save in column
     * Return converted to column data type value
     *
     * @param array $column     the column describe array
     */
    public function prepareColumnValue(array $column, mixed $value): mixed;

    /**
     * Generate fragment of SQL, that check condition and return true or false value
     *
     * @param string|\Maho\Db\Expr $true          true value
     * @param string|\Maho\Db\Expr $false         false value
     */
    public function getCheckSql(string $condition, string|\Maho\Db\Expr $true, string|\Maho\Db\Expr $false): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL, that check value against multiple condition cases
     * and return different result depends on them
     *
     * @param array $casesResults Cases and results
     * @param string|null $defaultValue value to use if value doesn't confirm to any cases
     */
    public function getCaseSql(string $valueName, array $casesResults, ?string $defaultValue = null): \Maho\Db\Expr;

    /**
     * Returns valid IFNULL expression
     *
     * @param string|int $value Applies when $expression is NULL
     */
    public function getIfNullSql(string $expression, string|int $value = '0'): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL for rounding a numeric value to specified precision.
     * Handles database-specific ROUND function requirements.
     */
    public function getRoundSql(string $expression, int $precision = 0): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL to cast a value to text/varchar for comparison.
     * This is useful for comparing integer columns with varchar columns.
     */
    public function getCastToTextSql(string $expression): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL, that combine together (concatenate) the results from data array
     * All arguments in data must be quoted
     *
     * @param string|null $separator concatenate with separator
     */
    public function getConcatSql(array $data, ?string $separator = null): \Maho\Db\Expr;

    /**
     * Returns the configuration variables in this adapter.
     */
    public function getConfig(): array;

    /**
     * Generate fragment of SQL that returns length of character string
     * The string argument must be quoted
     */
    public function getLengthSql(string $string): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the smallest
     * (minimum-valued) argument
     * All arguments in data must be quoted
     */
    public function getLeastSql(array $data): \Maho\Db\Expr;

    /**
     * Generate fragment of SQL, that compare with two or more arguments, and returns the largest
     * (maximum-valued) argument
     * All arguments in data must be quoted
     */
    public function getGreatestSql(array $data): \Maho\Db\Expr;

    /**
     * Add time values (intervals) to a date value
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    public function getDateAddSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr;

    /**
     * Subtract time values (intervals) to a date value
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    public function getDateSubSql(\Maho\Db\Expr|string $date, int|string $interval, string $unit): \Maho\Db\Expr;

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
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    public function getDateFormatSql(\Maho\Db\Expr|string $date, string $format): \Maho\Db\Expr;

    /**
     * Extract the date part of a date or datetime expression
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    public function getDatePartSql(\Maho\Db\Expr|string $date): \Maho\Db\Expr;

    /**
     * Prepare substring sql function
     *
     * @param \Maho\Db\Expr|string $stringExpression quoted field name or SQL statement
     */
    public function getSubstringSql(\Maho\Db\Expr|string $stringExpression, int|string|\Maho\Db\Expr $pos, int|string|\Maho\Db\Expr|null $len = null): \Maho\Db\Expr;

    /**
     * Prepare standard deviation sql function
     *
     * @param \Maho\Db\Expr|string $expressionField   quoted field name or SQL statement
     */
    public function getStandardDeviationSql(\Maho\Db\Expr|string $expressionField): \Maho\Db\Expr;

    /**
     * Extract part of a date
     *
     * @see INTERVAL_ constants for $unit
     *
     * @param \Maho\Db\Expr|string $date   quoted field name or SQL statement
     */
    public function getDateExtractSql(\Maho\Db\Expr|string $date, string $unit): \Maho\Db\Expr;

    /**
     * Get difference between two dates in days
     *
     * @param \Maho\Db\Expr|string $date1 First date (quoted field name or SQL statement)
     * @param \Maho\Db\Expr|string $date2 Second date (quoted field name or SQL statement)
     * @return \Maho\Db\Expr SQL expression that returns (date1 - date2) in days
     */
    public function getDateDiffSql(\Maho\Db\Expr|string $date1, \Maho\Db\Expr|string $date2): \Maho\Db\Expr;

    /**
     * Get SQL expression for days until next annual occurrence of a date
     *
     * This calculates the number of days from a reference date until the next
     * occurrence of an anniversary (e.g., birthday). Handles:
     * - Dates where the year is in the future (returns days to that date in current year)
     * - Dates where the anniversary has passed this year (returns days to next year)
     * - Leap year birthdays (Feb 29) in non-leap years (uses Feb 28)
     *
     * @param \Maho\Db\Expr|string $dateField The date field containing the anniversary (e.g., birth date)
     * @param string $referenceDate The reference date in 'Y-m-d' or 'Y-m-d H:i:s' format (usually today)
     * @return \Maho\Db\Expr SQL expression that returns days until next anniversary
     */
    public function getDaysUntilAnniversarySql(\Maho\Db\Expr|string $dateField, string $referenceDate): \Maho\Db\Expr;

    /**
     * Retrieve valid table name
     * Check table name length and allowed symbols
     */
    public function getTableName(string $tableName): string;

    /**
     * Retrieve valid index name
     * Check index name length and allowed symbols
     *
     * @param string|array $fields  the columns list
     */
    public function getIndexName(string $tableName, string|array $fields, string $indexType = ''): string;

    /**
     * Retrieve valid foreign key name
     * Check foreign key name length and allowed symbols
     */
    public function getForeignKeyName(string $priTableName, string $priColumnName, string $refTableName, string $refColumnName): string;

    /**
     * Stop updating indexes
     */
    public function disableTableKeys(string $tableName, ?string $schemaName = null): self;

    /**
     * Re-create missing indexes
     */
    public function enableTableKeys(string $tableName, ?string $schemaName = null): self;

    /**
     * Get insert from Select object query
     */
    public function insertFromSelect(\Maho\Db\Select $select, string $table, array $fields = [], bool|int $mode = false): string;

    /**
     * Get insert queries in array for insert by range with step parameter
     */
    public function selectsByRange(string $rangeField, \Maho\Db\Select $select, int $stepCount = 100): array;

    /**
     * Get update table query using select object for join and update
     */
    public function updateFromSelect(\Maho\Db\Select $select, string|array $table): string;

    /**
     * Get delete from select object query
     *
     * @param string $table the table name or alias used in select
     */
    public function deleteFromSelect(\Maho\Db\Select $select, string $table): string;

    /**
     * Return array of table(s) checksum as table name - checksum pairs
     */
    public function getTablesChecksum(array|string $tableNames, ?string $schemaName = null): array;

    /**
     * Check if the database support STRAIGHT JOIN
     */
    public function supportStraightJoin(): bool;

    /**
     * Adds order by random to select object
     * Possible using integer field for optimization
     */
    public function orderRand(\Maho\Db\Select $select, ?string $field = null): self;

    /**
     * Render SQL FOR UPDATE clause
     */
    public function forUpdate(string $sql): string;

    /**
     * Try to find installed primary key name, if not - format new one.
     *
     * @param string $tableName Table name
     * @return string Primary Key name
     */
    public function getPrimaryKeyName(string $tableName, ?string $schemaName = null): string;

    /**
     * Converts fetched blob into raw binary PHP data.
     * Some DB drivers return blobs as hex-coded strings, so we need to process them.
     */
    public function decodeVarbinary(mixed $value): mixed;

    /**
     * Returns date that fits into TYPE_DATETIME range and is suggested to act as default 'zero' value
     * for a column for current RDBMS.
     */
    public function getSuggestedZeroDate(): string;

    /**
     * Drop trigger
     */
    public function dropTrigger(string $triggerName): self;

    /**
     * Get adapter transaction level state. Return 0 if all transactions are complete
     */
    public function getTransactionLevel(): int;

    /**
     * Convert date format to unix time
     */
    public function getUnixTimestamp(string|\Maho\Db\Expr $date): \Maho\Db\Expr;

    /**
     * Convert unix time to date format
     */
    public function fromUnixtime(int|\Maho\Db\Expr $timestamp): \Maho\Db\Expr;

    /**
     * Change table auto increment value
     */
    public function changeTableAutoIncrement(string $tableName, int $increment, ?string $schemaName = null): \Maho\Db\Statement\StatementInterface;

    /**
     * Create new table from provided select statement
     */
    public function createTableFromSelect(string $tableName, \Maho\Db\Select $select, bool $temporary = false): void;

    /**
     * Retrieve the list of all tables in the database
     *
     * @return string[] List of table names
     */
    public function listTables(?string $schemaName = null): array;

    /**
     * Modify table columns, foreign keys, comments and engine
     */
    public function modifyTables(array $tables): self;

    /**
     * Retrieve last inserted ID
     */
    public function lastInsertId(?string $tableName = null, ?string $primaryKey = null): string|int;

    /**
     * Acquire a named lock
     *
     * @param string $lockName The name of the lock
     * @param int $timeout Timeout in seconds to wait for the lock
     * @return bool True if lock was acquired, false otherwise
     */
    public function getLock(string $lockName, int $timeout = 0): bool;

    /**
     * Release a named lock
     *
     * @param string $lockName The name of the lock
     * @return bool True if lock was released, false otherwise
     */
    public function releaseLock(string $lockName): bool;

    /**
     * Check if a named lock is currently held
     *
     * @param string $lockName The name of the lock
     * @return bool True if lock is held, false otherwise
     */
    public function isLocked(string $lockName): bool;

    /**
     * Get SQL expression for timestamp difference in seconds
     *
     * Returns the difference between two timestamps in seconds (end - start).
     * This is database-agnostic and handles the syntax differences between MySQL and PostgreSQL.
     *
     * @param string $startTimestamp The start timestamp column or expression
     * @param string $endTimestamp The end timestamp column or expression
     */
    public function getTimestampDiffExpr(string $startTimestamp, string $endTimestamp): \Maho\Db\Expr;

    /**
     * Get SQL expression for concatenating grouped values
     *
     * Returns a SQL expression that concatenates values from grouped rows.
     * This is database-agnostic and handles the syntax differences between MySQL (GROUP_CONCAT)
     * and PostgreSQL (STRING_AGG).
     *
     * @param string $expression The column or expression to concatenate
     * @param string $separator The separator to use between values (default: ',')
     */
    public function getGroupConcatExpr(string $expression, string $separator = ','): \Maho\Db\Expr;

    /**
     * Get SQL expression for FIND_IN_SET functionality
     *
     * Returns a SQL expression that checks if a value exists in a comma-separated list.
     * This is database-agnostic and handles the syntax differences between MySQL (FIND_IN_SET)
     * and PostgreSQL (ANY with string_to_array).
     *
     * @param string $needle The value to search for (can be a ? placeholder)
     * @param string $haystack The column containing comma-separated values
     */
    public function getFindInSetExpr(string $needle, string $haystack): \Maho\Db\Expr;

    /**
     * Generate a database-agnostic Unix timestamp expression
     *
     * MySQL: UNIX_TIMESTAMP($timestamp) or UNIX_TIMESTAMP() for current time
     * PostgreSQL: EXTRACT(EPOCH FROM $timestamp)::bigint
     *
     * @param string|null $timestamp Optional timestamp expression (defaults to current time)
     */
    public function getUnixTimestampExpr(?string $timestamp = null): \Maho\Db\Expr;

    /**
     * Extract a scalar value from a JSON column at a given path
     *
     * Returns a SQL expression that extracts and unquotes a value from JSON data.
     * The path uses MySQL/SQLite dot notation (e.g., '$.name', '$.address.city').
     * Platform adapters translate this internally for their native syntax.
     *
     * @param string $column The column name containing JSON data
     * @param string $path The JSON path (e.g., '$.name', '$.address.city')
     */
    public function getJsonExtractExpr(string $column, string $path): \Maho\Db\Expr;

    /**
     * Search for a string value within a JSON column
     *
     * Returns a boolean SQL expression that checks if a value exists at the given path.
     * Supports '$**' recursive wildcard for deep search (e.g., '$**.attribute').
     * The value parameter is a plain PHP string — quoting is handled internally.
     *
     * @param string $column The column name containing JSON data
     * @param string $value The string value to search for
     * @param string $path The JSON path, supports '$**' recursive wildcard
     */
    public function getJsonSearchExpr(string $column, string $value, string $path): \Maho\Db\Expr;

    /**
     * Check if a JSON column contains a specific JSON value
     *
     * Returns a boolean SQL expression. The value parameter must be a JSON-encoded
     * string (e.g., '"hello"', '42', 'true'). Optional path scopes the check.
     * Wildcard paths ('$**') are not supported — use getJsonSearchExpr() instead.
     *
     * @param string $column The column name containing JSON data
     * @param string $value A JSON-encoded value (e.g., '"hello"', '42')
     * @param string|null $path Optional JSON path to scope the check
     */
    public function getJsonContainsExpr(string $column, string $value, ?string $path = null): \Maho\Db\Expr;
}
