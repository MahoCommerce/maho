<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Restriction_Rule_Condition_Customer extends Mage_Rule_Model_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_customer');
        $this->loadAttributeOptions();
        $this->loadOperatorOptions();
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    #[\Override]
    public function loadAttributeOptions()
    {
        $attributes = [
            'group_id' => Mage::helper('payment')->__('Customer Group'),
            'email' => Mage::helper('payment')->__('Email'),
            'created_at' => Mage::helper('payment')->__('Registration Date'),
            'is_active' => Mage::helper('payment')->__('Is Active'),
            'gender' => Mage::helper('payment')->__('Gender'),
            'dob' => Mage::helper('payment')->__('Date of Birth'),
            'orders_complete_count' => Mage::helper('payment')->__('Number of Complete Orders'),
        ];

        // Add customer EAV attributes that are user-defined and visible
        $customerAttributes = Mage::getResourceModel('customer/attribute_collection')
            ->addFieldToFilter('is_user_defined', 1)
            ->addFieldToFilter('is_visible', 1)
            ->load();

        foreach ($customerAttributes as $attribute) {
            if ($attribute->getFrontendLabel()) {
                $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
            }
        }

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): \Maho\Data\Form\Element\AbstractElement
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);
        return $element;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'group_id', 'gender', 'is_active' => 'select',
            'created_at', 'dob' => 'date',
            'email' => 'string',
            'orders_complete_count' => 'numeric',
            default => $this->_getAttributeInputType(),
        };
    }

    /**
     * Get value element type
     */
    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'group_id', 'gender', 'is_active' => 'select',
            'created_at', 'dob' => 'date',
            default => 'text',
        };
    }

    /**
     * Get value select options
     */
    #[\Override]
    public function getValueSelectOptions(): array
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                'group_id' => Mage::getModel('adminhtml/system_config_source_customer_group')
                    ->toOptionArray(),
                'gender' => [
                    ['value' => '1', 'label' => Mage::helper('customer')->__('Male')],
                    ['value' => '2', 'label' => Mage::helper('customer')->__('Female')],
                ],
                'is_active' => [
                    ['value' => '1', 'label' => Mage::helper('customer')->__('Active')],
                    ['value' => '0', 'label' => Mage::helper('customer')->__('Inactive')],
                ],
                default => $this->_getAttributeSelectOptions(),
            };
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    /**
     * Get input type for customer attribute
     */
    protected function _getAttributeInputType(): string
    {
        $attribute = $this->getCustomerAttribute();
        if ($attribute) {
            return match ($attribute->getFrontendInput()) {
                'select', 'multiselect' => 'select',
                'date' => 'date',
                'boolean' => 'boolean',
                default => 'string',
            };
        }
        return 'string';
    }

    /**
     * Get select options for customer attribute
     */
    protected function _getAttributeSelectOptions(): array
    {
        $attribute = $this->getCustomerAttribute();
        if ($attribute && $attribute->usesSource()) {
            return $attribute->getSource()->getAllOptions();
        }
        return [];
    }

    protected function getCustomerAttribute(): ?Mage_Customer_Model_Attribute
    {
        $attribute = Mage::getSingleton('eav/config')
            ->getAttribute('customer', $this->getAttribute());

        return ($attribute instanceof Mage_Customer_Model_Attribute) ? $attribute : null;
    }

    protected function getCustomerCompleteOrderCount(int $customerId): int
    {
        if (!$customerId) {
            return 0;
        }

        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_COMPLETE);

        return $collection->getSize();
    }

    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        // Get customer from quote or use directly if passed
        if ($object instanceof Mage_Customer_Model_Customer) {
            $customer = $object;
        } elseif ($object instanceof Mage_Sales_Model_Quote) {
            $customer = $object->getCustomer();
            if (!$customer || !$customer->getId()) {
                // For guest customers, we can only validate certain attributes
                if (in_array($this->getAttribute(), ['group_id'])) {
                    $value = $object->getCustomerGroupId();
                } else {
                    return false; // Can't validate customer attributes for guests
                }
            } else {
                // Handle special runtime attributes
                if ($this->getAttribute() === 'orders_complete_count') {
                    $value = $this->getCustomerCompleteOrderCount($customer->getId());
                } else {
                    $value = $customer->getData($this->getAttribute());
                }
            }
        } else {
            // Try to get customer from the object
            $customer = $object->getCustomer();
            if (!$customer) {
                return false;
            }
            // Handle special runtime attributes
            if ($this->getAttribute() === 'orders_complete_count') {
                $value = $this->getCustomerCompleteOrderCount($customer->getId());
            } else {
                $value = $customer->getData($this->getAttribute());
            }
        }

        if (isset($value)) {
            return $this->validateAttribute($value);
        }

        // If customer exists, get the value
        if (isset($customer) && $customer instanceof Mage_Customer_Model_Customer && $customer->getId()) {
            // Handle special runtime attributes
            if ($this->getAttribute() === 'orders_complete_count') {
                $value = $this->getCustomerCompleteOrderCount($customer->getId());
            } else {
                $value = $customer->getData($this->getAttribute());
            }
            return $this->validateAttribute($value);
        }

        return false;
    }
}
