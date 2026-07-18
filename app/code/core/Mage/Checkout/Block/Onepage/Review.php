<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Block_Onepage_Review extends Mage_Checkout_Block_Onepage_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->getCheckout()->setStepData('review', [
            'label'     => Mage::helper('checkout')->__('Order Review'),
            'is_show'   => $this->isShow(),
        ]);
        parent::_construct();

        $this->getQuote()->collectTotals()->save();
    }
}
