<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Address State Provider - Fetches customer address data.
 *
 * SECURITY: Customers can only access their own addresses.
 * Admins can access any customer's addresses.
 */
final class AddressProvider extends \Maho\ApiPlatform\Provider
{
    public function __construct(\Symfony\Bundle\SecurityBundle\Security $security)
    {
        parent::__construct($security);
    }

    /**
     * Provide address data based on operation type
     *
     * @return TraversablePaginator<Address>|Address|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Address|null
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName() ?? '';

        // Handle GraphQL operations
        if ($operationName === 'my') {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new NotFoundHttpException('Authentication required');
            }
            $this->authorizeCustomerAccess($customerId);
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new NotFoundHttpException('Customer not found');
            }
            return $this->getCollection($customer);
        }

        // Item lookups: ownership is not checked here. The read operations'
        // `is_owner(object, 'customerId')` expression denies post-read (and maps
        // to 404), and the write processor re-verifies ownership itself.
        if (!$operation instanceof CollectionOperationInterface) {
            return $this->getItem((int) ($uriVariables['id'] ?? 0));
        }

        // Collections: derive the target customer and verify access, which is
        // their only guard (a paginator can't be ownership-checked post-read).
        $customerId = (int) ($uriVariables['customerId'] ?? 0);
        if (!$customerId) {
            $customerId = (int) $this->getAuthenticatedCustomerId();
        }

        if (!$customerId) {
            throw new NotFoundHttpException('Customer ID is required');
        }

        $this->authorizeCustomerAccess($customerId);

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        return $this->getCollection($customer);
    }

    /**
     * Get a single address by ID
     */
    private function getItem(int $addressId): ?Address
    {
        if (!$addressId) {
            return null;
        }

        $address = \Mage::getModel('customer/address')->load($addressId);
        if (!$address->getId()) {
            return null;
        }

        $customer = \Mage::getModel('customer/customer')->load((int) $address->getCustomerId());
        if (!$customer->getId()) {
            return null;
        }

        return $this->mapToDto($address, $customer);
    }

    /**
     * Get all addresses for a customer
     *
     * @return TraversablePaginator<Address>
     */
    private function getCollection(\Mage_Customer_Model_Customer $customer): TraversablePaginator
    {
        $addresses = [];

        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapToDto($address, $customer);
        }

        $total = count($addresses);

        return new TraversablePaginator(new \ArrayIterator($addresses), 1, max($total, 50), $total);
    }

    /**
     * Map Maho address model to Address DTO
     */
    public function mapToDto(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer): Address
    {
        return Address::fromCustomerAddress($address, $customer);
    }
}
