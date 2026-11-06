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
 * table and in the order address table. Both keep the raw value, as the customer address does, so
 * a reorder copies the value back as it is. An order address also keeps the option labels in
 * custom_attribute_labels, in the language of the store of the order, so the order shows what
 * the customer saw after an option changes.
 */
class Mage_Sales_Model_Observer_AddressAttributes
{
    /** @var array<string, Mage_Customer_Model_Attribute>|null */
    protected ?array $attributes = null;

    /**
     * Refuse a new address attribute whose code is a column of the order addresses: a column that
     * the sales schema declares, or the column of a deleted attribute, which keeps the values of past quotes and orders.
     */
    #[Maho\Config\Observer('customer_entity_attribute_save_before', id: 'sales_address_attribute_code')]
    public function validateCode(\Maho\Event\Observer $observer): void
    {
        $attribute = $observer->getEvent()->getAttribute();
        // isObjectNew() stays true after the first save of an attribute, so a second save looks new too
        if (!$this->isUserAddressAttribute($attribute) || $attribute->getId()) {
            return;
        }
        $code = (string) $attribute->getAttributeCode();
        $resource = Mage::getSingleton('core/resource');
        if (in_array($code, \Maho\Db\Schema\Collector::declaredColumns('sales_flat_quote_address', 'sales_flat_order_address'), true)
            || $resource->getConnection('core_read')->tableColumnExists($resource->getTableName('sales/order_address'), $code)
        ) {
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
        $setup->addAttribute('order_address', $code, ['type' => (string) $attribute->getBackendType(), 'comment' => $comment]);
        $connection = $setup->getConnection();
        $connection->resetDdlCache($setup->getTable('sales/quote_address'));
        $connection->resetDdlCache($setup->getTable('sales/order_address'));
    }

    #[Maho\Config\Observer('core_copy_fieldset_customer_address_to_quote_address', id: 'sales_address_attribute_copy')]
    public function copyCustomerToQuote(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget());
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_quote_address_to_customer_address', id: 'sales_address_attribute_copy')]
    public function copyQuoteToCustomer(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget());
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_quote_address_to_order_address', id: 'sales_address_attribute_copy')]
    public function copyQuoteToOrder(\Maho\Event\Observer $observer): void
    {
        $source = $observer->getEvent()->getSource();
        $target = $observer->getEvent()->getTarget();
        $this->copy($source, $target);
        if ($source instanceof Mage_Sales_Model_Quote_Address && $target instanceof Mage_Sales_Model_Order_Address) {
            $target->setCustomAttributeLabels($this->getOptionLabels($target, (int) $source->getQuote()?->getStoreId()));
        }
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_order_address_to_quote_address', id: 'sales_address_attribute_copy')]
    public function copyOrderToQuote(\Maho\Event\Observer $observer): void
    {
        $this->copy($observer->getEvent()->getSource(), $observer->getEvent()->getTarget());
    }

    protected function copy(mixed $source, mixed $target): void
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
            $target->setData($code, $value);
        }
    }

    /**
     * Return the option labels of the select, multiselect and boolean values of $address, in store $storeId.
     *
     * @return array<string, string|list<string>>
     */
    protected function getOptionLabels(\Maho\DataObject $address, int $storeId): array
    {
        $labels = [];
        foreach ($this->getAttributes() as $code => $attribute) {
            $value = (string) $address->getData($code);
            if ($value === '' || !static::hasOptions($attribute)) {
                continue;
            }
            // The attributes are shared, so the store of the labels is set for this read only
            $previousStoreId = $attribute->getStoreId();
            $attribute->setStoreId($storeId);
            try {
                $items = [];
                foreach (explode(',', $value) as $id) {
                    $label = $attribute->getSource()->getOptionText($id);
                    $items[] = is_string($label) && $label !== '' ? $label : $id;
                }
            } finally {
                $attribute->setStoreId($previousStoreId);
            }
            $labels[$code] = $attribute->getFrontendInput() === 'multiselect' ? $items : $items[0];
        }
        return $labels;
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
}
