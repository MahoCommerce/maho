<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Totals_Item extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    /**
     * Determine display parameters before rendering HTML
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();

        $this->setCanDisplayTotalPaid($this->getParentBlock()->getCanDisplayTotalPaid());
        $this->setCanDisplayTotalRefunded($this->getParentBlock()->getCanDisplayTotalRefunded());
        $this->setCanDisplayTotalDue($this->getParentBlock()->getCanDisplayTotalDue());

        return $this;
    }

    /**
     * Initialize totals object
     *
     * @return $this
     */
    public function initTotals()
    {
        /** @var Mage_Sales_Block_Order_Totals $parent */
        $parent = $this->getParentBlock();

        $total = new \Maho\DataObject([
            'code'      => $this->getNameInLayout(),
            'block_name' => $this->getNameInLayout(),
            'area'      => $this->getDisplayArea(),
            'strong'    => $this->getStrong(),
        ]);
        if ($this->getBeforeCondition()) {
            $parent->addTotalBefore($total, $this->getBeforeCondition());
        } else {
            $parent->addTotal($total, $this->getAfterCondition());
        }
        return $this;
    }

    /**
     * Price HTML getter
     *
     * @param float $baseAmount
     * @param float $amount
     * @return string
     */
    public function displayPrices($baseAmount, $amount)
    {
        /** @var Mage_Adminhtml_Helper_Sales $helper */
        $helper = $this->helper('adminhtml/sales');
        return $helper->displayPrices($this->getOrder(), $baseAmount, $amount);
    }

    /**
     * Price attribute HTML getter
     *
     * @param string $code
     * @param bool $strong
     * @param string $separator
     * @return string
     */
    public function displayPriceAttribute($code, $strong = false, $separator = '<br>')
    {
        /** @var Mage_Adminhtml_Helper_Sales $helper */
        $helper = $this->helper('adminhtml/sales');
        return $helper->displayPriceAttribute($this->getSource(), $code, $strong, $separator);
    }

    /**
     * Source order getter
     *
     * @return Mage_Sales_Model_Order
     */
    #[\Override]
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }
}
