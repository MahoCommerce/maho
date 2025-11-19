<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend_Custbalance extends Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend
{
    /**
     * Fetch customer balance
     *
     * @return $this
     */
    #[\Override]
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        $custbalance = $address->getCustbalanceAmount();
        if ($custbalance != 0) {
            $address->addTotal([
                'code'  => 'custbalance',
                'title' => Mage::helper('sales')->__('Store Credit'),
                'value' => -$custbalance,
            ]);
        }
        return $this;
    }
}
