<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

use Maho\Customer\Api\Resource\Address;

/**
 * Centralized address mapping service
 *
 * Converts Maho address models (order, quote, customer) to the Address DTO.
 */
class AddressMapper
{
    public function fromOrderAddress(\Mage_Sales_Model_Order_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
    }

    public function fromQuoteAddress(\Mage_Sales_Model_Quote_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
    }

    public function fromCustomerAddress(\Mage_Customer_Model_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
    }
}
