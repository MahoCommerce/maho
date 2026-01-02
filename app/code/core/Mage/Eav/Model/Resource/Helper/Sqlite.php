<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Resource_Helper_Sqlite extends Mage_Core_Model_Resource_Helper_Sqlite
{
    /**
     * SQLite column - Table DDL type pairs
     *
     * @var array
     */
    protected $_ddlColumnTypes = [
        Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'integer',
        Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'integer',
        Maho\Db\Ddl\Table::TYPE_INTEGER       => 'integer',
        Maho\Db\Ddl\Table::TYPE_BIGINT        => 'integer',
        Maho\Db\Ddl\Table::TYPE_FLOAT         => 'real',
        Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'real',
        Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'real',
        Maho\Db\Ddl\Table::TYPE_DATE          => 'text',
        Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'text',
        Maho\Db\Ddl\Table::TYPE_DATETIME      => 'text',
        Maho\Db\Ddl\Table::TYPE_TEXT          => 'text',
        Maho\Db\Ddl\Table::TYPE_BLOB          => 'blob',
        Maho\Db\Ddl\Table::TYPE_VARBINARY     => 'blob',
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
            'date'     => Maho\Db\Ddl\Table::TYPE_DATE,
            'blob'     => Maho\Db\Ddl\Table::TYPE_BLOB,
            'json'     => Maho\Db\Ddl\Table::TYPE_TEXT,
            'ascii_string' => Maho\Db\Ddl\Table::TYPE_TEXT,
            'binary'   => Maho\Db\Ddl\Table::TYPE_BLOB,
        ];

        if (isset($doctrineTypeMap[$columnType])) {
            return $doctrineTypeMap[$columnType];
        }

        $result = array_search($columnType, $this->_ddlColumnTypes);
        // Default to TEXT for unknown types
        return $result !== false ? $result : Maho\Db\Ddl\Table::TYPE_TEXT;
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
     * Wrap value for GROUP BY compatibility
     *
     * SQLite is lenient like MySQL with GROUP BY, so no wrapping is needed.
     *
     * @param string|Maho\Db\Expr $value
     * @return string|Maho\Db\Expr
     */
    public function wrapForGroupBy($value)
    {
        return $value;
    }

    /**
     * Check if database requires strict GROUP BY (all SELECT columns in GROUP BY)
     *
     * @return bool
     */
    public function requiresStrictGroupBy()
    {
        return false;
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
     * @param string|Maho\Db\Expr $expression
     * @return Maho\Db\Expr
     */
    public function getCastToIntExpression($expression)
    {
        return new Maho\Db\Expr("CAST($expression AS INTEGER)");
    }
}
