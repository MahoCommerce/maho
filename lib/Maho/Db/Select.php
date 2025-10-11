<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class for SQL SELECT generation and results.
 *
 * Standalone implementation that generates SQL without depending on Zend Framework.
 *
 * @property Adapter\AdapterInterface $_adapter
 * @method Adapter\AdapterInterface getAdapter()
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
    public const FULL_JOIN      = 'full join';
    public const CROSS_JOIN     = 'cross join';
    public const NATURAL_JOIN   = 'natural join';

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

    /**
     * The adapter that created this Select object
     */
    protected Adapter\Pdo\Mysql $_adapter;

    /**
     * The component parts of a SELECT statement
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
     * Class constructor
     *
     * @param Adapter\Pdo\Mysql $adapter
     */
    public function __construct($adapter)
    {
        $this->_adapter = $adapter;
    }

    /**
     * Get the adapter
     *
     * @return Adapter\Pdo\Mysql
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Makes the query SELECT DISTINCT.
     *
     * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
     * @return $this
     */
    public function distinct($flag = true)
    {
        $this->_parts[self::DISTINCT] = (bool) $flag;
        return $this;
    }

    /**
     * Adds a FROM table and optional columns to the query.
     *
     * @param  array|string|Expr $name The table name or array of table => alias.
     * @param  array|string $cols The columns to select from the table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function from($name, $cols = '*', $schema = null)
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
     *
     * @param  array|string|Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function join($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->joinInner($name, $cond, $cols, $schema);
    }

    /**
     * Add an INNER JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  string|null $cond Join on this condition (null for CROSS join).
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinInner($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        // If no condition is given for inner join, make it a cross join
        if (empty($cond) && $cond !== null) {
            return $this->_join(self::CROSS_JOIN, $name, null, $cols, $schema);
        }
        return $this->_join(self::INNER_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a LEFT OUTER JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinLeft($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::LEFT_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a RIGHT OUTER JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinRight($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a FULL OUTER JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinFull($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::FULL_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a CROSS JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinCross($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::CROSS_JOIN, $name, null, $cols, $schema);
    }

    /**
     * Add a NATURAL JOIN table and columns to the query
     *
     * @param  array|string|Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The schema name to specify, if any.
     * @return $this
     */
    public function joinNatural($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::NATURAL_JOIN, $name, null, $cols, $schema);
    }

    /**
     * Populate the {@link $_parts} 'join' key
     *
     * @param  string $type Type of join
     * @param  array|string|Expr $name Table name
     * @param  string|null $cond Join on this condition (null for CROSS/NATURAL joins)
     * @param  array|string $cols The columns to select from the joined table
     * @param  string $schema The database name to specify, if any.
     * @return $this
     */
    protected function _join($type, $name, $cond, $cols, $schema = null)
    {
        if (!in_array($type, [self::INNER_JOIN, self::LEFT_JOIN, self::RIGHT_JOIN, self::FULL_JOIN, self::CROSS_JOIN, self::NATURAL_JOIN])) {
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
            // $name is a string
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation($tableName);
        }

        // Schema
        if ($schema !== null && is_string($schema)) {
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
            if (is_string($col)) {
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
            } elseif ($col instanceof Select) {
                // Convert Select to Expr
                $col = new Expr(sprintf('(%s)', $col->assemble()));
            }

            $columnValues[] = [$currentCorrelationName, $col, is_string($alias) ? $alias : null];
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
     *
     * @param string $cond The WHERE condition.
     * @param mixed $value OPTIONAL A single value to quote into the condition.
     * @param mixed $type OPTIONAL The type of the given value
     * @return $this
     */
    public function where($cond, $value = null, $type = null)
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);
        return $this;
    }

    /**
     * Adds a WHERE condition to the query by OR
     *
     * @param string $cond The WHERE condition.
     * @param mixed $value OPTIONAL A single value to quote into the condition.
     * @param mixed $type OPTIONAL The type of the given value
     * @return $this
     */
    public function orWhere($cond, $value = null, $type = null)
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);
        return $this;
    }

    /**
     * Internal function for creating WHERE conditions
     *
     * @param string $cond
     * @param mixed $value
     * @param string $type
     * @param bool $bool True = AND, False = OR
     * @return array<string, string>
     */
    protected function _where($cond, $value = null, $type = null, $bool = true)
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
        } else {
            return [self::SQL_OR => $cond];
        }
    }

    /**
     * Adds grouping to the query.
     *
     * @param  array|string $spec The column(s) to group by.
     * @return $this
     */
    public function group($spec)
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
     * @return $this
     */
    public function having($cond, $value = null, $type = null)
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
     * @return $this
     */
    public function orHaving($cond, $value = null, $type = null)
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
     * Adds a row order to the query.
     *
     * @param mixed $spec The column(s) and direction to order by.
     * @return $this
     */
    public function order($spec)
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
    public function limit($count = null, $offset = null)
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
    public function limitPage($page, $rowCount)
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
    public function forUpdate($flag = true)
    {
        $this->_parts[self::FOR_UPDATE] = (bool) $flag;
        return $this;
    }

    /**
     * Get part of the structured information for the current query.
     *
     * @param string $part
     * @return mixed
     */
    public function getPart($part)
    {
        $part = strtolower($part);
        if (!array_key_exists($part, $this->_parts)) {
            throw new Exception("Invalid Select part '$part'");
        }
        return $this->_parts[$part];
    }

    /**
     * Modify (hack) part of the structured information for the current query
     *
     * @param string $part
     * @param mixed $value
     * @return $this
     */
    public function setPart($part, $value)
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
    public function columns($cols = '*', $correlationName = null)
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
     *
     * @param string $part OPTIONAL
     * @return $this
     */
    public function reset($part = null)
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
    public function useStraightJoin($flag = true)
    {
        $this->_parts[self::STRAIGHT_JOIN] = (bool) $flag;
        return $this;
    }

    /**
     * Cross Table Update From Current select
     *
     * @param string|array $table
     * @return string
     */
    public function crossUpdateFromSelect($table)
    {
        return $this->getAdapter()->updateFromSelect($this, $table);
    }

    /**
     * Insert to table from current select
     *
     * @param string $tableName
     * @param array $fields
     * @param bool $onDuplicate
     * @return string
     */
    public function insertFromSelect($tableName, $fields = [], $onDuplicate = true)
    {
        $mode = $onDuplicate ? Adapter\AdapterInterface::INSERT_ON_DUPLICATE : false;
        return $this->getAdapter()->insertFromSelect($this, $tableName, $fields, $mode);
    }

    /**
     * Generate INSERT IGNORE query to the table from current select
     *
     * @param string $tableName
     * @param array $fields
     * @return string
     */
    public function insertIgnoreFromSelect($tableName, $fields = [])
    {
        return $this->getAdapter()
            ->insertFromSelect($this, $tableName, $fields, Adapter\AdapterInterface::INSERT_IGNORE);
    }

    /**
     * Retrieve DELETE query from select
     *
     * @param string $table The table name or alias
     * @return string
     */
    public function deleteFromSelect($table)
    {
        return $this->getAdapter()->deleteFromSelect($this, $table);
    }

    /**
     * Adds the random order to query
     *
     * @param string $field integer field name
     * @return $this
     */
    public function orderRand($field = null)
    {
        $this->_adapter->orderRand($this, $field);
        return $this;
    }

    /**
     * Add EXISTS clause
     *
     * @param  Select $select
     * @param  string           $joinCondition
     * @param  bool             $isExists
     * @return $this
     */
    public function exists($select, $joinCondition, $isExists = true)
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
     *
     * @return $this
     */
    public function resetJoinLeft()
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
     *
     * @return string This object as a SELECT string.
     */
    public function assemble()
    {
        $sql = self::SQL_SELECT;

        // Add STRAIGHT_JOIN if enabled
        if ($this->_adapter->supportStraightJoin() && $this->_parts[self::STRAIGHT_JOIN]) {
            $sql .= ' ' . self::SQL_STRAIGHT_JOIN;
        }

        // Add DISTINCT
        if ($this->_parts[self::DISTINCT]) {
            $sql .= ' ' . self::SQL_DISTINCT;
        }

        // Add columns
        $columns = [];
        if (!$this->_parts[self::COLUMNS]) {
            // If no columns specified, use SELECT *
            $columns[] = '*';
        } else {
            foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
                [$correlationName, $column, $alias] = $columnEntry;
                if ($column instanceof Expr) {
                    $columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
                } elseif ($column == self::SQL_WILDCARD) {
                    $columns[] = ($correlationName ? $this->_adapter->quoteIdentifier($correlationName) . '.' : '') . self::SQL_WILDCARD;
                } else {
                    $columns[] = $this->_adapter->quoteColumnAs([$correlationName, $column], $alias, true);
                }
            }
        }
        $sql .= ' ' . implode(', ', $columns);

        // Add FROM clause
        if ($this->_parts[self::FROM]) {
            $sql .= ' ' . self::SQL_FROM . ' ';
            $from = [];
            foreach ($this->_parts[self::FROM] as $correlationName => $table) {
                $tmp = '';

                // Add join type for all but the first table
                if (!empty($from)) {
                    $tmp .= ' ' . strtoupper($table['joinType']) . ' ';
                }

                // Add table name
                $tmp .= $this->_adapter->quoteTableAs($table['tableName'], $correlationName, true);

                // Add join condition
                if (!empty($table['joinCondition']) && !empty($from)) {
                    $tmp .= ' ' . self::SQL_ON . ' ' . $table['joinCondition'];
                }

                $from[] = $tmp;
            }
            $sql .= implode('', $from);
        }

        // Add WHERE clause
        if ($this->_parts[self::WHERE]) {
            $sql .= ' ' . self::SQL_WHERE;
            $where = [];
            foreach ($this->_parts[self::WHERE] as $term) {
                if (is_array($term)) {
                    foreach ($term as $type => $cond) {
                        if (!empty($where)) {
                            $where[] = $type;
                        }
                        $where[] = $cond;
                    }
                } else {
                    if (!empty($where)) {
                        $where[] = self::SQL_AND;
                    }
                    $where[] = $term;
                }
            }
            $sql .= ' ' . implode(' ', $where);
        }

        // Add GROUP BY
        if ($this->_parts[self::GROUP]) {
            $group = [];
            foreach ($this->_parts[self::GROUP] as $term) {
                if ($term instanceof Expr) {
                    $group[] = $term->__toString();
                } else {
                    $group[] = $this->_adapter->quoteIdentifier($term, true);
                }
            }
            $sql .= ' ' . self::SQL_GROUP_BY . ' ' . implode(', ', $group);
        }

        // Add HAVING
        if ($this->_parts[self::HAVING]) {
            $sql .= ' ' . self::SQL_HAVING . ' ' . implode(' ', $this->_parts[self::HAVING]);
        }

        // Add ORDER BY
        if ($this->_parts[self::ORDER]) {
            $order = [];
            foreach ($this->_parts[self::ORDER] as $term) {
                if ($term instanceof Expr) {
                    $order[] = $term->__toString();
                } elseif (is_array($term)) {
                    // Check if the order field is a SQL expression (contains parentheses = function call)
                    // If so, don't quote it as an identifier
                    if (str_contains($term[0], '(') && str_contains($term[0], ')')) {
                        $order[] = $term[0] . ' ' . $term[1];
                    } else {
                        $order[] = $this->_adapter->quoteIdentifier($term[0], true) . ' ' . $term[1];
                    }
                }
            }
            $sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
        }

        // Add UNION
        if ($this->_parts[self::UNION]) {
            // Check if this is an empty select (no FROM clause)
            $isEmptySelect = empty($this->_parts[self::FROM]);

            if ($isEmptySelect) {
                // For empty selects, just render the UNIONs without a base query
                $parts = [];
                foreach ($this->_parts[self::UNION] as $union) {
                    $target = $union['target'];
                    if ($target instanceof Select) {
                        $target = $target->assemble();
                    }
                    $parts[] = $target;
                }
                // Join with UNION (first one doesn't need UNION keyword)
                $sql = $parts[0];
                for ($i = 1; $i < count($parts); $i++) {
                    $unionType = $this->_parts[self::UNION][$i]['type'];
                    $sql .= ' ' . ($unionType === self::SQL_UNION_ALL ? 'UNION ALL' : 'UNION') . ' ' . $parts[$i];
                }

                // Add ORDER BY if specified (applies to entire UNION)
                if ($this->_parts[self::ORDER]) {
                    $order = [];
                    foreach ($this->_parts[self::ORDER] as $term) {
                        if ($term instanceof Expr) {
                            $order[] = $term->__toString();
                        } elseif (is_array($term)) {
                            if (str_contains($term[0], '(') && str_contains($term[0], ')')) {
                                $order[] = $term[0] . ' ' . $term[1];
                            } else {
                                $order[] = $this->_adapter->quoteIdentifier($term[0], true) . ' ' . $term[1];
                            }
                        }
                    }
                    $sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
                }
            } else {
                // Normal case: base query with UNIONs
                $parts = [$sql];
                foreach ($this->_parts[self::UNION] as $union) {
                    $target = $union['target'];
                    if ($target instanceof Select) {
                        $target = $target->assemble();
                    }
                    $parts[] = ($union['type'] === self::SQL_UNION_ALL ? 'UNION ALL' : 'UNION') . ' ' . $target;
                }
                $sql = implode(' ', $parts);
            }
        }

        // Add LIMIT
        if ($this->_parts[self::LIMIT_COUNT] !== null) {
            $sql .= ' LIMIT ' . (int) $this->_parts[self::LIMIT_COUNT];
            if ($this->_parts[self::LIMIT_OFFSET] !== null) {
                $sql .= ' OFFSET ' . (int) $this->_parts[self::LIMIT_OFFSET];
            }
        }

        // Add FOR UPDATE
        if ($this->_parts[self::FOR_UPDATE]) {
            $sql = $this->_adapter->forUpdate($sql);
        }

        return $sql;
    }

    /**
     * Converts this object to an SQL SELECT string.
     *
     * @return string
     */
    #[\Override]
    public function __toString()
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
     *
     * @param array $select Array of Select objects to union
     * @param string $type Union type (default: UNION, or UNION ALL)
     * @return $this
     */
    public function union($select = [], $type = null)
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
     *
     * @param array $bind Optional bind parameters
     * @return Statement\Pdo\Mysql
     */
    public function query($bind = [])
    {
        return $this->_adapter->query($this, $bind);
    }
}
