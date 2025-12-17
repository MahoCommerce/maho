<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'numeric',
        Maho\Db\Ddl\Table::TYPE_DATE          => 'date',
        Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'timestamp',
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
     * Returns DDL type by column type in database
     *
     * @param string $columnType
     * @return string
     */
    public function getDdlTypeByColumnType($columnType)
    {
        // Map Doctrine DBAL type names to DDL constants
        // The DATA_TYPE comes from Doctrine type classes (e.g., IntegerType -> 'integer')
        $doctrineTypeMap = [
            'string'   => Maho\Db\Ddl\Table::TYPE_TEXT,
            'text'     => Maho\Db\Ddl\Table::TYPE_TEXT,
            'integer'  => Maho\Db\Ddl\Table::TYPE_INTEGER,
            'smallint' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'bigint'   => Maho\Db\Ddl\Table::TYPE_BIGINT,
            'float'    => Maho\Db\Ddl\Table::TYPE_FLOAT,
            'decimal'  => Maho\Db\Ddl\Table::TYPE_DECIMAL,
            'boolean'  => Maho\Db\Ddl\Table::TYPE_BOOLEAN,
            'datetime' => Maho\Db\Ddl\Table::TYPE_DATETIME,
            'datetimetz' => Maho\Db\Ddl\Table::TYPE_DATETIME,
            'date'     => Maho\Db\Ddl\Table::TYPE_DATE,
            'time'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'blob'     => Maho\Db\Ddl\Table::TYPE_BLOB,
            'binary'   => Maho\Db\Ddl\Table::TYPE_VARBINARY,
        ];

        if (isset($doctrineTypeMap[$columnType])) {
            return $doctrineTypeMap[$columnType];
        }

        // Fallback: try the old mapping in case raw PostgreSQL types are passed
        switch ($columnType) {
            case 'character':
            case 'character varying':
            case 'varchar':
            case 'bpchar':
                $columnType = 'text';
                break;
            case 'int':
            case 'int4':
            case 'serial':
                $columnType = 'integer';
                break;
            case 'int2':
            case 'smallserial':
                $columnType = 'smallint';
                break;
            case 'int8':
            case 'bigserial':
                $columnType = 'bigint';
                break;
            case 'float4':
                $columnType = 'real';
                break;
            case 'float8':
            case 'double precision':
                $columnType = 'real';
                break;
            case 'bool':
                $columnType = 'boolean';
                break;
            case 'timestamp without time zone':
            case 'timestamp with time zone':
            case 'timestamptz':
            case 'timestamp':
                $columnType = 'timestamp';
                break;
            case 'numeric':
                // Already in correct form
                break;
        }

        return array_search($columnType, $this->_ddlColumnTypes);
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
