<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Customer\Api;

/**
 * Centralized address mapping service
 *
 * Converts Maho address models (order, quote, customer) to the Address DTO.
 */
class AddressMapper
{
    public function fromOrderAddress(\Mage_Sales_Model_Order_Address $address): Address
    {
        return $this->mapCommonFields(new Address(), $address);
    }

    public function fromQuoteAddress(\Mage_Sales_Model_Quote_Address $address): Address
    {
        return $this->mapCommonFields(new Address(), $address);
    }

    public function fromCustomerAddress(
        \Mage_Customer_Model_Address $address,
        ?\Mage_Customer_Model_Customer $customer = null,
    ): Address {
        $dto = $this->mapCommonFields(new Address(), $address);
        $dto->customerId = (int) $address->getCustomerId();

        if ($customer !== null) {
            $dto->isDefaultBilling = $address->getId() == $customer->getDefaultBilling();
            $dto->isDefaultShipping = $address->getId() == $customer->getDefaultShipping();
        }

        return $dto;
    }

    private function mapCommonFields(Address $dto, \Maho\DataObject $address): Address
    {
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getData('firstname') ?? '';
        $dto->lastName = $address->getData('lastname') ?? '';
        $dto->company = $address->getData('company');
        $dto->street = $address->getStreet();
        $dto->city = $address->getData('city') ?? '';
        $dto->region = $address->getData('region');
        $dto->regionId = $address->getData('region_id') ? (int) $address->getData('region_id') : null;
        $dto->postcode = $address->getData('postcode') ?? '';
        $dto->countryId = $address->getData('country_id') ?? '';
        $dto->telephone = $address->getData('telephone') ?? '';

        return $dto;
    }
}
