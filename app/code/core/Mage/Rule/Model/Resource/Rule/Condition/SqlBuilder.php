<?php

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rule_Model_Resource_Rule_Condition_SqlBuilder
{
    /**
     * Database adapter
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_adapter;

    public function __construct(array $config = [])
    {
        $this->_adapter = $config['adapter'] ?? Mage::getSingleton('core/resource')->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE);
    }

    /**
     * Convert operator for sql where
     *
     * @param string $field
     * @param string $operator
     * @param string|array $value
     * @return string
     */
    public function getOperatorCondition($field, $operator, $value)
    {
        // Handle FIND_IN_SET operators separately (database-agnostic)
        if (in_array($operator, ['()', '!()', '[]', '![]'])) {
            return $this->_buildFindInSetCondition($field, $operator, $value);
        }

        switch ($operator) {
            case '!=':
            case '>=':
            case '<=':
            case '>':
            case '<':
                $selectOperator = sprintf('%s?', $operator);
                break;
            case '{}':
            case '!{}':
                if (preg_match('/^.*(category_id)$/', $field) && is_array($value)) {
                    $selectOperator = ' IN (?)';
                } else {
                    $selectOperator = ' LIKE ?';
                }
                if (str_starts_with($operator, '!')) {
                    $selectOperator = ' NOT' . $selectOperator;
                }
                break;

            default:
                $selectOperator = '=?';
                break;
        }
        $field = $this->_adapter->quoteIdentifier($field);

        if (is_array($value) && in_array($operator, ['==', '!=', '>=', '<=', '>', '<', '{}', '!{}'])) {
            $results = [];
            foreach ($value as $v) {
                $results[] = $this->_adapter->quoteInto("{$field}{$selectOperator}", $v);
            }
            $result = implode(' AND ', $results);
        } else {
            $result = $this->_adapter->quoteInto("{$field}{$selectOperator}", $value);
        }
        return $result;
    }

    /**
     * Build FIND_IN_SET condition using database-agnostic helper
     */
    protected function _buildFindInSetCondition(string $field, string $operator, mixed $value): string
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $quotedField = $this->_adapter->quoteIdentifier($field);
        $negate = str_starts_with($operator, '!');
        $useOr = in_array($operator, ['()', '!()']);

        $results = [];
        foreach ($value as $v) {
            $expr = $this->_adapter->getFindInSetExpr($this->_adapter->quote($v), $quotedField);
            $results[] = ($negate ? 'NOT ' : '') . $expr;
        }

        return implode($useOr ? ' OR ' : ' AND ', $results);
    }
}
