<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/**
 * Class Mage_Checkout_Block_Agreements
 *
 * @package    Mage_Checkout
 *
 * @method bool hasAgreements()
 */
class Mage_Checkout_Block_Agreements extends Mage_Core_Block_Template
{
    /**
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getAgreements()
    {
        if (!$this->hasAgreements()) {
            if (!Mage::getStoreConfigFlag('checkout/options/enable_agreements')) {
                $agreements = [];
            } else {
                $agreements = Mage::getModel('checkout/agreement')->getCollection()
                    ->addStoreFilter(Mage::app()->getStore()->getId())
                    ->addFieldToFilter('is_active', 1)
                    ->setOrder('position', \Maho\Data\Collection::SORT_ORDER_ASC);
            }
            $this->setAgreements($agreements);
        }
        return $this->getData('agreements');
    }

    public function setAgreements(array|Mage_Checkout_Model_Resource_Agreement_Collection|null $value): static
    {
        return $this->setData('agreements', $value);
    }
}
