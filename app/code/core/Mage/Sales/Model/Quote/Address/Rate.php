<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Quote_Address_Rate _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address_Rate getResource()
 * @method Mage_Sales_Model_Resource_Quote_Address_Rate_Collection getCollection()
 */
class Mage_Sales_Model_Quote_Address_Rate extends Mage_Shipping_Model_Rate_Abstract
{
    protected $_address;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_address_rate');
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if ($this->getAddress()) {
            $this->setAddressId($this->getAddress()->getId());
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function setAddress(Mage_Sales_Model_Quote_Address $address)
    {
        $this->_address = $address;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->_address;
    }

    /**
     * @return $this
     */
    public function importShippingRate(Mage_Shipping_Model_Rate_Result_Abstract $rate)
    {
        if ($rate instanceof Mage_Shipping_Model_Rate_Result_Error) {
            $this
                ->setCode($rate->getCarrier() . '_error')
                ->setCarrier($rate->getCarrier())
                ->setCarrierTitle($rate->getCarrierTitle())
                ->setErrorMessage($rate->getErrorMessage())
            ;
        } elseif ($rate instanceof Mage_Shipping_Model_Rate_Result_Method) {
            $this
                ->setCode($rate->getCarrier() . '_' . $rate->getMethod())
                ->setCarrier($rate->getCarrier())
                ->setCarrierTitle($rate->getCarrierTitle())
                ->setMethod($rate->getMethod())
                ->setMethodTitle($rate->getMethodTitle())
                ->setMethodDescription($rate->getMethodDescription())
                ->setMethodLogo($rate->getMethodLogo())
                ->setPrice($rate->getPrice())
            ;
        }
        return $this;
    }

    public function getAddressId(): ?int
    {
        $value = $this->getData('address_id');
        return $value === null ? null : (int) $value;
    }

    public function setAddressId(?int $value): static
    {
        return $this->setData('address_id', $value);
    }

    public function getCarrierTitle(): ?string
    {
        $value = $this->getData('carrier_title');
        return $value === null ? null : (string) $value;
    }

    public function setCarrierTitle(?string $value): static
    {
        return $this->setData('carrier_title', $value);
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData('error_message');
        return $value === null ? null : (string) $value;
    }

    public function setErrorMessage(?string $value): static
    {
        return $this->setData('error_message', $value);
    }

    public function getMethod(): ?string
    {
        $value = $this->getData('method');
        return $value === null ? null : (string) $value;
    }

    public function setMethod(?string $value): static
    {
        return $this->setData('method', $value);
    }

    public function getMethodDescription(): ?string
    {
        $value = $this->getData('method_description');
        return $value === null ? null : (string) $value;
    }

    public function setMethodDescription(?string $value): static
    {
        return $this->setData('method_description', $value);
    }

    public function getMethodTitle(): ?string
    {
        $value = $this->getData('method_title');
        return $value === null ? null : (string) $value;
    }

    public function setMethodTitle(?string $value): static
    {
        return $this->setData('method_title', $value);
    }

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
