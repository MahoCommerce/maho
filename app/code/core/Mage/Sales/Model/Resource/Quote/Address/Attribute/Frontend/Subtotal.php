<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Quote address attribute frontend subtotal resource model
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend_Subtotal extends Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend
{
    /**
     * Add total
     *
     * @return $this
     */
    #[\Override]
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        $address->addTotal([
            'code'  => 'subtotal',
            'title' => Mage::helper('sales')->__('Subtotal'),
            'value' => $address->getSubtotal()
        ]);

        return $this;
    }
}
