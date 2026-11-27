<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

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
            $this->setAgreements(Mage::getService('checkout/agreement')->getActive(
                (int) Mage::app()->getStore()->getId(),
                $this->getLocation(),
            ));
        }
        return $this->getData('agreements');
    }

    /**
     * The place where the block shows, a Mage_Checkout_Model_Agreement::LOCATION_* value. A layout sets it.
     */
    public function getLocation(): string
    {
        return (string) ($this->getData('location') ?: Mage_Checkout_Model_Agreement::LOCATION_CHECKOUT);
    }

    public function setLocation(string $value): static
    {
        return $this->setData('location', $value);
    }

    public function setAgreements(array|Mage_Checkout_Model_Resource_Agreement_Collection|null $value): static
    {
        return $this->setData('agreements', $value);
    }
}
