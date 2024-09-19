<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Used in creating options for use_in_forms selection
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Customer_Model_Config_Forms
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'adminhtml_checkout',
                'label' => Mage::helper('customer')->__('Adminhtml Checkout')
            ],
            [
                'value' => 'adminhtml_customer',
                'label' => Mage::helper('customer')->__('Adminhtml Customer')
            ],
            [
                'value' => 'checkout_register',
                'label' => Mage::helper('customer')->__('Checkout Register')
            ],
            [
                'value' => 'customer_account_create',
                'label' => Mage::helper('customer')->__('Customer Account Create')
            ],
            [
                'value' => 'customer_account_edit',
                'label' => Mage::helper('customer')->__('Customer Account Edit')
            ],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toOptionHash()
    {
        return array_combine(
            array_column($this->toOptionArray(), 'value'),
            array_column($this->toOptionArray(), 'label')
        );
    }
}
