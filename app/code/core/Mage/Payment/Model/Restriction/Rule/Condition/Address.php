<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule address condition
 */
class Mage_Payment_Model_Restriction_Rule_Condition_Address extends Mage_Rule_Model_Condition_Abstract
{
    /**
     * Fallback form instance
     */
    protected ?Varien_Data_Form $_form = null;

    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_address');
        $this->loadAttributeOptions();
        $this->loadOperatorOptions();
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $attributes = [
            'base_subtotal' => Mage::helper('payment')->__('Subtotal'),
            'total_qty' => Mage::helper('payment')->__('Total Items Quantity'),
            'weight' => Mage::helper('payment')->__('Total Weight'),
            'payment_method' => Mage::helper('payment')->__('Payment Method'),
            'shipping_method' => Mage::helper('payment')->__('Shipping Method'),
            'postcode' => Mage::helper('payment')->__('Shipping Postcode'),
            'region' => Mage::helper('payment')->__('Shipping Region'),
            'region_id' => Mage::helper('payment')->__('Shipping State/Province'),
            'country_id' => Mage::helper('payment')->__('Shipping Country'),
        ];

        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Get attribute element
     *
     * @return Varien_Data_Form_Element_Abstract
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);
        return $element;
    }

    /**
     * Get input type
     *
     * @return string
     */
    public function getInputType()
    {
        return match ($this->getAttribute()) {
            'base_subtotal', 'weight', 'total_qty' => 'numeric',
            'shipping_method', 'payment_method', 'country_id', 'region_id' => 'select',
            default => 'string',
        };
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        return match ($this->getAttribute()) {
            'shipping_method', 'payment_method', 'country_id', 'region_id' => 'select',
            default => 'text',
        };
    }

    /**
     * Get value select options
     *
     * @return array
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                'country_id' => Mage::getModel('adminhtml/system_config_source_country')
                    ->toOptionArray(),
                'region_id' => Mage::getModel('adminhtml/system_config_source_allregion')
                    ->toOptionArray(),
                'shipping_method' => Mage::getModel('adminhtml/system_config_source_shipping_allmethods')
                    ->toOptionArray(),
                'payment_method' => Mage::getModel('adminhtml/system_config_source_payment_allmethods')
                    ->toOptionArray(),
                default => [],
            };
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    /**
     * Override getForm to provide fallback when rule is not available
     */
    public function getForm()
    {
        if ($this->getRule()) {
            return $this->getRule()->getForm();
        }

        // Fallback: create a basic form if rule is not available
        if (!$this->_form) {
            $this->_form = new Varien_Data_Form();
        }
        return $this->_form;
    }

    /**
     * Validate Address Rule Condition
     *
     * @return bool
     */
    public function validate(Varien_Object $object)
    {
        $address = $object;
        if (!$address instanceof Mage_Sales_Model_Quote_Address) {
            // If object is a quote, use it directly
            if ($object instanceof Mage_Sales_Model_Quote) {
                $quote = $object;
            } else {
                // Otherwise try to get quote from object
                $quote = $object->getQuote();
                if (!$quote) {
                    return false;
                }
            }

            if ($quote->isVirtual()) {
                $address = $quote->getBillingAddress();
            } else {
                $address = $quote->getShippingAddress();
            }
        }

        return $this->validateAttribute($address->getData($this->getAttribute()));
    }
}
