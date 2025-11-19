<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Quote_Address_Total_Grand extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /**
     * Collect grand total address amount
     *
     * @return  Mage_Sales_Model_Quote_Address_Total_Grand
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $grandTotal     = $address->getGrandTotal();
        $baseGrandTotal = $address->getBaseGrandTotal();

        $store      = $address->getQuote()->getStore();
        $totals     = array_sum($address->getAllTotalAmounts());
        $totals     = $store->roundPrice($totals);
        $baseTotals = array_sum($address->getAllBaseTotalAmounts());
        $baseTotals = $store->roundPrice($baseTotals);

        $address->setGrandTotal($grandTotal + $totals);
        $address->setBaseGrandTotal($baseGrandTotal + $baseTotals);
        return $this;
    }

    /**
     * Add grand total information to address
     *
     * @return  Mage_Sales_Model_Quote_Address_Total_Grand
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $address->addTotal([
            'code'  => $this->getCode(),
            'title' => Mage::helper('sales')->__('Grand Total'),
            'value' => $address->getGrandTotal(),
            'area'  => 'footer',
        ]);
        return $this;
    }
}
