<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Quote_Address_Total_Shipping extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function __construct()
    {
        $this->setCode('shipping');
    }

    /**
     * Collect totals information about shipping
     *
     * @return  Mage_Sales_Model_Quote_Address_Total_Shipping
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

        $address->collectWeight();

        if (!count($this->_getAddressItems($address))) {
            return $this;
        }

        $address->collectShippingRates();

        $this->_setAmount(0)
            ->_setBaseAmount(0);

        $method = $address->getShippingMethod();

        if ($method) {
            foreach ($address->getAllShippingRates() as $rate) {
                if ($rate->getCode() == $method) {
                    $amountPrice = $address->getQuote()->getStore()->convertPrice($rate->getPrice(), false);
                    $this->_setAmount($amountPrice);
                    $this->_setBaseAmount($rate->getPrice());
                    $shippingDescription = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
                    $address->setShippingDescription(trim($shippingDescription, ' -'));
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Add shipping totals information to address object
     *
     * @return $this
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $amount = $address->getShippingAmount();
        if ($amount != 0 || $address->getShippingDescription()) {
            $title = Mage::helper('sales')->__('Shipping & Handling');
            if ($address->getShippingDescription()) {
                $title .= ' (' . $address->getShippingDescription() . ')';
            }
            $address->addTotal([
                'code' => $this->getCode(),
                'title' => $title,
                'value' => $address->getShippingAmount(),
            ]);
        }
        return $this;
    }

    /**
     * Get Shipping label
     *
     * @return string
     */
    #[\Override]
    public function getLabel()
    {
        return Mage::helper('sales')->__('Shipping');
    }
}
