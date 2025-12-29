<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Helper_Sqlite extends Mage_Core_Model_Resource_Helper_Abstract
{
    /**
     * Returns expression for field unification.
     *
     * @param string $field
     * @return Maho\Db\Expr
     */
    #[\Override]
    public function castField($field)
    {
        return new Maho\Db\Expr($field);
    }

    /**
     * Returns analytic expression for database column
     *
     * @param string $column
     * @param string $groupAliasName OPTIONAL
     * @param string $orderBy OPTIONAL
     * @return Maho\Db\Expr
     */
    public function prepareColumn($column, $groupAliasName = null, $orderBy = null)
    {
        return new Maho\Db\Expr((string) $column);
    }

    /**
     * Returns select query with analytic functions
     *
     * @return string
     */
    public function getQueryUsingAnalyticFunction(\Maho\Db\Select $select)
    {
        return $select->assemble();
    }

    /**
     * Returns Insert From Select On Duplicate query with analytic functions
     *
     * @param string $table
     * @param array $fields
     * @return string
     */
    public function getInsertFromSelectUsingAnalytic(\Maho\Db\Select $select, $table, $fields)
    {
        return $select->insertFromSelect($table, $fields);
    }

    /**
     * Correct limitation of queries with UNION
     *
     * @param Maho\Db\Select $select
     * @return Maho\Db\Select
     */
    public function limitUnion($select)
    {
        return $select;
    }

    /**
     * Returns array of quoted orders with direction
     *
     * @param bool $autoReset
     * @return array
     */
    protected function _prepareOrder(\Maho\Db\Select $select, $autoReset = false)
    {
        $selectOrders = $select->getPart(Maho\Db\Select::ORDER);
        if (!$selectOrders) {
            return [];
        }

        $orders = [];
        foreach ($selectOrders as $term) {
            if (is_array($term)) {
                if (!is_numeric($term[0])) {
                    $orders[] = sprintf('%s %s', $this->_getReadAdapter()->quoteIdentifier($term[0], true), $term[1]);
                }
            } else {
                if (!is_numeric($term)) {
                    $orders[] = $this->_getReadAdapter()->quoteIdentifier($term, true);
                }
            }
        }

        if ($autoReset) {
            $select->reset(Maho\Db\Select::ORDER);
        }

        return $orders;
    }

    /**
     * Truncate alias name from field.
     *
     * @param string $field
     * @param bool   $reverse OPTIONAL
     * @return string
     */
    protected function _truncateAliasName($field, $reverse = false)
    {
        $string = $field;
        if (!is_numeric($field) && (str_contains($field, '.'))) {
            $size = strpos($field, '.');
            if ($reverse) {
                $string = substr($field, 0, $size);
            } else {
                $string = substr($field, $size + 1);
            }
        }

        return $string;
    }

    /**
     * Returns quoted group by fields
     *
     * @param bool $autoReset
     * @return array
     */
    protected function _prepareGroup(\Maho\Db\Select $select, $autoReset = false)
    {
        $selectGroups = $select->getPart(Maho\Db\Select::GROUP);
        if (!$selectGroups) {
            return [];
        }

        $groups = [];
        foreach ($selectGroups as $term) {
            $groups[] = $this->_getReadAdapter()->quoteIdentifier($term, true);
        }

        if ($autoReset) {
            $select->reset(Maho\Db\Select::GROUP);
        }

        return $groups;
    }

    /**
     * Prepare and returns having array
     *
     * @param bool $autoReset
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _prepareHaving(\Maho\Db\Select $select, $autoReset = false)
    {
        $selectHavings = $select->getPart(Maho\Db\Select::HAVING);
        if (!$selectHavings) {
            return [];
        }

        $havings = [];
        $columns = $select->getPart(Maho\Db\Select::COLUMNS);
        foreach ($columns as $columnEntry) {
            $correlationName = (string) $columnEntry[1];
            $column = $columnEntry[2];
            foreach ($selectHavings as $having) {
                if (str_contains($having, $correlationName)) {
                    if (is_string($column)) {
                        $havings[] = str_replace($correlationName, $column, $having);
                    } else {
                        throw new Mage_Core_Exception(sprintf("Can't prepare expression without column alias: '%s'", $correlationName));
                    }
                }
            }
        }

        if ($autoReset) {
            $select->reset(Maho\Db\Select::HAVING);
        }

        return $havings;
    }

    /**
     * @param string $query
     * @param int $limitCount
     * @param int $limitOffset
     * @param array $columnList
     * @return string
     */
    protected function _assembleLimit($query, $limitCount, $limitOffset, $columnList = [])
    {
        if ($limitCount !== null) {
            $limitCount = (int) $limitCount;
            $limitOffset = (int) $limitOffset;

            if ($limitOffset + $limitCount != $limitOffset + 1) {
                $query = sprintf('%s LIMIT %d OFFSET %d', $query, $limitCount, $limitOffset);
            }
        }

        return $query;
    }

    /**
     * Prepare select column list
     *
     * @param string $groupByCondition
     * @return array
     * @throws Mage_Core_Exception
     */
    public function prepareColumnsList(\Maho\Db\Select $select, $groupByCondition = null)
    {
        if (!count($select->getPart(Maho\Db\Select::FROM))) {
            return $select->getPart(Maho\Db\Select::COLUMNS);
        }

        $columns = $select->getPart(Maho\Db\Select::COLUMNS);
        $tables = $select->getPart(Maho\Db\Select::FROM);
        $preparedColumns = [];

        foreach ($columns as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if ($column instanceof Maho\Db\Expr) {
                if ($alias !== null) {
                    if (preg_match('/(^|[^a-zA-Z_])^(SELECT)?(SUM|MIN|MAX|AVG|COUNT)\s*\(/i', (string) $column, $matches)) {
                        $column = $this->prepareColumn($column, $groupByCondition);
                    }
                    $preparedColumns[strtoupper($alias)] = [null, $column, $alias];
                } else {
                    throw new Mage_Core_Exception("Can't prepare expression without alias");
                }
            } else {
                if ($column == Maho\Db\Select::SQL_WILDCARD) {
                    if ($tables[$correlationName]['tableName'] instanceof Maho\Db\Expr) {
                        throw new Mage_Core_Exception("Can't prepare expression when tableName is instance of Maho\Db\Expr");
                    }
                    $tableColumns = $this->_getReadAdapter()->describeTable($tables[$correlationName]['tableName']);
                    foreach (array_keys($tableColumns) as $col) {
                        $preparedColumns[strtoupper($col)] = [$correlationName, $col, null];
                    }
                } else {
                    $columnKey = is_null($alias) ? $column : $alias;
                    $preparedColumns[strtoupper($columnKey)] = [$correlationName, $column, $alias];
                }
            }
        }

        return $preparedColumns;
    }

    /**
     * Add prepared column group_concat expression.
     * SQLite uses GROUP_CONCAT like MySQL.
     *
     * @param Maho\Db\Select $select
     * @param string $fieldAlias Field alias which will be added with column group_concat expression
     * @param string $fields
     * @param string $groupConcatDelimiter
     * @param string $fieldsDelimiter
     * @param string $additionalWhere
     * @return Maho\Db\Select
     */
    public function addGroupConcatColumn($select, $fieldAlias, $fields, $groupConcatDelimiter = ',', $fieldsDelimiter = '', $additionalWhere = '')
    {
        if (is_array($fields)) {
            $fieldExpr = $this->_getReadAdapter()->getConcatSql($fields, $fieldsDelimiter);
        } else {
            $fieldExpr = $fields;
        }
        if ($additionalWhere) {
            $fieldExpr = $this->_getReadAdapter()->getCheckSql($additionalWhere, $fieldExpr, "''");
        }

        $separator = $groupConcatDelimiter ?: ',';
        $select->columns([$fieldAlias => new Maho\Db\Expr(sprintf("GROUP_CONCAT(%s, '%s')", $fieldExpr, $separator))]);

        return $select;
    }

    /**
     * Returns expression of days passed from $startDate to $endDate.
     * SQLite uses julianday() for date arithmetic.
     *
     * @param  string|Maho\Db\Expr $startDate
     * @param  string|Maho\Db\Expr $endDate
     * @return Maho\Db\Expr
     */
    public function getDateDiff($startDate, $endDate)
    {
        $dateDiff = "(CAST(julianday($endDate) - julianday($startDate) AS INTEGER))";
        return new Maho\Db\Expr($dateDiff);
    }

    /**
     * Escapes and quotes LIKE value.
     *
     * @param string $value
     * @param array $options
     * @return Maho\Db\Expr
     *
     * @see escapeLikeValue()
     */
    #[\Override]
    public function addLikeEscape($value, $options = [])
    {
        $value = $this->escapeLikeValue($value, $options);
        return new Maho\Db\Expr($this->_getReadAdapter()->quote($value));
    }
}
