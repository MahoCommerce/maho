<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule customer condition
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

    /**
     * Get attribute element
     *
     * @return Varien_Data_Form_Element_Abstract
     */
    #[\Override]
    public function getAttributeElement()
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
            default => $this->_getAttributeInputType(),
        };
    }

    /**
     * Get value element type
     *
     * @return string
     */
    #[\Override]
    public function getValueElementType()
    {
        return match ($this->getAttribute()) {
            'group_id', 'gender', 'is_active' => 'select',
            'created_at', 'dob' => 'date',
            default => 'text',
        };
    }

    /**
     * Get value select options
     *
     * @return array
     */
    #[\Override]
    public function getValueSelectOptions()
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
     *
     * @return string
     */
    protected function _getAttributeInputType()
    {
        $attribute = $this->_getCustomerAttribute();
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
     *
     * @return array
     */
    protected function _getAttributeSelectOptions()
    {
        $attribute = $this->_getCustomerAttribute();
        if ($attribute && $attribute->usesSource()) {
            return $attribute->getSource()->getAllOptions();
        }
        return [];
    }

    /**
     * Get customer attribute model
     *
     * @return Mage_Customer_Model_Attribute|null
     */
    protected function _getCustomerAttribute()
    {
        $attribute = Mage::getSingleton('eav/config')
            ->getAttribute('customer', $this->getAttribute());

        return ($attribute instanceof Mage_Customer_Model_Attribute) ? $attribute : null;
    }

    /**
     * Validate customer condition
     *
     * @return bool
     */
    #[\Override]
    public function validate(Varien_Object $object)
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
                $value = $customer->getData($this->getAttribute());
            }
        } else {
            // Try to get customer from the object
            $customer = $object->getCustomer();
            if (!$customer) {
                return false;
            }
            $value = $customer->getData($this->getAttribute());
        }

        if (isset($value)) {
            return $this->validateAttribute($value);
        }

        // If customer exists, get the value
        if (isset($customer) && $customer instanceof Mage_Customer_Model_Customer && $customer->getId()) {
            $value = $customer->getData($this->getAttribute());
            return $this->validateAttribute($value);
        }

        return false;
    }
}
