<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

/**
 * Tax Total Row Renderer
 */
class Mage_Tax_Block_Checkout_Tax extends Mage_Checkout_Block_Total_Default
{
    /**
     * Template used in the block
     *
     * @var string
     */
    protected $_template = 'tax/checkout/tax.phtml';

    /**
     * The factory instance to get helper
     *
     * @var Mage_Core_Model_Factory|null
     */
    protected $_factory;

    /**
     * Initialize factory instance
     */
    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
    }

    /**
     * Get all FPTs
     *
     * @return array
     */
    public function getAllWeee()
    {
        $allWeee = [];
        $store = $this->getTotal()->getAddress()->getQuote()->getStore();
        /** @var Mage_Weee_Helper_Data $helper */
        $helper = $this->_factory->getHelper('weee');

        if (!$helper->includeInSubtotal($store)) {
            foreach ($this->getTotal()->getAddress()->getCachedItemsAll() as $item) {
                foreach ($helper->getApplied($item) as $tax) {
                    $weeeDiscount = $tax['weee_discount'] ?? 0;
                    $title = $tax['title'];
                    $rowAmount = $tax['row_amount'] ?? 0;
                    $rowAmountInclTax = $tax['row_amount_incl_tax'] ?? 0;
                    $amountDisplayed = ($helper->isTaxIncluded()) ? $rowAmountInclTax : $rowAmount;
                    if (array_key_exists($title, $allWeee)) {
                        $allWeee[$title] = $allWeee[$title] + $amountDisplayed - $weeeDiscount;
                    } else {
                        $allWeee[$title] = $amountDisplayed - $weeeDiscount;
                    }
                }
            }
        }
        return $allWeee;
    }
}
