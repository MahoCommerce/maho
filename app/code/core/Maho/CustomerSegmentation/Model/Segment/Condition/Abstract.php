<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

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
            'IN', 'NOT IN' => is_array($value) ? array_map(trim(...), $value) : array_map(trim(...), explode(',', $value)),
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
            // The adapter ignores case on every engine: a plain LIKE does on MySQL and SQLite, but not on PostgreSQL.
            'LIKE' => $adapter->prepareSqlCondition($field, ['like' => $value]),
            'NOT LIKE' => $adapter->prepareSqlCondition($field, ['nlike' => $value]),
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

    /**
     * Input types of user-defined attributes that hold no value to compare.
     */
    protected const SKIPPED_CUSTOM_INPUTS = ['file', 'image', 'multiline'];

    /**
     * @var array<string, array<string, Mage_Eav_Model_Entity_Attribute_Abstract>>
     */
    protected array $customAttributes = [];

    /**
     * Return the user-defined attributes of $entityTypeCode that a condition can test, keyed by code.
     *
     * @return array<string, Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    protected function getCustomAttributes(string $entityTypeCode): array
    {
        if (!isset($this->customAttributes[$entityTypeCode])) {
            $this->customAttributes[$entityTypeCode] = [];
            $config = Mage::getSingleton('eav/config');
            $codes = Mage::getResourceModel('eav/entity_attribute_collection')
                ->setEntityTypeFilter((int) $config->getEntityType($entityTypeCode)->getId())
                ->addFieldToFilter('is_user_defined', 1)
                ->addFieldToFilter('frontend_input', ['nin' => self::SKIPPED_CUSTOM_INPUTS])
                ->getColumnValues('attribute_code');
            foreach ($codes as $code) {
                $attribute = $config->getAttribute($entityTypeCode, $code);
                if ($attribute && $attribute->getId() && !$attribute->isStatic()) {
                    $this->customAttributes[$entityTypeCode][(string) $code] = $attribute;
                }
            }
        }
        return $this->customAttributes[$entityTypeCode];
    }

    /**
     * Return the user-defined attribute of $entityTypeCode that the condition tests, or null for another field.
     */
    protected function getSelectedCustomAttribute(string $entityTypeCode): ?Mage_Eav_Model_Entity_Attribute_Abstract
    {
        return $this->getCustomAttributes($entityTypeCode)[(string) $this->getAttribute()] ?? null;
    }

    /**
     * Add the labels of the user-defined attributes of $entityTypeCode to $options, keyed by code.
     *
     * @param array<string, string> $options
     * @return array<string, string>
     */
    protected function addCustomAttributeOptions(array $options, string $entityTypeCode): array
    {
        foreach ($this->getCustomAttributes($entityTypeCode) as $code => $attribute) {
            $options[$code] ??= (string) ($attribute->getFrontendLabel() ?: $code);
        }
        return $options;
    }

    /**
     * Return the input type of a user-defined attribute: select, multiselect, date or string.
     */
    protected function getCustomInputType(Mage_Eav_Model_Entity_Attribute_Abstract $attribute): string
    {
        return match ($attribute->getFrontendInput()) {
            'select', 'boolean' => 'select',
            'multiselect' => 'multiselect',
            'date' => 'date',
            default => 'string',
        };
    }

    /**
     * Return the value element of a user-defined attribute: one option for a select or a
     * multiselect, a date field for a date, and a text field for the others.
     */
    protected function getCustomValueElementType(Mage_Eav_Model_Entity_Attribute_Abstract $attribute): string
    {
        return match ($this->getCustomInputType($attribute)) {
            'select', 'multiselect' => 'select',
            'date' => 'date',
            default => 'text',
        };
    }

    /**
     * Return the operators of a user-defined attribute, or null for the operators of its input type.
     *
     * @return list<array{value: string, label: string}>|null
     */
    protected function getCustomOperatorOptions(Mage_Eav_Model_Entity_Attribute_Abstract $attribute): ?array
    {
        $helper = Mage::helper('rule');
        return match ($this->getCustomInputType($attribute)) {
            'select' => [
                ['value' => '==', 'label' => $helper->__('is')],
                ['value' => '!=', 'label' => $helper->__('is not')],
            ],
            'multiselect' => [
                ['value' => '{}', 'label' => $helper->__('contains')],
                ['value' => '!{}', 'label' => $helper->__('does not contain')],
            ],
            default => null,
        };
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    protected function getCustomValueOptions(Mage_Eav_Model_Entity_Attribute_Abstract $attribute): array
    {
        if ($attribute->getFrontendInput() === 'boolean') {
            return [
                ['value' => '1', 'label' => Mage::helper('core')->__('Yes')],
                ['value' => '0', 'label' => Mage::helper('core')->__('No')],
            ];
        }
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true) || !$attribute->usesSource()) {
            return [];
        }
        return array_values(array_filter(
            $attribute->getSource()->getAllOptions(),
            fn(array $option): bool => ($option['value'] ?? '') !== '',
        ));
    }

    /**
     * Return the condition on the entity IDs of the records whose value of $attribute matches, as
     * "$idField IN (...)" or, for "does not contain", as "$idField NOT IN (...)".
     */
    protected function buildCustomAttributeCondition(
        \Maho\Db\Adapter\AdapterInterface $adapter,
        Mage_Eav_Model_Entity_Attribute_Abstract $attribute,
        string $idField,
    ): string {
        $select = $adapter->select()
            ->from(['attr' => $attribute->getBackendTable()], ['entity_id'])
            ->where('attr.attribute_id = ?', (int) $attribute->getId());

        if ($this->getCustomInputType($attribute) === 'multiselect') {
            // A multiselect keeps its option IDs separated by commas
            $list = $adapter->getConcatSql(["','", 'attr.value', "','"]);
            $select->where($adapter->quoteInto("{$list} LIKE ?", '%,' . trim((string) $this->getValue()) . ',%'));
            return $idField . ($this->getOperator() === '!{}' ? ' NOT IN (' : ' IN (') . $select . ')';
        }

        $operator = $this->getMappedSqlOperator();
        $field = 'attr.value';
        if ($attribute->getBackendType() === 'datetime') {
            $field = (string) $adapter->getDatePartSql($field);
        }
        $select->where($this->buildSqlCondition($adapter, $field, $operator, $this->getValue()));
        return $idField . ' IN (' . $select . ')';
    }

    #[\Override]
    public function asHtml(): string
    {
        $html = $this->getTypeElement()->getHtml()
                . Mage::helper('customersegmentation')->__(
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

    /**
     * Keep only the rows of the stores that use the base currency of the website, so that the amounts add up in one currency.
     */
    protected function filterCurrencyStores(\Maho\Db\Select $select, string $alias, ?int $websiteId): void
    {
        if ($websiteId) {
            $select->where("{$alias}.store_id IN (?)", $this->getCurrencyStoreIds($websiteId));
        }
    }

    /**
     * Return the IDs of the stores of all websites that use the base currency of the website.
     * With a global price scope, every website uses the same base currency.
     *
     * @return list<int>
     */
    protected function getCurrencyStoreIds(int $websiteId): array
    {
        $currency = Mage::app()->getWebsite($websiteId)->getBaseCurrencyCode();
        $storeIds = [];
        foreach (Mage::app()->getWebsites() as $website) {
            if ($website->getBaseCurrencyCode() === $currency) {
                array_push($storeIds, ...array_map(intval(...), $website->getStoreIds()));
            }
        }
        return $storeIds;
    }

    /**
     * Return the value name with the base currency code after it, when all websites of the segment use the same base currency.
     * An amount condition compares the base currency amounts of the orders of each website.
     */
    protected function getAmountValueName(): string
    {
        $valueName = $this->getValueName();
        $websites = Mage::app()->getWebsites();
        $rule = $this->getRule();
        if ($rule instanceof Maho_CustomerSegmentation_Model_Segment && $rule->getWebsiteIds() !== []) {
            $websites = array_intersect_key($websites, array_flip($rule->getWebsiteIds()));
        }
        $codes = array_unique(array_map(fn(Mage_Core_Model_Website $website): string => (string) $website->getBaseCurrencyCode(), $websites));
        return count($codes) === 1 ? $valueName . ' ' . reset($codes) : $valueName;
    }

    #[\Override]
    public function loadArray($arr, string $key = 'conditions'): self
    {
        $this->setData($key, $arr);

        return parent::loadArray($arr);
    }
}
