<?php

/**
 * Copy the values of user-defined customer attributes to quotes and orders.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * Each user-defined customer attribute has a column customer_<code> in the quote table and in the
 * order table, as the platform fields customer_dob and customer_taxvat have. Both keep the raw value,
 * so a reorder copies it back as it is, and a customer who registers at checkout gets it from the
 * quote. An order also keeps the option labels in customer_attribute_labels, in the language of the
 * store of the order, so the order shows what the customer saw after an option changes.
 */
class Mage_Sales_Model_Observer_CustomerAttributes
{
    public const COLUMN_PREFIX = 'customer_';

    /**
     * The customer forms of the storefront. Mage\Customer\Api\CustomAttributes::STOREFRONT_FORMS
     * has the same list, but the API classes load only in the API.
     */
    public const STOREFRONT_FORMS = ['customer_account_create', 'customer_account_edit', 'checkout_register'];

    /** @var array<string, Mage_Customer_Model_Attribute>|null */
    protected ?array $attributes = null;

    /**
     * Refuse a new customer attribute whose column is a column of the quotes or the orders: a column
     * that the sales schema declares, such as customer_note, or the column of a deleted attribute.
     */
    #[Maho\Config\Observer('customer_entity_attribute_save_before', id: 'sales_customer_attribute_code')]
    public function validateCode(\Maho\Event\Observer $observer): void
    {
        $attribute = $observer->getEvent()->getAttribute();
        // isObjectNew() stays true after the first save of an attribute, so a second save looks new too
        if (!$this->isUserCustomerAttribute($attribute) || $attribute->getId()) {
            return;
        }
        $column = self::COLUMN_PREFIX . $attribute->getAttributeCode();
        $resource = Mage::getSingleton('core/resource');
        if (in_array($column, \Maho\Db\Schema\Collector::declaredColumns('sales_flat_quote', 'sales_flat_order'), true)
            || $resource->getConnection('core_read')->tableColumnExists($resource->getTableName('sales/order'), $column)
        ) {
            Mage::throwException(Mage::helper('sales')->__('The orders already have the column "%s". Use another code.', $column));
        }
    }

    /**
     * Add the quote and order columns of a user-defined customer attribute.
     */
    #[Maho\Config\Observer('customer_entity_attribute_save_commit_after', id: 'sales_customer_attribute_columns')]
    public function addColumns(\Maho\Event\Observer $observer): void
    {
        $attribute = $observer->getEvent()->getAttribute();
        if (!$this->isUserCustomerAttribute($attribute)) {
            return;
        }
        static::addOrderColumns($attribute);
        $this->attributes = null;
    }

    /**
     * Add the quote and order columns of an attribute, when they do not exist.
     */
    public static function addOrderColumns(Mage_Eav_Model_Entity_Attribute $attribute): void
    {
        $column = self::COLUMN_PREFIX . $attribute->getAttributeCode();
        $definition = ['type' => (string) $attribute->getBackendType(), 'comment' => 'Customer attribute ' . $attribute->getAttributeCode()];
        $setup = new Mage_Sales_Model_Resource_Setup('sales_setup');
        $setup->addAttribute('quote', $column, $definition);
        $setup->addAttribute('order', $column, $definition);
        $connection = $setup->getConnection();
        $connection->resetDdlCache($setup->getTable('sales/quote'));
        $connection->resetDdlCache($setup->getTable('sales/order'));
    }

    #[Maho\Config\Observer('core_copy_fieldset_customer_account_to_quote', id: 'sales_customer_attribute_copy')]
    public function copyCustomerToQuote(\Maho\Event\Observer $observer): void
    {
        $customer = $observer->getEvent()->getSource();
        $quote = $observer->getEvent()->getTarget();
        if (!$customer instanceof \Maho\DataObject || !$quote instanceof \Maho\DataObject) {
            return;
        }
        foreach (array_keys($this->getAttributes()) as $code) {
            $value = static::asText($customer->getData($code));
            $quote->setData(self::COLUMN_PREFIX . $code, $value === '' ? null : $value);
        }
    }

    /**
     * Give a customer who registers at checkout the values that the billing step kept on the quote.
     */
    #[Maho\Config\Observer('core_copy_fieldset_checkout_onepage_quote_to_customer', id: 'sales_customer_attribute_copy')]
    public function copyQuoteToCustomer(\Maho\Event\Observer $observer): void
    {
        $quote = $observer->getEvent()->getSource();
        $customer = $observer->getEvent()->getTarget();
        if (!$quote instanceof \Maho\DataObject || !$customer instanceof \Maho\DataObject) {
            return;
        }
        foreach (array_keys($this->getAttributes()) as $code) {
            $value = $quote->getData(self::COLUMN_PREFIX . $code);
            if ($value !== null && $value !== '') {
                $customer->setData($code, $value);
            }
        }
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_convert_quote_to_order', id: 'sales_customer_attribute_copy')]
    public function copyQuoteToOrder(\Maho\Event\Observer $observer): void
    {
        $quote = $observer->getEvent()->getSource();
        $order = $observer->getEvent()->getTarget();
        if (!$quote instanceof Mage_Sales_Model_Quote || !$order instanceof Mage_Sales_Model_Order) {
            return;
        }
        $this->copyColumns($quote, $order);
        $order->setCustomerAttributeLabels($this->getOptionLabels($order, (int) $quote->getStoreId()));
    }

    #[Maho\Config\Observer('core_copy_fieldset_sales_copy_order_to_edit', id: 'sales_customer_attribute_copy')]
    public function copyOrderToQuote(\Maho\Event\Observer $observer): void
    {
        $order = $observer->getEvent()->getSource();
        $quote = $observer->getEvent()->getTarget();
        if ($order instanceof \Maho\DataObject && $quote instanceof \Maho\DataObject) {
            $this->copyColumns($order, $quote);
        }
    }

    /**
     * Return the label and the value of each user-defined attribute that $order holds, in the order
     * of the attributes. A dropdown, a multiselect and a Yes/No give the labels that the customer saw.
     * With $storefrontOnly, only the attributes that a storefront form shows, because the document
     * goes to the customer.
     *
     * @return list<array{code: string, label: string, value: string}>
     */
    public function getDisplayValues(Mage_Sales_Model_Order $order, bool $storefrontOnly): array
    {
        $labels = $order->getCustomerAttributeLabels();
        $rows = [];
        foreach ($this->getAttributes() as $code => $attribute) {
            $value = static::asText($order->getData(self::COLUMN_PREFIX . $code));
            if ($value === '' || !$attribute->getIsVisible()
                || ($storefrontOnly && !array_intersect((array) $attribute->getUsedInForms(), self::STOREFRONT_FORMS))
            ) {
                continue;
            }
            if (isset($labels[$code])) {
                $value = is_array($labels[$code]) ? implode(', ', $labels[$code]) : (string) $labels[$code];
            } elseif ($attribute->getFrontendInput() === 'date') {
                $value = Mage::helper('core')->formatDate($value, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
            }
            $rows[] = ['code' => $code, 'label' => (string) $attribute->getStoreLabel((int) $order->getStoreId()), 'value' => $value];
        }
        return $rows;
    }

    protected function copyColumns(\Maho\DataObject $source, \Maho\DataObject $target): void
    {
        foreach (array_keys($this->getAttributes()) as $code) {
            $column = self::COLUMN_PREFIX . $code;
            if ($source->hasData($column)) {
                $target->setData($column, $source->getData($column));
            }
        }
    }

    protected static function asText(mixed $value): string
    {
        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    /**
     * Return the option labels of the select, multiselect and boolean values of $order, in store $storeId.
     *
     * @return array<string, string|list<string>>
     */
    protected function getOptionLabels(Mage_Sales_Model_Order $order, int $storeId): array
    {
        $labels = [];
        foreach ($this->getAttributes() as $code => $attribute) {
            $value = static::asText($order->getData(self::COLUMN_PREFIX . $code));
            if ($value === '' || !in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true)) {
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
            $codes = Mage::getResourceModel('customer/attribute_collection')
                ->addFieldToFilter('is_user_defined', 1)
                ->addFieldToFilter('frontend_input', ['nin' => ['file', 'image']])
                ->setOrder('sort_order', 'ASC')
                ->getColumnValues('attribute_code');
            foreach ($codes as $code) {
                $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', $code);
                if ($attribute instanceof Mage_Customer_Model_Attribute && $attribute->getId()) {
                    $this->attributes[(string) $code] = $attribute;
                }
            }
        }
        return $this->attributes;
    }

    protected function isUserCustomerAttribute(mixed $attribute): bool
    {
        return $attribute instanceof Mage_Eav_Model_Entity_Attribute
            && $attribute->getIsUserDefined()
            && $attribute->getEntityType()->getEntityTypeCode() === 'customer';
    }
}
