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
        switch ($this->getAttribute()) {
            case 'base_subtotal':
            case 'weight':
            case 'total_qty':
                return 'numeric';

            case 'shipping_method':
            case 'payment_method':
            case 'country_id':
            case 'region_id':
                return 'select';
        }
        return 'string';
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        switch ($this->getAttribute()) {
            case 'shipping_method':
            case 'payment_method':
            case 'country_id':
            case 'region_id':
                return 'select';
        }
        return 'text';
    }

    /**
     * Get value select options
     *
     * @return array
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            switch ($this->getAttribute()) {
                case 'country_id':
                    $options = Mage::getModel('adminhtml/system_config_source_country')
                        ->toOptionArray();
                    break;

                case 'region_id':
                    $options = Mage::getModel('adminhtml/system_config_source_allregion')
                        ->toOptionArray();
                    break;

                case 'shipping_method':
                    $options = Mage::getModel('adminhtml/system_config_source_shipping_allmethods')
                        ->toOptionArray();
                    break;

                case 'payment_method':
                    $options = Mage::getModel('adminhtml/system_config_source_payment_allmethods')
                        ->toOptionArray();
                    break;

                default:
                    $options = [];
            }
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
     * @param Varien_Object $object
     * @return bool
     */
    public function validate(Varien_Object $object)
    {
        $address = $object;
        if (!$address instanceof Mage_Sales_Model_Quote_Address) {
            if ($object->getQuote()->isVirtual()) {
                $address = $object->getQuote()->getBillingAddress();
            } else {
                $address = $object->getQuote()->getShippingAddress();
            }
        }

        return $this->validateAttribute($address->getData($this->getAttribute()));
    }
}
