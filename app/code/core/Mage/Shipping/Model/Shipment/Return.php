<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
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
        return $this->getData('shipper_contact_person_name');
    }

    public function setShipperContactPersonName(?string $value): static
    {
        return $this->setData('shipper_contact_person_name', $value);
    }

    public function getShipperContactPersonFirstName(): ?string
    {
        return $this->getData('shipper_contact_person_first_name');
    }

    public function setShipperContactPersonFirstName(?string $value): static
    {
        return $this->setData('shipper_contact_person_first_name', $value);
    }

    public function getShipperContactPersonLastName(): ?string
    {
        return $this->getData('shipper_contact_person_last_name');
    }

    public function setShipperContactPersonLastName(?string $value): static
    {
        return $this->setData('shipper_contact_person_last_name', $value);
    }

    public function getShipperContactCompanyName(): ?string
    {
        return $this->getData('shipper_contact_company_name');
    }

    public function setShipperContactCompanyName(?string $value): static
    {
        return $this->setData('shipper_contact_company_name', $value);
    }

    public function getShipperContactPhoneNumber(): ?string
    {
        return $this->getData('shipper_contact_phone_number');
    }

    public function setShipperContactPhoneNumber(?string $value): static
    {
        return $this->setData('shipper_contact_phone_number', $value);
    }

    public function getShipperAddressStreet(): ?string
    {
        return $this->getData('shipper_address_street');
    }

    public function setShipperAddressStreet(?string $value): static
    {
        return $this->setData('shipper_address_street', $value);
    }

    public function getShipperAddressStreet1(): ?string
    {
        return $this->getData('shipper_address_street1');
    }

    public function setShipperAddressStreet1(?string $value): static
    {
        return $this->setData('shipper_address_street1', $value);
    }

    public function getShipperAddressStreet2(): ?string
    {
        return $this->getData('shipper_address_street2');
    }

    public function setShipperAddressStreet2(?string $value): static
    {
        return $this->setData('shipper_address_street2', $value);
    }

    public function getShipperAddressCity(): ?string
    {
        return $this->getData('shipper_address_city');
    }

    public function setShipperAddressCity(?string $value): static
    {
        return $this->setData('shipper_address_city', $value);
    }

    public function getShipperAddressStateOrProvinceCode(): ?string
    {
        return $this->getData('shipper_address_state_or_province_code');
    }

    public function setShipperAddressStateOrProvinceCode(?string $value): static
    {
        return $this->setData('shipper_address_state_or_province_code', $value);
    }

    public function getShipperAddressPostalCode(): ?string
    {
        return $this->getData('shipper_address_postal_code');
    }

    public function setShipperAddressPostalCode(?string $value): static
    {
        return $this->setData('shipper_address_postal_code', $value);
    }

    public function getShipperAddressCountryCode(): ?string
    {
        return $this->getData('shipper_address_country_code');
    }

    public function setShipperAddressCountryCode(?string $value): static
    {
        return $this->setData('shipper_address_country_code', $value);
    }

    public function getRecipientContactPersonName(): ?string
    {
        return $this->getData('recipient_contact_person_name');
    }

    public function setRecipientContactPersonName(?string $value): static
    {
        return $this->setData('recipient_contact_person_name', $value);
    }

    public function getRecipientContactPersonFirstName(): ?string
    {
        return $this->getData('recipient_contact_person_first_name');
    }

    public function setRecipientContactPersonFirstName(?string $value): static
    {
        return $this->setData('recipient_contact_person_first_name', $value);
    }

    public function getRecipientContactPersonLastName(): ?string
    {
        return $this->getData('recipient_contact_person_last_name');
    }

    public function setRecipientContactPersonLastName(?string $value): static
    {
        return $this->setData('recipient_contact_person_last_name', $value);
    }

    public function getRecipientContactCompanyName(): ?string
    {
        return $this->getData('recipient_contact_company_name');
    }

    public function setRecipientContactCompanyName(?string $value): static
    {
        return $this->setData('recipient_contact_company_name', $value);
    }

    public function getRecipientContactPhoneNumber(): ?string
    {
        return $this->getData('recipient_contact_phone_number');
    }

    public function setRecipientContactPhoneNumber(?string $value): static
    {
        return $this->setData('recipient_contact_phone_number', $value);
    }

    public function getRecipientAddressStreet(): ?string
    {
        return $this->getData('recipient_address_street');
    }

    public function setRecipientAddressStreet(?string $value): static
    {
        return $this->setData('recipient_address_street', $value);
    }

    public function getRecipientAddressStreet1(): ?string
    {
        return $this->getData('recipient_address_street1');
    }

    public function setRecipientAddressStreet1(?string $value): static
    {
        return $this->setData('recipient_address_street1', $value);
    }

    public function getRecipientAddressStreet2(): ?string
    {
        return $this->getData('recipient_address_street2');
    }

    public function setRecipientAddressStreet2(?string $value): static
    {
        return $this->setData('recipient_address_street2', $value);
    }

    public function getRecipientAddressCity(): ?string
    {
        return $this->getData('recipient_address_city');
    }

    public function setRecipientAddressCity(?string $value): static
    {
        return $this->setData('recipient_address_city', $value);
    }

    public function getRecipientAddressStateOrProvinceCode(): ?string
    {
        return $this->getData('recipient_address_state_or_province_code');
    }

    public function setRecipientAddressStateOrProvinceCode(?string $value): static
    {
        return $this->setData('recipient_address_state_or_province_code', $value);
    }

    public function getRecipientAddressPostalCode(): ?string
    {
        return $this->getData('recipient_address_postal_code');
    }

    public function setRecipientAddressPostalCode(?string $value): static
    {
        return $this->setData('recipient_address_postal_code', $value);
    }

    public function getRecipientAddressCountryCode(): ?string
    {
        return $this->getData('recipient_address_country_code');
    }

    public function setRecipientAddressCountryCode(?string $value): static
    {
        return $this->setData('recipient_address_country_code', $value);
    }

    public function getShippingMethod(): ?string
    {
        return $this->getData('shipping_method');
    }

    public function setShippingMethod(?string $value): static
    {
        return $this->setData('shipping_method', $value);
    }

    public function getPackageWeight(): ?float
    {
        return $this->getData('package_weight');
    }

    public function setPackageWeight(?float $value): static
    {
        return $this->setData('package_weight', $value);
    }
}
