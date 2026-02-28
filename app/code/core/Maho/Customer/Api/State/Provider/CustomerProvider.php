<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Customer\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Service\CustomerService;
use Maho\Customer\Api\Resource\Customer;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\AddressMapper;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Customer State Provider - Fetches customer data for API Platform
 *
 * SECURITY: Customers can only access their own data.
 * Collection listing requires ROLE_ADMIN.
 *
 * @implements ProviderInterface<Customer>
 */
final class CustomerProvider implements ProviderInterface
{
    use AuthenticationTrait;

    private AddressMapper $addressMapper;
    private CustomerService $customerService;

    public function __construct(Security $security)
    {
        $this->addressMapper = new AddressMapper();
        $this->customerService = new CustomerService();
        $this->security = $security;
    }

    /**
     * Provide customer data based on operation type
     *
     * @return ArrayPaginator<Customer>|Customer|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|Customer|null
    {
        $operationName = $operation->getName();

        // Handle 'me' query - get current authenticated customer
        if ($operationName === 'me') {
            $customerId = $this->getAuthenticatedCustomerId();
            return $customerId ? $this->getItem($customerId) : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            // SECURITY: Only admins and API users with customers/read can list customers
            if (!$this->isAdmin() && !$this->isApiUser()) {
                $this->requireAdmin('Listing all customers requires admin or API user access');
            }
            return $this->getCollection($context);
        }

        // Single customer lookup - admins and API users can access any, customers only their own
        $requestedId = (int) $uriVariables['id'];
        if (!$this->isApiUser()) {
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
     * @return ArrayPaginator<Customer>
     */
    private function getCollection(array $context): ArrayPaginator
    {
        // GraphQL passes args in 'args', REST uses 'filters'
        $filters = $context['args'] ?? $context['filters'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = max(1, min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 15), 100)); // Max 100 for search results
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
            return new ArrayPaginator(items: [], currentPage: $page, itemsPerPage: $pageSize, totalItems: 0);
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

        return new ArrayPaginator(
            items: $customers,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: (int) ($result['total'] ?? count($customers)),
        );
    }

    // TODO: Extract customer mapping to a shared CustomerMapper service to eliminate duplication with CustomerProcessor/CustomerProvider
    /**
     * Map Maho customer model to Customer DTO (full version with all addresses)
     */
    private function mapToDto(\Mage_Customer_Model_Customer $customer): Customer
    {
        $dto = new Customer();
        $dto->id = (int) $customer->getId();
        $dto->email = $customer->getEmail();
        $dto->firstName = $customer->getFirstname();
        $dto->lastName = $customer->getLastname();
        $dto->fullName = trim(($customer->getFirstname() ?? '') . ' ' . ($customer->getLastname() ?? ''));
        $subscriber = \Mage::getModel('newsletter/subscriber')->loadByCustomer($customer);
        $dto->isSubscribed = $subscriber->isSubscribed();
        $dto->groupId = (int) $customer->getGroupId();
        $dto->createdAt = $customer->getCreatedAt();
        $dto->updatedAt = $customer->getUpdatedAt();

        $dto->addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addressDto = $this->addressMapper->fromCustomerAddress($address);

            // Track default billing/shipping on the address DTO
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
    private function mapToDtoForSearch(
        \Mage_Customer_Model_Customer $customer,
        array $defaultBillingIds,
        array $addressMap,
    ): Customer {
        $dto = new Customer();
        $dto->id = (int) $customer->getId();
        $dto->email = $customer->getEmail();
        $dto->firstName = $customer->getFirstname();
        $dto->lastName = $customer->getLastname();
        $dto->fullName = trim(($customer->getFirstname() ?? '') . ' ' . ($customer->getLastname() ?? ''));
        $dto->groupId = (int) $customer->getGroupId();

        // Only include default billing address from pre-loaded map
        $customerId = (int) $customer->getId();
        if (isset($defaultBillingIds[$customerId])) {
            $addressId = $defaultBillingIds[$customerId];
            if (isset($addressMap[$addressId])) {
                $addressDto = $this->addressMapper->fromCustomerAddress($addressMap[$addressId]);
                $addressDto->isDefaultBilling = true;
                $dto->defaultBillingAddress = $addressDto;
            }
        }

        \Mage::dispatchEvent('api_customer_dto_build', ['customer' => $customer, 'dto' => $dto]);


        return $dto;
    }

}
