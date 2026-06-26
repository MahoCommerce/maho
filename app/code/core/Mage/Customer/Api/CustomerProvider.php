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
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Customer State Provider - Fetches customer data for API Platform.
 *
 * SECURITY: Customers can only access their own data.
 * Collection listing requires ROLE_ADMIN.
 */
final class CustomerProvider extends \Maho\ApiPlatform\Provider
{
    private CustomerService $customerService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->customerService = new CustomerService();
    }

    /**
     * Provide customer data based on operation type
     *
     * @return TraversablePaginator<Customer>|Customer|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Customer|null
    {
        $operationName = $operation->getName();

        // Handle 'me' query - get current authenticated customer
        if ($operationName === 'me') {
            $customerId = $this->getAuthenticatedCustomerId();
            return $customerId ? $this->getItem($customerId) : null;
        }



        if ($operation instanceof CollectionOperationInterface) {
            // SECURITY: Only admins and API users with customers/read can list customers
            if (!$this->isAdmin()) {
                if ($this->isApiUser()) {
                    $this->requireApiPermission('customers/read');
                } else {
                    $this->requireAdmin('Listing all customers requires admin or API user access');
                }
            }
            return $this->getCollection($context);
        }

        // Single customer lookup. Admin tokens can read any customer (admins
        // are gated by Maho ACL anyway). API-user tokens must hold the
        // explicit customers/read permission to cross customer boundaries,
        // otherwise a bare service-account token would let any client_credentials
        // key dump every customer's profile + addresses. ROLE_CUSTOMER (a
        // customer's own token) falls through to authorizeCustomerAccess(),
        // which enforces self-only.
        $requestedId = (int) $uriVariables['id'];
        if ($this->isApiUser()) {
            $this->requireApiPermission('customers/read');
        } elseif (!$this->isAdmin()) {
            $this->authorizeCustomerAccess($requestedId);
        }

        return $this->getItem($requestedId);
    }

    /**
     * Get a single customer by ID
     */
    private function getItem(int $id): ?Customer
    {
        $mahoCustomer = $this->customerService->getCustomerById($id);
        return $mahoCustomer ? $this->mapToDto($mahoCustomer) : null;
    }

    /**
     * Get customer collection with pagination and search
     *
     * @return TraversablePaginator<Customer>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 15, 100);
        $filters = $context['args'] ?? $context['filters'] ?? [];
        $search = $filters['search'] ?? null;
        $email = $filters['email'] ?? null;
        $telephone = $filters['telephone'] ?? null;

        $result = $this->customerService->searchCustomers(
            search: $search ?? '',
            email: $email,
            telephone: $telephone,
            page: $page,
            pageSize: $pageSize,
        );

        if (empty($result['customers'])) {
            return new TraversablePaginator(new \ArrayIterator([]), 1, $pageSize, 0);
        }

        // Pre-load default billing addresses for all customers in a single query
        $customerIds = [];
        $defaultBillingIds = [];
        foreach ($result['customers'] as $mahoCustomer) {
            $customerId = (int) $mahoCustomer->getId();
            $customerIds[] = $customerId;
            $billingId = $mahoCustomer->getDefaultBilling();
            if ($billingId) {
                $defaultBillingIds[$customerId] = (int) $billingId;
            }
        }

        // Load all default billing addresses in one query
        $addressMap = [];
        if (!empty($defaultBillingIds)) {
            $addressCollection = \Mage::getResourceModel('customer/address_collection')
                ->addAttributeToSelect(['firstname', 'lastname', 'street', 'city', 'postcode', 'region', 'region_id', 'country_id', 'telephone', 'company'])
                ->addFieldToFilter('entity_id', ['in' => array_values($defaultBillingIds)]);

            foreach ($addressCollection as $address) {
                $addressMap[(int) $address->getId()] = $address;
            }
        }

        // Map customers with pre-loaded addresses
        $customers = [];
        foreach ($result['customers'] as $mahoCustomer) {
            $customers[] = $this->mapToDtoForSearch($mahoCustomer, $defaultBillingIds, $addressMap);
        }

        return new TraversablePaginator(new \ArrayIterator($customers), $page, $pageSize, (int) ($result['total'] ?? count($customers)));
    }

    /**
     * Map Maho customer model to Customer DTO (full version with all addresses)
     */
    public function mapToDto(\Mage_Customer_Model_Customer $customer): Customer
    {
        $dto = Customer::fromModel($customer);

        $subscriber = \Mage::getModel('newsletter/subscriber')->loadByCustomer($customer);
        $dto->isSubscribed = $subscriber->isSubscribed();

        $dto->addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addressDto = Address::fromCustomerAddress($address);

            if ($address->getId() == $customer->getDefaultBilling()) {
                $addressDto->isDefaultBilling = true;
                $dto->defaultBillingAddress = $addressDto;
            }
            if ($address->getId() == $customer->getDefaultShipping()) {
                $addressDto->isDefaultShipping = true;
                $dto->defaultShippingAddress = $addressDto;
            }

            $dto->addresses[] = $addressDto;
        }

        return $dto;
    }

    /**
     * Map Maho customer to DTO for search results (optimized - only default billing address)
     *
     * @param array<int, int> $defaultBillingIds Map of customer_id => address_id
     * @param array<int, \Mage_Customer_Model_Address> $addressMap Map of address_id => address
     */
    public function mapToDtoForSearch(
        \Mage_Customer_Model_Customer $customer,
        array $defaultBillingIds,
        array $addressMap,
    ): Customer {
        $dto = Customer::fromModel($customer);

        // Only include default billing address from pre-loaded map
        $customerId = (int) $customer->getId();
        if (isset($defaultBillingIds[$customerId])) {
            $addressId = $defaultBillingIds[$customerId];
            if (isset($addressMap[$addressId])) {
                $addressDto = Address::fromCustomerAddress($addressMap[$addressId]);
                $addressDto->isDefaultBilling = true;
                $dto->defaultBillingAddress = $addressDto;
            }
        }

        \Mage::dispatchEvent('api_customer_dto_build', ['customer' => $customer, 'dto' => $dto]);

        return $dto;
    }

}
