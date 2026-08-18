<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

/**
 * @package    Mage_Shipping
 */

class Mage_Shipping_Model_Shipment_Return extends \Maho\DataObject
{
    public function getOrderShipment(): ?Mage_Sales_Model_Order_Shipment
    {
        return $this->getData('order_shipment');
    }

    public function setOrderShipment(?Mage_Sales_Model_Order_Shipment $value): static
    {
        return $this->setData('order_shipment', $value);
    }

    public function getShipperContactPersonName(): ?string
    {
        $value = $this->getData('shipper_contact_person_name');
        return $value === null ? null : (string) $value;
    }

    public function setShipperContactPersonName(?string $value): static
    {
        return $this->setData('shipper_contact_person_name', $value);
    }

    public function getShipperContactPersonFirstName(): ?string
    {
        $value = $this->getData('shipper_contact_person_first_name');
        return $value === null ? null : (string) $value;
    }

    public function setShipperContactPersonFirstName(?string $value): static
    {
        return $this->setData('shipper_contact_person_first_name', $value);
    }

    public function getShipperContactPersonLastName(): ?string
    {
        $value = $this->getData('shipper_contact_person_last_name');
        return $value === null ? null : (string) $value;
    }

    public function setShipperContactPersonLastName(?string $value): static
    {
        return $this->setData('shipper_contact_person_last_name', $value);
    }

    public function getShipperContactCompanyName(): ?string
    {
        $value = $this->getData('shipper_contact_company_name');
        return $value === null ? null : (string) $value;
    }

    public function setShipperContactCompanyName(?string $value): static
    {
        return $this->setData('shipper_contact_company_name', $value);
    }

    public function getShipperContactPhoneNumber(): ?string
    {
        $value = $this->getData('shipper_contact_phone_number');
        return $value === null ? null : (string) $value;
    }

    public function setShipperContactPhoneNumber(?string $value): static
    {
        return $this->setData('shipper_contact_phone_number', $value);
    }

    public function getShipperAddressStreet(): ?string
    {
        $value = $this->getData('shipper_address_street');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressStreet(?string $value): static
    {
        return $this->setData('shipper_address_street', $value);
    }

    public function getShipperAddressStreet1(): ?string
    {
        $value = $this->getData('shipper_address_street1');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressStreet1(?string $value): static
    {
        return $this->setData('shipper_address_street1', $value);
    }

    public function getShipperAddressStreet2(): ?string
    {
        $value = $this->getData('shipper_address_street2');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressStreet2(?string $value): static
    {
        return $this->setData('shipper_address_street2', $value);
    }

    public function getShipperAddressCity(): ?string
    {
        $value = $this->getData('shipper_address_city');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressCity(?string $value): static
    {
        return $this->setData('shipper_address_city', $value);
    }

    public function getShipperAddressStateOrProvinceCode(): ?string
    {
        $value = $this->getData('shipper_address_state_or_province_code');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressStateOrProvinceCode(?string $value): static
    {
        return $this->setData('shipper_address_state_or_province_code', $value);
    }

    public function getShipperAddressPostalCode(): ?string
    {
        $value = $this->getData('shipper_address_postal_code');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressPostalCode(?string $value): static
    {
        return $this->setData('shipper_address_postal_code', $value);
    }

    public function getShipperAddressCountryCode(): ?string
    {
        $value = $this->getData('shipper_address_country_code');
        return $value === null ? null : (string) $value;
    }

    public function setShipperAddressCountryCode(?string $value): static
    {
        return $this->setData('shipper_address_country_code', $value);
    }

    public function getRecipientContactPersonName(): ?string
    {
        $value = $this->getData('recipient_contact_person_name');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientContactPersonName(?string $value): static
    {
        return $this->setData('recipient_contact_person_name', $value);
    }

    public function getRecipientContactPersonFirstName(): ?string
    {
        $value = $this->getData('recipient_contact_person_first_name');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientContactPersonFirstName(?string $value): static
    {
        return $this->setData('recipient_contact_person_first_name', $value);
    }

    public function getRecipientContactPersonLastName(): ?string
    {
        $value = $this->getData('recipient_contact_person_last_name');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientContactPersonLastName(?string $value): static
    {
        return $this->setData('recipient_contact_person_last_name', $value);
    }

    public function getRecipientContactCompanyName(): ?string
    {
        $value = $this->getData('recipient_contact_company_name');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientContactCompanyName(?string $value): static
    {
        return $this->setData('recipient_contact_company_name', $value);
    }

    public function getRecipientContactPhoneNumber(): ?string
    {
        $value = $this->getData('recipient_contact_phone_number');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientContactPhoneNumber(?string $value): static
    {
        return $this->setData('recipient_contact_phone_number', $value);
    }

    public function getRecipientAddressStreet(): ?string
    {
        $value = $this->getData('recipient_address_street');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressStreet(?string $value): static
    {
        return $this->setData('recipient_address_street', $value);
    }

    public function getRecipientAddressStreet1(): ?string
    {
        $value = $this->getData('recipient_address_street1');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressStreet1(?string $value): static
    {
        return $this->setData('recipient_address_street1', $value);
    }

    public function getRecipientAddressStreet2(): ?string
    {
        $value = $this->getData('recipient_address_street2');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressStreet2(?string $value): static
    {
        return $this->setData('recipient_address_street2', $value);
    }

    public function getRecipientAddressCity(): ?string
    {
        $value = $this->getData('recipient_address_city');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressCity(?string $value): static
    {
        return $this->setData('recipient_address_city', $value);
    }

    public function getRecipientAddressStateOrProvinceCode(): ?string
    {
        $value = $this->getData('recipient_address_state_or_province_code');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressStateOrProvinceCode(?string $value): static
    {
        return $this->setData('recipient_address_state_or_province_code', $value);
    }

    public function getRecipientAddressPostalCode(): ?string
    {
        $value = $this->getData('recipient_address_postal_code');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressPostalCode(?string $value): static
    {
        return $this->setData('recipient_address_postal_code', $value);
    }

    public function getRecipientAddressCountryCode(): ?string
    {
        $value = $this->getData('recipient_address_country_code');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientAddressCountryCode(?string $value): static
    {
        return $this->setData('recipient_address_country_code', $value);
    }

    public function getShippingMethod(): ?string
    {
        $value = $this->getData('shipping_method');
        return $value === null ? null : (string) $value;
    }

    public function setShippingMethod(?string $value): static
    {
        return $this->setData('shipping_method', $value);
    }

    public function getPackageWeight(): ?float
    {
        $value = $this->getData('package_weight');
        return $value === null ? null : (float) $value;
    }

    public function setPackageWeight(?float $value): static
    {
        return $this->setData('package_weight', $value);
    }
}
