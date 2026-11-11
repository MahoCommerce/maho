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
 * An admin token or a service token can access the addresses of a customer of a website in its allowlist.
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
        // A backend token passes that expression for each customer, so its website allowlist applies here.
        if ($operation->getName() !== 'my' && !$operation instanceof CollectionOperationInterface) {
            try {
                $address = \Mage::getService('customer/address')->getById((int) ($uriVariables['id'] ?? 0));
                $customer = $this->getCustomers()->getById((int) $address->getCustomerId());
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                return null;
            }
            if ($this->isAdmin() || $this->isApiUser()) {
                $this->assertCustomerReachable($customer);
            }
            return $this->mapToDto($address, $customer);
        }

        // Collections: derive the target customer and verify access, which is
        // their only guard (a paginator can't be ownership-checked post-read).
        $customerId = $operation->getName() === 'my' ? 0 : (int) ($uriVariables['customerId'] ?? 0);
        $customerId = $customerId ?: $this->requireCustomerId();
        $customer = $this->getCustomers()->getById($customerId);
        $this->assertCustomerReachable($customer);
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
