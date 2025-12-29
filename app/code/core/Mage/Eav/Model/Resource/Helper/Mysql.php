<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Resource_Helper_Mysql extends Mage_Core_Model_Resource_Helper_Mysql
{
    /**
     * Mysql column - Table DDL type pairs
     *
     * @var array
     */
    protected $_ddlColumnTypes      = [
        Maho\Db\Ddl\Table::TYPE_BOOLEAN       => 'bool',
        Maho\Db\Ddl\Table::TYPE_SMALLINT      => 'smallint',
        Maho\Db\Ddl\Table::TYPE_INTEGER       => 'int',
        Maho\Db\Ddl\Table::TYPE_BIGINT        => 'bigint',
        Maho\Db\Ddl\Table::TYPE_FLOAT         => 'float',
        Maho\Db\Ddl\Table::TYPE_DECIMAL       => 'decimal',
        Maho\Db\Ddl\Table::TYPE_NUMERIC       => 'decimal',
        Maho\Db\Ddl\Table::TYPE_DATE          => 'date',
        Maho\Db\Ddl\Table::TYPE_TIMESTAMP     => 'timestamp',
        Maho\Db\Ddl\Table::TYPE_DATETIME      => 'datetime',
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
        switch ($columnType) {
            case 'char':
            case 'varchar':
                $columnType = 'text';
                break;
            case 'tinyint':
                $columnType = 'smallint';
                break;
            case 'int unsigned':
                $columnType = 'int';
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
     * Wrap value for GROUP BY compatibility
     *
     * MySQL allows non-aggregated columns in SELECT even if not in GROUP BY
     * (unless ONLY_FULL_GROUP_BY is enabled), so no wrapping is needed.
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
        $mainGroup  = [];
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
        return new Maho\Db\Expr("CAST($expression AS SIGNED)");
    }
}
