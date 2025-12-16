<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Maho_CustomerSegmentation_Model_Segment_Condition_Abstract extends Mage_Rule_Model_Condition_Abstract
{
    abstract public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false;

    public function getMappedSqlOperator(): string
    {
        $operator = $this->getOperator();
        $value = $this->getValue();

        // Fallback to default operator if none is set
        if (empty($operator)) {
            return '=';
        }

        return match ($operator) {
            '==' => $this->getEqualityOperator($value),
            '!=' => $this->getInequalityOperator($value),
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

    protected function getEqualityOperator(mixed $value): string
    {
        if (is_array($value) && count($value) > 1) {
            return 'IN';
        }
        return '=';
    }

    protected function getInequalityOperator(mixed $value): string
    {
        if (is_array($value) && count($value) > 1) {
            return 'NOT IN';
        }
        return '!=';
    }

    public function prepareValueForSql(mixed $value, string $operator): mixed
    {
        return match ($operator) {
            'LIKE', 'NOT LIKE' => '%' . $value . '%',
            'IN', 'NOT IN' => is_array($value) ? array_map('trim', $value) : array_map('trim', explode(',', $value)),
            default => $value,
        };
    }

    /**
     * Prepare numeric value for SQL (cast string to float for SQLite compatibility)
     */
    protected function prepareNumericValue(mixed $value): float|int
    {
        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    protected function buildSqlCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $field, string $operator, mixed $value): string
    {
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

    protected function getCustomerTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('customer/entity');
    }

    protected function getCustomerAttributeTable(string $attributeCode): array|false
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

    protected function getOrderTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order');
    }

    protected function getQuoteTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/quote');
    }

    protected function getCustomerAddressAttributeTable(string $attributeCode): array|false
    {
        $attribute = Mage::getSingleton('eav/config')
            ->getAttribute('customer_address', $attributeCode);

        if (!$attribute) {
            return false;
        }

        return [
            'table' => $attribute->getBackendTable(),
            'attribute_id' => $attribute->getId(),
        ];
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
