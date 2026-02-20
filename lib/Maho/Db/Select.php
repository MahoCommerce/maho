<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Db;

class Select
{
    // Query part constants
    public const DISTINCT       = 'distinct';
    public const COLUMNS        = 'columns';
    public const FROM           = 'from';
    public const UNION          = 'union';
    public const WHERE          = 'where';
    public const GROUP          = 'group';
    public const HAVING         = 'having';
    public const ORDER          = 'order';
    public const LIMIT_COUNT    = 'limitcount';
    public const LIMIT_OFFSET   = 'limitoffset';
    public const FOR_UPDATE     = 'forupdate';
    public const STRAIGHT_JOIN  = 'straightjoin';

    // Join type constants
    public const INNER_JOIN     = 'inner join';
    public const LEFT_JOIN      = 'left join';
    public const RIGHT_JOIN     = 'right join';

    public const SQL_WILDCARD   = '*';
    public const SQL_SELECT     = 'SELECT';
    public const SQL_FROM       = 'FROM';
    public const SQL_WHERE      = 'WHERE';
    public const SQL_DISTINCT   = 'DISTINCT';
    public const SQL_GROUP_BY   = 'GROUP BY';
    public const SQL_ORDER_BY   = 'ORDER BY';
    public const SQL_HAVING     = 'HAVING';
    public const SQL_FOR_UPDATE = 'FOR UPDATE';
    public const SQL_AND        = 'AND';
    public const SQL_AS         = 'AS';
    public const SQL_OR         = 'OR';
    public const SQL_ON         = 'ON';
    public const SQL_ASC        = 'ASC';
    public const SQL_DESC       = 'DESC';

    public const SQL_STRAIGHT_JOIN = 'STRAIGHT_JOIN';
    public const SQL_UNION_ALL = 'ALL';

    public const TYPE_CONDITION = 'TYPE_CONDITION';

    protected Adapter\AdapterInterface $_adapter;

    /**
     * The component parts of a SELECT statement
     * Maintained for backward compatibility with getPart()/setPart()
     */
    protected array $_parts = [
        self::DISTINCT     => false,
        self::COLUMNS      => [],
        self::FROM         => [],
        self::UNION        => [],
        self::WHERE        => [],
        self::GROUP        => [],
        self::HAVING       => [],
        self::ORDER        => [],
        self::LIMIT_COUNT  => null,
        self::LIMIT_OFFSET => null,
        self::FOR_UPDATE   => false,
        self::STRAIGHT_JOIN => false,
    ];

    /**
     * Tracks which columns are being select from each table and join
     */
    protected array $_tableCols = [];

    /**
     * Flag to force explicit column aliases for UNION compatibility
     * SQLite requires ORDER BY columns to exactly match result set column names
     */
    protected bool $_forceExplicitAliases = false;

    public function __construct(Adapter\AdapterInterface $adapter)
    {
        $this->_adapter = $adapter;
    }

    public function getAdapter(): Adapter\AdapterInterface
    {
        return $this->_adapter;
    }

    /**
     * Makes the query SELECT DISTINCT.
     *
     * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
     */
    public function distinct(bool $flag = true): self
    {
        $this->_parts[self::DISTINCT] = (bool) $flag;
        return $this;
    }

    /**
     * Adds a FROM table and optional columns to the query.
     */
    public function from(array|string|Expr|Select $name, array|string|Expr $cols = '*', ?string $schema = null): self
    {
        // Allow empty array for selecting expressions without a table (e.g., SELECT 1)
        if (is_array($name) && empty($name)) {
            // Just add the columns without a table
            if ($cols) {
                $this->columns($cols);
            }
            return $this;
        }

        return $this->joinInner($name, null, $cols, $schema);
    }

    /**
     * Adds a JOIN table and columns to the query.
     */
    public function join(array|string|Expr|Select $name, ?string $cond, array|string|Expr $cols = self::SQL_WILDCARD, ?string $schema = null): self
    {
        return $this->joinInner($name, $cond, $cols, $schema);
    }

    /**
     * Add an INNER JOIN table and columns to the query
     */
    public function joinInner(array|string|Expr|Select $name, ?string $cond, array|string|Expr $cols = self::SQL_WILDCARD, ?string $schema = null): self
    {
        return $this->_join(self::INNER_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a LEFT OUTER JOIN table and columns to the query
     */
    public function joinLeft(array|string|Expr|Select $name, string $cond, array|string|Expr $cols = self::SQL_WILDCARD, ?string $schema = null): self
    {
        return $this->_join(self::LEFT_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a RIGHT OUTER JOIN table and columns to the query
     */
    public function joinRight(array|string|Expr|Select $name, string $cond, array|string|Expr $cols = self::SQL_WILDCARD, ?string $schema = null): self
    {
        return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols, $schema);
    }


    /**
     * Populate the {@link $_parts} 'join' key
     */
    protected function _join(string $type, array|string|Expr|Select $name, ?string $cond, array|string|Expr $cols, ?string $schema = null): self
    {
        if (!in_array($type, [self::INNER_JOIN, self::LEFT_JOIN, self::RIGHT_JOIN])) {
            throw new Exception("Invalid join type '$type'");
        }

        if (empty($name)) {
            throw new Exception('No table specified for join');
        }

        // Get table name and correlation name
        $correlationName = '';
        $tableName = '';
        if (is_array($name)) {
            // Must be array($correlationName => $tableName) or array($ident, $correlationName)
            foreach ($name as $_correlationName => $_tableName) {
                if (is_string($_correlationName)) {
                    // We assume the key is the correlation name (or alias)
                    $tableName = $_tableName;
                    $correlationName = $_correlationName;
                } else {
                    // We assume the first element is the table name
                    $tableName = $_tableName;
                    $correlationName = $this->_uniqueCorrelation($tableName);
                }
                break;
            }
        } else {
            // $name is a string, Expr, or Select
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation($tableName);
        }

        // Schema
        if ($schema !== null) {
            $tableName = [$schema, $tableName];
        }

        // Store the join information
        $this->_parts[self::FROM][$correlationName] = [
            'joinType'      => $type,
            'schema'        => $schema,
            'tableName'     => $tableName,
            'joinCondition' => $cond,
        ];

        // Add to the columns to be selected
        $this->_tableCols($correlationName, $cols);

        return $this;
    }

    /**
     * Generate a unique correlation name
     *
     * @param string|array|Expr|Select $name A qualified identifier.
     * @return string A unique correlation name.
     */
    protected function _uniqueCorrelation($name)
    {
        if (is_array($name)) {
            $name = end($name);
        }

        // Extract just the table name if it includes a Expr or Select (subquery)
        if ($name instanceof Expr || $name instanceof Select) {
            $name = (string) $name;
        }

        // Strip schema qualification
        if (($pos = strrpos($name, '.')) !== false) {
            $name = substr($name, $pos + 1);
        }

        for ($i = 2; array_key_exists($name, $this->_parts[self::FROM]); ++$i) {
            $name = $name . '_' . (string) $i;
        }

        return $name;
    }

    /**
     * Add columns to the query
     *
     * @param  string $correlationName Correlation name or table name
     * @param  array|string $cols The columns to add
     * @param  string $afterCorrelationName OPTIONAL place the columns after this correlation
     * @return void
     */
    protected function _tableCols($correlationName, $cols, $afterCorrelationName = null)
    {
        if (!is_array($cols)) {
            $cols = [$cols];
        }

        if ($correlationName == null) {
            $correlationName = '';
        }

        $columnValues = [];

        foreach ($cols as $alias => $col) {
            // Skip empty columns (e.g., when join has '' as column spec)
            if ($col === '' || $col === null) {
                continue;
            }

            $currentCorrelationName = $correlationName;
            // Preserve Expr objects as-is (don't parse them)
            if ($col instanceof Expr) {
                // Keep Expr as-is, use empty correlation name so it's not modified
                $columnValues[] = ['', $col, is_string($alias) ? $alias : null];
            } elseif ($col instanceof Select) {
                // Convert Select to Expr
                $col = new Expr(sprintf('(%s)', $col->assemble()));
                $columnValues[] = ['', $col, is_string($alias) ? $alias : null];
            } elseif (is_string($col)) {
                // Check for a column matching "<column> AS <alias>" and extract the alias name
                if (preg_match('/^(.+)\s+' . self::SQL_AS . '\s+(.+)$/i', $col, $m)) {
                    $col = $m[1];
                    $alias = $m[2];
                }
                // Check for columns from joined tables (but not functions with parentheses)
                if (preg_match('/(.+)\.(.+)/', $col, $m) && !str_contains($col, '(')) {
                    $currentCorrelationName = $m[1];
                    $col = $m[2];
                }
                // Check if it's a SQL function (contains parentheses) - wrap in Expr
                if (str_contains($col, '(') && str_contains($col, ')')) {
                    $col = new Expr($col);
                }
                $columnValues[] = [$currentCorrelationName, $col, is_string($alias) ? $alias : null];
            } else {
                // Unknown type, add as-is
                $columnValues[] = [$currentCorrelationName, $col, is_string($alias) ? $alias : null];
            }
        }

        if ($columnValues) {
            if ($afterCorrelationName == null || !in_array($afterCorrelationName, $this->_tableCols)) {
                $this->_parts[self::COLUMNS] = array_merge($this->_parts[self::COLUMNS], $columnValues);
            } else {
                $tmpColumns = $this->_parts[self::COLUMNS];
                $this->_parts[self::COLUMNS] = [];
                foreach ($tmpColumns as $colEntry) {
                    $this->_parts[self::COLUMNS][] = $colEntry;
                    if ($colEntry[0] == $afterCorrelationName) {
                        foreach ($columnValues as $columnValue) {
                            $this->_parts[self::COLUMNS][] = $columnValue;
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds a WHERE condition to the query by AND
     */
    public function where(string $cond, mixed $value = null, mixed $type = null): self
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);
        return $this;
    }

    /**
     * Adds a WHERE condition to the query by OR
     */
    public function orWhere(string $cond, mixed $value = null, mixed $type = null): self
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);
        return $this;
    }

    /**
     * Internal function for creating WHERE conditions
     */
    protected function _where(string $cond, mixed $value = null, mixed $type = null, bool $bool = true): array
    {
        if (is_null($value) && is_null($type)) {
            $value = '';
        }

        // Additional internal type used for really null value
        if ((string) $type === self::TYPE_CONDITION) {
            $type = null;
        }

        if (is_array($value)) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
            $value = null;
        }

        if ($value !== null) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
        }

        $cond = '(' . $cond . ')';

        if ($bool === true) {
            return [self::SQL_AND => $cond];
        }
        return [self::SQL_OR => $cond];
    }

    /**
     * Adds grouping to the query.
     *
     * @param  array|string|Expr $spec The column(s) to group by.
     */
    public function group(array|string|Expr $spec): self
    {
        if (!is_array($spec)) {
            $spec = [$spec];
        }

        foreach ($spec as $val) {
            $this->_parts[self::GROUP][] = $val;
        }

        return $this;
    }

    /**
     * Adds a HAVING condition to the query by AND.
     *
     * @param string $cond The HAVING condition.
     * @param mixed $value OPTIONAL A single value to quote into the condition.
     * @param int $type OPTIONAL The type of the given value
     */
    public function having(string $cond, mixed $value = null, mixed $type = null): self
    {
        if ($value !== null) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
        }

        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_AND . " ($cond)";
        } else {
            $this->_parts[self::HAVING][] = "($cond)";
        }

        return $this;
    }

    /**
     * Adds a HAVING condition to the query by OR.
     *
     * @param string $cond The HAVING condition.
     * @param mixed $value OPTIONAL A single value to quote into the condition.
     * @param int $type OPTIONAL The type of the given value
     */
    public function orHaving(string $cond, mixed $value = null, mixed $type = null): self
    {
        if ($value !== null) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
        }

        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_OR . " ($cond)";
        } else {
            $this->_parts[self::HAVING][] = "($cond)";
        }

        return $this;
    }

    /**
     * Expand column aliases in HAVING clause to their expressions.
     *
     * PostgreSQL doesn't allow column aliases in HAVING clauses (e.g., HAVING cnt > 1
     * where cnt is an alias for COUNT(*)). This method replaces aliases with their
     * actual expressions.
     */
    protected function _expandHavingAliases(string $having): string
    {
        // Build a map of aliases to their SQL expressions
        $aliasMap = [];
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if ($alias !== null) {
                // Get the SQL expression for this column
                if ($column instanceof Expr) {
                    $expr = $column->__toString();
                } else {
                    // For regular columns, include the table correlation if present
                    if ($correlationName) {
                        $expr = $this->_adapter->quoteIdentifier($correlationName) . '.' .
                                $this->_adapter->quoteIdentifier($column);
                    } else {
                        $expr = $this->_adapter->quoteIdentifier($column);
                    }
                }
                $aliasMap[$alias] = $expr;
            }
        }

        // Replace aliases with expressions using word boundaries
        foreach ($aliasMap as $alias => $expr) {
            // Use word boundaries to avoid replacing partial matches
            // e.g., "cnt" should not match "accounts"
            $having = preg_replace(
                '/\b' . preg_quote($alias, '/') . '\b/',
                $expr,
                $having,
            );
        }

        return $having;
    }

    /**
     * Adds a row order to the query.
     *
     * @param array|string|Expr $spec The column(s) and direction to order by.
     */
    public function order(array|string|Expr $spec): self
    {
        if (!is_array($spec)) {
            $spec = [$spec];
        }

        // force 'ASC' or 'DESC' on each order spec, default is ASC.
        foreach ($spec as $val) {
            if ($val instanceof Expr) {
                $expr = $val->__toString();
                if (empty($expr)) {
                    continue;
                }
                $this->_parts[self::ORDER][] = $val;
            } else {
                if (empty($val)) {
                    continue;
                }
                $direction = self::SQL_ASC;
                if (preg_match('/(.*\W)(' . self::SQL_ASC . '|' . self::SQL_DESC . ')\b/si', $val, $matches)) {
                    $val = trim($matches[1]);
                    $direction = $matches[2];
                }
                $this->_parts[self::ORDER][] = [$val, $direction];
            }
        }

        return $this;
    }

    /**
     * Sets a limit count and offset to the query.
     *
     * @param int $count OPTIONAL The number of rows to return.
     * @param int $offset OPTIONAL Start returning after this many rows.
     * @return $this
     */
    public function limit(?int $count = null, ?int $offset = null): self
    {
        if ($count === null) {
            $this->_parts[self::LIMIT_COUNT] = null;
        } else {
            $this->_parts[self::LIMIT_COUNT] = (int) $count;
        }

        if ($offset === null) {
            $this->_parts[self::LIMIT_OFFSET] = null;
        } else {
            $this->_parts[self::LIMIT_OFFSET] = (int) $offset;
        }

        return $this;
    }

    /**
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     * @param int $rowCount Use this many rows per page.
     * @return $this
     */
    public function limitPage(int $page, int $rowCount): self
    {
        $page     = ($page > 0) ? $page : 1;
        $rowCount = ($rowCount > 0) ? $rowCount : 1;
        $this->_parts[self::LIMIT_COUNT]  = (int) $rowCount;
        $this->_parts[self::LIMIT_OFFSET] = (int) $rowCount * ($page - 1);
        return $this;
    }

    /**
     * Makes the query SELECT FOR UPDATE.
     *
     * @param bool $flag Whether or not the SELECT is FOR UPDATE (default true).
     * @return $this
     */
    public function forUpdate(bool $flag = true): self
    {
        $this->_parts[self::FOR_UPDATE] = (bool) $flag;
        return $this;
    }

    /**
     * Get part of the structured information for the current query.
     */
    public function getPart(string $part): mixed
    {
        $part = strtolower($part);
        if (!array_key_exists($part, $this->_parts)) {
            throw new Exception("Invalid Select part '$part'");
        }
        return $this->_parts[$part];
    }

    /**
     * Modify (hack) part of the structured information for the current query
     */
    public function setPart(string $part, mixed $value): self
    {
        $part = strtolower($part);
        if (!array_key_exists($part, $this->_parts)) {
            throw new Exception("Invalid Select part '$part'");
        }
        $this->_parts[$part] = $value;
        return $this;
    }

    /**
     * Adds columns to the current query.
     *
     * @param array|string $cols The columns to select.
     * @param string $correlationName Correlation name or table alias
     * @return $this
     */
    public function columns(array|string $cols = '*', ?string $correlationName = null): self
    {
        if ($correlationName === null && count($this->_parts[self::FROM])) {
            $correlationNameKeys = array_keys($this->_parts[self::FROM]);
            $correlationName = current($correlationNameKeys);
        }

        if (!is_array($cols)) {
            $cols = [$cols];
        }

        foreach ($cols as $alias => $col) {
            if ($col instanceof Select) {
                // Convert subselects to expressions
                $cols[$alias] = new Expr(sprintf('(%s)', $col->assemble()));
            }
        }

        $this->_tableCols($correlationName, $cols);

        return $this;
    }

    /**
     * Clears parts of the SELECT object, or all of it.
     */
    public function reset(?string $part = null): self
    {
        if ($part == null) {
            $this->_parts = [
                self::DISTINCT     => false,
                self::COLUMNS      => [],
                self::FROM         => [],
                self::UNION        => [],
                self::WHERE        => [],
                self::GROUP        => [],
                self::HAVING       => [],
                self::ORDER        => [],
                self::LIMIT_COUNT  => null,
                self::LIMIT_OFFSET => null,
                self::FOR_UPDATE   => false,
                self::STRAIGHT_JOIN => false,
            ];
        } elseif (array_key_exists($part, $this->_parts)) {
            $this->_parts[$part] = match ($part) {
                self::DISTINCT, self::FOR_UPDATE, self::STRAIGHT_JOIN => false,
                self::GROUP, self::HAVING, self::WHERE, self::COLUMNS, self::FROM, self::UNION, self::ORDER => [],
                default => null,
            };
        }
        return $this;
    }

    /**
     * Use a STRAIGHT_JOIN for the SQL Select
     *
     * @param bool $flag Whether or not the SELECT use STRAIGHT_JOIN (default true).
     * @return $this
     */
    public function useStraightJoin(bool $flag = true): self
    {
        $this->_parts[self::STRAIGHT_JOIN] = (bool) $flag;
        return $this;
    }

    /**
     * Cross Table Update From Current select
     */
    public function crossUpdateFromSelect(string|array $table): string
    {
        return $this->getAdapter()->updateFromSelect($this, $table);
    }

    /**
     * Insert to table from current select
     */
    public function insertFromSelect(string $tableName, array $fields = [], bool $onDuplicate = true): string
    {
        $mode = $onDuplicate ? Adapter\AdapterInterface::INSERT_ON_DUPLICATE : false;
        return $this->getAdapter()->insertFromSelect($this, $tableName, $fields, $mode);
    }

    /**
     * Generate INSERT IGNORE query to the table from current select
     */
    public function insertIgnoreFromSelect(string $tableName, array $fields = []): string
    {
        return $this->getAdapter()
            ->insertFromSelect($this, $tableName, $fields, Adapter\AdapterInterface::INSERT_IGNORE);
    }

    /**
     * Retrieve DELETE query from select
     */
    public function deleteFromSelect(string $table): string
    {
        return $this->getAdapter()->deleteFromSelect($this, $table);
    }

    /**
     * Adds the random order to query
     */
    public function orderRand(?string $field = null): self
    {
        $this->_adapter->orderRand($this, $field);
        return $this;
    }

    /**
     * Add EXISTS clause
     */
    public function exists(Select $select, string $joinCondition, bool $isExists = true): self
    {
        if ($isExists) {
            $exists = 'EXISTS (%s)';
        } else {
            $exists = 'NOT EXISTS (%s)';
        }
        $select->reset(self::COLUMNS)
            ->columns([new Expr('1')])
            ->where($joinCondition);

        $exists = sprintf($exists, $select->assemble());

        $this->where($exists);
        return $this;
    }

    /**
     * Reset unused LEFT JOIN(s)
     */
    public function resetJoinLeft(): self
    {
        foreach ($this->_parts[self::FROM] as $tableId => $tableProp) {
            if ($tableProp['joinType'] == self::LEFT_JOIN) {
                $useJoin = false;
                foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
                    [$correlationName, $column] = $columnEntry;
                    if ($column instanceof Expr) {
                        if ($this->_findTableInCond($tableId, $column)
                            || $this->_findTableInCond($tableProp['tableName'], $column)
                        ) {
                            $useJoin = true;
                        }
                    } else {
                        if ($correlationName == $tableId) {
                            $useJoin = true;
                        }
                    }
                }
                foreach ($this->_parts[self::WHERE] as $where) {
                    $whereCond = is_array($where) ? current($where) : $where;
                    if ($this->_findTableInCond($tableId, $whereCond)
                        || $this->_findTableInCond($tableProp['tableName'], $whereCond)
                    ) {
                        $useJoin = true;
                    }
                }

                $joinUseInCond  = $useJoin;
                $joinInTables   = [];

                foreach ($this->_parts[self::FROM] as $tableCorrelationName => $table) {
                    if ($tableCorrelationName == $tableId) {
                        continue;
                    }
                    if (!empty($table['joinCondition'])) {
                        if ($this->_findTableInCond($tableId, $table['joinCondition'])
                            || $this->_findTableInCond($tableProp['tableName'], $table['joinCondition'])
                        ) {
                            $useJoin = true;
                            $joinInTables[] = $tableCorrelationName;
                        }
                    }
                }

                if (!$useJoin) {
                    unset($this->_parts[self::FROM][$tableId]);
                } else {
                    $this->_parts[self::FROM][$tableId]['useInCond'] = $joinUseInCond;
                    $this->_parts[self::FROM][$tableId]['joinInTables'] = $joinInTables;
                }
            }
        }

        $this->_resetJoinLeft();

        return $this;
    }

    /**
     * Validate LEFT joins, and remove it if not exists
     *
     * @return $this
     */
    protected function _resetJoinLeft()
    {
        foreach ($this->_parts[self::FROM] as $tableId => $tableProp) {
            if ($tableProp['joinType'] == self::LEFT_JOIN) {
                if (isset($tableProp['useInCond']) && $tableProp['useInCond']) {
                    continue;
                }

                $used = false;
                if (isset($tableProp['joinInTables'])) {
                    foreach ($tableProp['joinInTables'] as $table) {
                        if (isset($this->_parts[self::FROM][$table])) {
                            $used = true;
                            break;
                        }
                    }
                }

                if (!$used) {
                    unset($this->_parts[self::FROM][$tableId]);
                    return $this->_resetJoinLeft();
                }
            }
        }

        return $this;
    }

    /**
     * Find table name in condition (where, column)
     *
     * @param string $table
     * @param string|Expr $cond
     * @return bool
     */
    protected function _findTableInCond($table, $cond)
    {
        $cond  = (string) $cond;
        $quote = '`';

        if (str_contains($cond, $quote . $table . $quote . '.')) {
            return true;
        }

        $position = 0;
        $result   = 0;
        $needle   = [];
        while (is_integer($result)) {
            $result = strpos($cond, $table . '.', $position);
            if (is_integer($result)) {
                $needle[] = $result;
                $position = ($result + strlen($table) + 1);
            }
        }

        if (!$needle) {
            return false;
        }

        foreach ($needle as $position) {
            if ($position == 0) {
                return true;
            }
            if (!preg_match('#[a-z0-9_]#is', substr($cond, $position - 1, 1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts this object to an SQL SELECT string.
     */
    public function assemble(): string
    {
        // Create QueryBuilder on-demand (only when assembling query)
        $connection = $this->_adapter->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        // Build columns using our formatting logic, then pass directly to QueryBuilder
        // QueryBuilder accepts raw SQL fragment strings and joins them with commas
        if (!$this->_parts[self::COLUMNS]) {
            $queryBuilder->select('*');
        } else {
            // For UNION queries, we need explicit AS aliases for all columns
            // SQLite requires ORDER BY columns to exactly match result set column names,
            // but "table"."column" without AS doesn't work - SQLite can't resolve it
            $needExplicitAliases = !empty($this->_parts[self::UNION]) || $this->_forceExplicitAliases;
            $columns = [];
            foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
                [$correlationName, $column, $alias] = $columnEntry;
                if ($column instanceof Expr) {
                    $colStr = $this->_adapter->quoteColumnAs($column, $alias, true);
                } elseif ($column == self::SQL_WILDCARD) {
                    $colStr = ($correlationName ? $this->_adapter->quoteIdentifier($correlationName) . '.' : '') . self::SQL_WILDCARD;
                } else {
                    // For UNION queries without explicit aliases, add the column name as alias
                    // This ensures SQLite can match ORDER BY column names to result columns
                    $effectiveAlias = $alias;
                    if ($needExplicitAliases && $alias === null && $correlationName && is_string($column)) {
                        $effectiveAlias = $column;
                    }
                    $colStr = $this->_adapter->quoteColumnAs([$correlationName, $column], $effectiveAlias, true);
                }
                $columns[] = $colStr;
            }
            // Pass formatted columns directly to QueryBuilder - it will join them with commas
            $queryBuilder->select(...$columns);
        }

        // Apply DISTINCT if needed
        if ($this->_parts[self::DISTINCT]) {
            $queryBuilder->distinct();
        }

        // Build FROM/JOIN clauses
        if ($this->_parts[self::FROM]) {
            $isFirstTable = true;
            $fromAlias = null;
            foreach ($this->_parts[self::FROM] as $correlationName => $table) {
                // Convert table name to string
                $tableNameStr = $table['tableName'];
                if (is_array($tableNameStr)) {
                    $tableNameStr = implode('.', array_map(fn($part) => $this->_adapter->quoteIdentifier($part), $tableNameStr));
                } elseif ($tableNameStr instanceof Expr) {
                    $tableNameStr = (string) $tableNameStr;
                } elseif ($tableNameStr instanceof Select) {
                    $tableNameStr = '(' . $tableNameStr->assemble() . ')';
                } else {
                    $tableNameStr = $this->_adapter->quoteIdentifier($tableNameStr);
                }

                // Quote correlation name to handle reserved keywords (e.g., 'order', 'group')
                $quotedCorrelationName = $this->_adapter->quoteIdentifier($correlationName);

                if ($isFirstTable) {
                    // First table uses from()
                    $queryBuilder->from($tableNameStr, $quotedCorrelationName);
                    $fromAlias = $quotedCorrelationName;
                    $isFirstTable = false;
                } else {
                    // Subsequent tables use join methods
                    $joinType = strtolower($table['joinType']);
                    $joinCondition = $table['joinCondition'] ?? '';

                    // Empty join condition means CROSS JOIN (no ON clause)
                    // DBAL doesn't support CROSS JOIN, so we use "1=1" condition which is equivalent
                    if (empty($joinCondition)) {
                        $joinCondition = '1=1';
                    }

                    match ($joinType) {
                        self::INNER_JOIN => $queryBuilder->innerJoin(
                            $fromAlias,
                            $tableNameStr,
                            $quotedCorrelationName,
                            $joinCondition,
                        ),
                        self::LEFT_JOIN => $queryBuilder->leftJoin(
                            $fromAlias,
                            $tableNameStr,
                            $quotedCorrelationName,
                            $joinCondition,
                        ),
                        self::RIGHT_JOIN => $queryBuilder->rightJoin(
                            $fromAlias,
                            $tableNameStr,
                            $quotedCorrelationName,
                            $joinCondition,
                        ),
                        default => throw new Exception("Join type '$joinType' not supported in QueryBuilder yet"),
                    };
                }
            }
        }

        // Build WHERE clause
        if ($this->_parts[self::WHERE]) {
            $isFirst = true;
            foreach ($this->_parts[self::WHERE] as $term) {
                if (is_array($term)) {
                    foreach ($term as $type => $cond) {
                        // Skip empty conditions
                        if (empty(trim($cond))) {
                            continue;
                        }
                        if ($isFirst) {
                            $queryBuilder->where($cond);
                            $isFirst = false;
                        } elseif ($type === self::SQL_OR) {
                            $queryBuilder->orWhere($cond);
                        } else {
                            $queryBuilder->andWhere($cond);
                        }
                    }
                } else {
                    // Skip empty conditions
                    if (empty(trim($term))) {
                        continue;
                    }
                    if ($isFirst) {
                        $queryBuilder->where($term);
                        $isFirst = false;
                    } else {
                        $queryBuilder->andWhere($term);
                    }
                }
            }
        }

        // Build GROUP BY clause
        if ($this->_parts[self::GROUP]) {
            $groupBy = [];
            foreach ($this->_parts[self::GROUP] as $term) {
                if ($term instanceof Expr) {
                    $groupBy[] = $term->__toString();
                } else {
                    $groupBy[] = $this->_adapter->quoteIdentifier($term, true);
                }
            }
            $queryBuilder->groupBy(...$groupBy);
        }

        // Build HAVING clause
        if ($this->_parts[self::HAVING]) {
            $havingStr = implode(' ', $this->_parts[self::HAVING]);
            // Expand column aliases to their expressions for PostgreSQL compatibility
            // (PostgreSQL doesn't allow column aliases in HAVING clauses)
            $havingStr = $this->_expandHavingAliases($havingStr);
            $queryBuilder->having($havingStr);
        }

        // Build ORDER BY clause (but not if we have UNION - it goes after UNION for SQLite compatibility)
        if ($this->_parts[self::ORDER] && !$this->_parts[self::UNION]) {
            foreach ($this->_parts[self::ORDER] as $term) {
                if ($term instanceof Expr) {
                    $queryBuilder->addOrderBy($term->__toString());
                } elseif (is_array($term)) {
                    $queryBuilder->addOrderBy($term[0], $term[1]);
                }
            }
        }

        // Build LIMIT/OFFSET (but not if we have UNION - it goes after UNION)
        if (!$this->_parts[self::UNION]) {
            if ($this->_parts[self::LIMIT_COUNT] !== null) {
                $queryBuilder->setMaxResults((int) $this->_parts[self::LIMIT_COUNT]);
            }
            if ($this->_parts[self::LIMIT_OFFSET] !== null) {
                $queryBuilder->setFirstResult((int) $this->_parts[self::LIMIT_OFFSET]);
            }
        }

        // Build UNION - DBAL requires converting base SELECT to first union() call
        if ($this->_parts[self::UNION]) {
            // Check if we have a base query (non-empty SELECT with FROM clause)
            $hasBaseQuery = !empty($this->_parts[self::FROM]);

            if ($hasBaseQuery) {
                // Get the base SELECT SQL - it now has correct columns from QueryBuilder
                $baseSQL = $queryBuilder->getSQL();

                // Create fresh QueryBuilder for UNION (reuse connection from earlier)
                $queryBuilder = $connection->createQueryBuilder();

                // Add base SELECT as first union part (union() always uses DISTINCT by default)
                $queryBuilder->union($baseSQL);
            } else {
                // No base query - create fresh QueryBuilder and use first UNION part as base
                $queryBuilder = $connection->createQueryBuilder();
            }

            // Add all UNION parts
            $isFirstUnion = !$hasBaseQuery;
            foreach ($this->_parts[self::UNION] as $union) {
                $target = $union['target'];
                if ($target instanceof Select) {
                    // Set flag so child Select adds explicit column aliases for SQLite compatibility
                    $target->_forceExplicitAliases = true;
                    $target = $target->assemble();
                } elseif (is_string($target)) {
                    // Strip outer parentheses from string SQL - DBAL handles wrapping internally
                    // This fixes SQLite compatibility where explicit parentheses cause syntax errors
                    $target = trim($target);
                    if (str_starts_with($target, '(') && str_ends_with($target, ')')) {
                        $target = substr($target, 1, -1);
                    }
                }

                $unionType = ($union['type'] === self::SQL_UNION_ALL)
                    ? \Doctrine\DBAL\Query\UnionType::ALL
                    : \Doctrine\DBAL\Query\UnionType::DISTINCT;

                if ($isFirstUnion) {
                    // First UNION part becomes the base when there's no base query
                    $queryBuilder->union($target);
                    $isFirstUnion = false;
                } else {
                    $queryBuilder->addUnion($target, $unionType);
                }
            }

            // Note: ORDER BY and LIMIT for UNION queries are added manually after getSQL()
            // because DBAL's handling of ORDER BY column quoting doesn't work well with SQLite
        }

        $sql = $queryBuilder->getSQL();

        // For UNION queries, add ORDER BY and LIMIT manually
        // This ensures column names aren't over-quoted for SQLite compatibility
        if ($this->_parts[self::UNION]) {
            // Add ORDER BY
            if ($this->_parts[self::ORDER]) {
                $orderParts = [];
                foreach ($this->_parts[self::ORDER] as $term) {
                    if ($term instanceof Expr) {
                        $orderParts[] = $term->__toString();
                    } elseif (is_array($term)) {
                        // Use unquoted column names for UNION ORDER BY
                        $orderParts[] = $term[0] . ' ' . $term[1];
                    }
                }
                if ($orderParts) {
                    $sql .= ' ORDER BY ' . implode(', ', $orderParts);
                }
            }

            // Add LIMIT/OFFSET
            if ($this->_parts[self::LIMIT_COUNT] !== null) {
                $sql .= ' LIMIT ' . (int) $this->_parts[self::LIMIT_COUNT];
                if ($this->_parts[self::LIMIT_OFFSET] !== null) {
                    $sql .= ' OFFSET ' . (int) $this->_parts[self::LIMIT_OFFSET];
                }
            }
        }

        // Add STRAIGHT_JOIN keyword if enabled (MySQL optimization hint)
        // Note: Must be added after getting SQL, as DISTINCT needs to come first (SELECT DISTINCT)
        if ($this->_adapter->supportStraightJoin() && $this->_parts[self::STRAIGHT_JOIN]) {
            // Insert STRAIGHT_JOIN keyword right after SELECT (or after SELECT DISTINCT)
            if ($this->_parts[self::DISTINCT]) {
                // SELECT DISTINCT -> SELECT DISTINCT STRAIGHT_JOIN
                $sql = str_replace('SELECT DISTINCT ', 'SELECT DISTINCT STRAIGHT_JOIN ', $sql);
            } else {
                // SELECT -> SELECT STRAIGHT_JOIN
                $sql = str_replace('SELECT ', 'SELECT STRAIGHT_JOIN ', $sql);
            }
        }

        // Add FOR UPDATE clause if enabled (for pessimistic locking)
        if ($this->_parts[self::FOR_UPDATE]) {
            $sql = $this->_adapter->forUpdate($sql);
        }

        return $sql;
    }

    /**
     * Converts this object to an SQL SELECT string.
     */
    #[\Override]
    public function __toString(): string
    {
        try {
            return $this->assemble();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }
    }

    /**
     * Adds a UNION clause to the query
     */
    public function union(array|Select $select = [], ?string $type = null): self
    {
        if (!is_array($select)) {
            $select = [$select];
        }

        if (!count($select)) {
            throw new Exception('No selects provided for union');
        }

        // If this select is empty, use the first select as the base (only if it's a Select)
        $isEmptySelect = empty($this->_parts[self::FROM]) && empty($this->_parts[self::COLUMNS]);
        if ($isEmptySelect && count($select) > 0) {
            $firstSelect = $select[0];
            // Only use the first select as base if it's a Select object
            // If it's a string (raw SQL), we need to keep this select empty and add all as unions
            if ($firstSelect instanceof Select) {
                // Take the first select and make it the base query
                array_shift($select);
                // Copy all parts EXCEPT union from the first select
                foreach (array_keys($this->_parts) as $part) {
                    if ($part !== self::UNION) {
                        $this->_parts[$part] = $firstSelect->getPart($part);
                    }
                }
            }
        }

        // Add remaining (or all) selects as UNIONs
        foreach ($select as $target) {
            $this->_parts[self::UNION][] = [
                'target' => $target,
                'type' => $type,
            ];
        }

        return $this;
    }

    /**
     * Executes the current select object and returns the result statement
     */
    public function query(array $bind = []): Statement\StatementInterface
    {
        return $this->_adapter->query($this, $bind);
    }
}
