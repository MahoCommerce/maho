<?php

/**
 * Copy the values of user-defined customer address attributes to quote and order addresses.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * Each user-defined customer address attribute has a column with its code in the quote address
 * table and in the order address table. A quote address keeps the raw value, as the customer
 * address does. An order address keeps the label of a select or a multiselect, so the order keeps
 * what the customer saw after an option changes.
 */
class Mage_Sales_Model_Observer_AddressAttributes
{
    /** @var array<string, Mage_Customer_Model_Attribute>|null */
    protected ?array $attributes = null;

    /**
     * Refuse a new address attribute whose code is a column that the sales schema declares.
     */
    #[Maho\Config\Observer('customer_entity_attribute_save_before', id: 'sales_address_attribute_code')]
    public function validateCode(\Maho\Event\Observer $observer): void
    {
        $attribute = $observer->getEvent()->getAttribute();
        if (!$this->isUserAddressAttribute($attribute) || !$attribute->isObjectNew()) {
            return;
        }
        $code = (string) $attribute->getAttributeCode();
        if (in_array($code, $this->getDeclaredColumns(), true)) {
            Mage::throwException(Mage::helper('sales')->__('The code "%s" is a column of the order addresses. Use another code.', $code));
        }
    }

    /**
     * Add the columns of a user-defined address attribute to the quote and order address tables.
     */
    #[Maho\Config\Observer('customer_entity_attribute_save_commit_after', id: 'sales_address_attribute_columns')]
    public function addColumns(\Maho\Event\Observer $observer): void
    {
        $attribute = $observer->getEvent()->getAttribute();
        if (!$this->isUserAddressAttribute($attribute)) {
            return;
        }
        static::addAddressColumns($attribute);
        $this->attributes = null;
    }

    /**
     * Add the quote and order address columns of an attribute, when they do not exist.
     */
    public static function addAddressColumns(Mage_Eav_Model_Entity_Attribute $attribute): void
    {
        $code = (string) $attribute->getAttributeCode();
        $comment = 'Address attribute ' . $code;
        $setup = new Mage_Sales_Model_Resource_Setup('sales_setup');
        $setup->addAttribute('quote_address', $code, ['type' => (string) $attribute->getBackendType(), 'comment' => $comment]);
        $setup->addAttribute('order_address', $code, [
            'type' => static::hasOptions($attribute) ? 'text' : (string) $attribute->getBackendType(),
            'comment' => $comment,
        ]);
        $connection = $setup->getConnection();
        $connection->resetDdlCache($setup->getTable('sales/quote_address'));
        $connection->resetDdlCache($setup->getTable('sales/order_address'));
    }

    #[Maho\Config\Observer('core_copy_fieldset_customer_address_to_quote_address', id: 'sales_address_attribute_copy')]
    public function copyCustomerToQuote(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget(), false);
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_quote_address_to_customer_address', id: 'sales_address_attribute_copy')]
    public function copyQuoteToCustomer(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget(), false);
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_quote_address_to_order_address', id: 'sales_address_attribute_copy')]
    public function copyQuoteToOrder(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget(), true);
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_order_address_to_quote_address', id: 'sales_address_attribute_copy')]
    public function copyOrderToQuote(\Maho\Event\Observer $observer): void
    {
        $source = $observer->getEvent()->getSource();
        $target = $observer->getEvent()->getTarget();
        if (!$source instanceof \Maho\DataObject || !$target instanceof \Maho\DataObject) {
            return;
        }
        foreach ($this->getAttributes() as $code => $attribute) {
            $value = $source->getData($code);
            if ($value === null) {
                continue;
            }
            $target->setData($code, static::hasOptions($attribute) ? $this->labelsToValue($attribute, (string) $value) : $value);
        }
    }

    protected function copy(mixed $source, mixed $target, bool $toOrder): void
    {
        if (!$source instanceof \Maho\DataObject || !$target instanceof \Maho\DataObject) {
            return;
        }
        foreach ($this->getAttributes() as $code => $attribute) {
            if (!$source->hasData($code)) {
                continue;
            }
            $value = $source->getData($code);
            if (is_array($value)) {
                $value = implode($attribute->getFrontendInput() === 'multiline' ? "\n" : ',', $value);
            }
            if ($toOrder && $value !== null && static::hasOptions($attribute)) {
                $value = $this->valueToLabels($attribute, (string) $value);
            }
            $target->setData($code, $value);
        }
    }

    /**
     * @return array<string, Mage_Customer_Model_Attribute>
     */
    protected function getAttributes(): array
    {
        if ($this->attributes === null) {
            $this->attributes = [];
            $collection = Mage::getResourceModel('customer/address_attribute_collection')
                ->addFieldToFilter('is_user_defined', 1);
            foreach ($collection as $attribute) {
                $this->attributes[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }
        return $this->attributes;
    }

    protected function isUserAddressAttribute(mixed $attribute): bool
    {
        return $attribute instanceof Mage_Eav_Model_Entity_Attribute
            && $attribute->getIsUserDefined()
            && $attribute->getEntityType()->getEntityTypeCode() === 'customer_address';
    }

    protected static function hasOptions(Mage_Eav_Model_Entity_Attribute $attribute): bool
    {
        return in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true);
    }

    protected function valueToLabels(Mage_Eav_Model_Entity_Attribute $attribute, string $value): string
    {
        $labels = [];
        foreach (explode(',', $value) as $id) {
            $label = $attribute->getSource()->getOptionText($id);
            $labels[] = is_string($label) && $label !== '' ? $label : $id;
        }
        return implode(', ', $labels);
    }

    protected function labelsToValue(Mage_Eav_Model_Entity_Attribute $attribute, string $labels): ?string
    {
        $ids = [];
        foreach (array_map(trim(...), explode(',', $labels)) as $label) {
            $id = $attribute->getSource()->getOptionId($label);
            if ($id !== null && $id !== '') {
                $ids[] = (string) $id;
            }
        }
        return $ids ? implode(',', $ids) : null;
    }

    /**
     * Return the columns that the sales schema declares on the quote and order address tables.
     *
     * @return list<string>
     */
    protected function getDeclaredColumns(): array
    {
        [$schema] = \Maho\Db\Schema\Collector::collect();
        $prefix = \Maho\Db\Schema\Collector::tablePrefix();
        $columns = [];
        foreach (['sales_flat_quote_address', 'sales_flat_order_address'] as $table) {
            foreach ($schema->getTable($prefix . $table)->getColumns() as $column) {
                $columns[] = $column->getObjectName()->getIdentifier()->getValue();
            }
        }
        return $columns;
    }
}
