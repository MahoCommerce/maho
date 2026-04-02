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
 * Centralized customer mapping service
 *
 * Converts Maho customer models to the Customer DTO.
 */
class CustomerMapper
{
    /**
     * Map a customer model to a Customer DTO (without addresses).
     */
    public function mapToDto(\Mage_Customer_Model_Customer $customer): Customer
    {
        $dto = new Customer();
        $dto->id = (int) $customer->getId();
        $dto->email = $customer->getEmail();
        $dto->firstName = $customer->getFirstname();
        $dto->lastName = $customer->getLastname();
        $dto->fullName = trim(($customer->getFirstname() ?? '') . ' ' . ($customer->getLastname() ?? ''));
        $dto->isSubscribed = (bool) $customer->getIsSubscribed();
        $dto->groupId = (int) $customer->getGroupId();
        $dto->createdAt = $customer->getCreatedAt();
        $dto->updatedAt = $customer->getUpdatedAt();
        $dto->password = null;

        return $dto;
    }
}
