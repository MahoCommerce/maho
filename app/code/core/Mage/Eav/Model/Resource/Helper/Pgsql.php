<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Helper_Pgsql extends Mage_Core_Model_Resource_Helper_Pgsql
{
    /**
     * PostgreSQL column - Table DDL type pairs
     *
     * @var array
     */
    protected $_ddlColumnTypes = [
        Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'boolean',
        Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'smallint',
        Maho\Db\Ddl\Table::TYPE_INTEGER       => 'integer',
        Maho\Db\Ddl\Table::TYPE_BIGINT        => 'bigint',
        Maho\Db\Ddl\Table::TYPE_FLOAT         => 'real',
        Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'numeric',
        Maho\Db\Ddl\Table::TYPE_DATE          => 'date',
        // TYPE_TIMESTAMP is a value-equal alias for TYPE_DATETIME — both fall here.
        // PgSQL's `timestamp` is the semantic equivalent of MySQL's DATETIME.
        Maho\Db\Ddl\Table::TYPE_DATETIME      => 'timestamp',
        Maho\Db\Ddl\Table::TYPE_TEXT          => 'text',
        Maho\Db\Ddl\Table::TYPE_BLOB          => 'bytea',
        Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'bytea',
    ];

    /**
     * Returns columns for select
     *
     * @param string $tableAlias
     * @param string $eavType
     * @return string
     */
    public function attributeSelectFields($tableAlias, $eavType)
    {
        return '*';
    }

    /**
     * Prepares value fields for unions depend on type
     *
     * @param string $value
     * @param string $eavType
     * @return string
     */
    public function prepareEavAttributeValue($value, $eavType)
    {
        return $value;
    }

    /**
     * Wrap value in aggregate function for PostgreSQL GROUP BY compatibility
     *
     * PostgreSQL requires all non-aggregated columns in SELECT to be in GROUP BY.
     * For EAV queries where we GROUP BY entity_id and attribute_id, columns from
     * LEFT JOINed tables (like t_d.value, t_s.value) need to be wrapped in MAX()
     * to satisfy PostgreSQL's strict GROUP BY requirements.
     *
     * @param string|Maho\Db\Expr $value
     * @return Maho\Db\Expr
     */
    public function wrapForGroupBy($value)
    {
        return new Maho\Db\Expr("MAX($value)");
    }

    /**
     * Check if database requires strict GROUP BY (all SELECT columns in GROUP BY)
     *
     * @return bool
     */
    public function requiresStrictGroupBy()
    {
        return true;
    }

    /**
     * Groups selects to separate unions depend on type
     *
     * @param array $selects
     * @return array
     */
    public function getLoadAttributesSelectGroups($selects)
    {
        $mainGroup = [];
        foreach ($selects as $eavType => $selectGroup) {
            $mainGroup = array_merge($mainGroup, $selectGroup);
        }
        return $mainGroup;
    }

    /**
     * Retrieve 'cast to int' expression
     *
     * In PostgreSQL, CAST('' AS INTEGER) fails with "invalid input syntax",
     * unlike MySQL which returns 0. Use NULLIF to handle empty strings,
     * and COALESCE to return 0 for NULL/empty values.
     *
     * @param string|Maho\Db\Expr $expression
     * @return Maho\Db\Expr
     */
    public function getCastToIntExpression($expression)
    {
        // NULLIF converts empty strings to NULL, then COALESCE returns 0 for NULL
        // The TRIM ensures we also handle whitespace-only strings
        return new Maho\Db\Expr("COALESCE(NULLIF(TRIM(CAST($expression AS TEXT)), '')::INTEGER, 0)");
    }
}
