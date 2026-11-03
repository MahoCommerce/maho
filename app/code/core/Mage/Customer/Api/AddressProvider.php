<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Address State Provider - Fetches customer address data.
 *
 * SECURITY: Customers can only access their own addresses.
 * Admins can access any customer's addresses.
 */
final class AddressProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return TraversablePaginator<Address>|Address|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Address|null
    {
        StoreContext::ensureStore();

        // Item lookups: ownership is not checked here. The read operations'
        // `is_owner(object, 'customerId')` expression denies post-read (and maps
        // to 404), and the write processor re-verifies ownership itself.
        if ($operation->getName() !== 'my' && !$operation instanceof CollectionOperationInterface) {
            try {
                $address = \Mage::getService('customer/address')->getById((int) ($uriVariables['id'] ?? 0));
                return $this->mapToDto($address, $this->getCustomers()->requireById((int) $address->getCustomerId()));
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                return null;
            }
        }

        // Collections: derive the target customer and verify access, which is
        // their only guard (a paginator can't be ownership-checked post-read).
        $customerId = $operation->getName() === 'my' ? 0 : (int) ($uriVariables['customerId'] ?? 0);
        $customerId = $customerId ?: $this->requireCustomerId();
        $this->assertCustomerAccess($customerId);

        $customer = $this->getCustomers()->requireById($customerId);
        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapToDto($address, $customer);
        }
        $total = count($addresses);

        return new TraversablePaginator(new \ArrayIterator($addresses), 1, max($total, 50), $total);
    }

    public function mapToDto(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer): Address
    {
        return Address::fromCustomerAddress($address, $customer);
    }

    private function getCustomers(): \Mage_Customer_Service_Customer
    {
        return \Mage::getService('customer/customer');
    }
}
