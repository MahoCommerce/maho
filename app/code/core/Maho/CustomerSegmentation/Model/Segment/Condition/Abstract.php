<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Maho_CustomerSegmentation_Model_Segment_Condition_Abstract extends Mage_Rule_Model_Condition_Abstract
{
    abstract public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false;

    public function getOperator(): string
    {
        $operator = parent::getOperator();

        // Ensure operator is always a string, never an array
        if (is_array($operator)) {
            Mage::log('Array operator detected in getOperator(): ' . print_r($operator, true) . ' for attribute: ' . $this->getAttribute(), Zend_Log::ERR);
            return '=';
        }

        return $operator ?: '=';
    }

    public function getMappedSqlOperator(): string
    {
        $operator = $this->getOperator();
        $value = $this->getValue();

        // Check for literal string '[]' which means an array was converted to string
        if ($operator === '[]' || $operator === 'Array') {
            return '=';
        }

        // Fallback to default operator if none is set
        if (empty($operator)) {
            return '=';
        }

        return match ($operator) {
            '==' => $this->_getEqualityOperator($value),
            '!=' => $this->_getInequalityOperator($value),
            '>=' => '>=',
            '<=' => '<=',
            '>' => '>',
            '<' => '<',
            '{}' => 'LIKE',
            '!{}' => 'NOT LIKE',
            '()' => 'IN',
            '!()' => 'NOT IN',
            default => $operator,
        };
    }

    protected function _getEqualityOperator(mixed $value): string
    {
        if (is_array($value) && count($value) > 1) {
            return 'IN';
        }
        return '=';
    }

    protected function _getInequalityOperator(mixed $value): string
    {
        if (is_array($value) && count($value) > 1) {
            return 'NOT IN';
        }
        return '!=';
    }

    public function prepareValueForSql(mixed $value, string $operator): mixed
    {
        switch ($operator) {
            case 'LIKE':
            case 'NOT LIKE':
                return '%' . $value . '%';
            case 'IN':
            case 'NOT IN':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                return array_map('trim', $value);
            default:
                return $value;
        }
    }

    protected function _buildSqlCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        // Fallback for empty operator
        if (empty($operator)) {
            $operator = '=';
        }

        $value = $this->prepareValueForSql($value, $operator);

        return match ($operator) {
            'IN', 'NOT IN' => $adapter->quoteInto("{$field} {$operator} (?)", $value),
            'LIKE', 'NOT LIKE' => $adapter->quoteInto("{$field} {$operator} ?", $value),
            'IS', 'IS NOT' => "{$field} {$operator} {$value}",
            default => $adapter->quoteInto("{$field} {$operator} ?", $value),
        };
    }

    protected function _getCustomerTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('customer/entity');
    }

    protected function _getCustomerAttributeTable(string $attributeCode): array|false
    {
        $attribute = Mage::getSingleton('eav/config')
            ->getAttribute('customer', $attributeCode);

        if (!$attribute) {
            return false;
        }

        return [
            'table' => $attribute->getBackendTable(),
            'attribute_id' => $attribute->getId(),
        ];
    }

    protected function _getOrderTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order');
    }

    protected function _getQuoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote');
    }

    #[\Override]
    public function asHtml(): string
    {
        $html = $this->getTypeElement()->getHtml() .
                Mage::helper('customersegmentation')->__(
                    'If %s %s %s',
                    $this->getAttributeElement()->getHtml(),
                    $this->getOperatorElement()->getHtml(),
                    $this->getValueElement()->getHtml(),
                );

        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }

        return $html;
    }

    #[\Override]
    public function loadArray($arr, string $key = 'conditions'): self
    {
        $this->setData($key, $arr);

        return parent::loadArray($arr);
    }
}
