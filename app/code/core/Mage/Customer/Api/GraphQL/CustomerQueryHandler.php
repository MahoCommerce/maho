<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api\GraphQL;

use Mage\Customer\Api\Address;
use Mage\Customer\Api\Customer;
use Mage\Customer\Api\CustomerProvider;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\AdminAcl;

/**
 * Customer Query Handler.
 *
 * Handles all customer-related GraphQL operations for admin API.
 * Uses CustomerProvider::mapToDto() for model-based mapping to ensure
 * events (api_customer_dto_build) and extensions fire consistently.
 */
class CustomerQueryHandler
{
    private readonly \Mage_Customer_Service_Customer $customerService;

    public function __construct(private CustomerProvider $customerProvider)
    {
        $this->customerService = \Mage::getService('customer/customer');
    }

    /**
     * Handle searchCustomers query
     */
    public function handleSearchCustomers(array $variables): array
    {
        AdminAcl::checkResource(Customer::class);
        $telephone = $variables['telephone'] ?? null;
        $email = $variables['email'] ?? null;
        $search = $variables['search'] ?? $variables['query'] ?? '';
        $page = $variables['page'] ?? 1;
        $pageSize = $variables['pageSize'] ?? 20;

        // Scoped to the caller's website allowlist like the REST collection
        $result = $this->customerService->search(
            $search,
            $email,
            $telephone,
            $page,
            $pageSize,
            websiteIds: $this->customerProvider->allowedWebsiteIdsForCaller(),
        );
        $customers = $result['customers'] ?? [];
        $edges = array_map(fn($c) => ['node' => $this->mapCustomer($c)], $customers);
        return ['customers' => [
            'edges' => $edges,
            'items' => array_map($this->mapCustomer(...), $customers),
            'total' => $result['total'] ?? 0,
        ]];
    }

    /**
     * Handle getCustomer query
     */
    public function handleGetCustomer(array $variables): array
    {
        AdminAcl::checkResource(Customer::class);
        $id = $variables['id'] ?? $variables['customerId'] ?? null;
        if (!$id) {
            throw ValidationException::requiredField('customerId');
        }
        $customer = $this->customerService->getById((int) $id);
        if ($customer) {
            $this->customerProvider->assertCustomerWebsiteAllowed($customer);
        }
        return ['customer' => $customer ? $this->mapCustomer($customer) : null];
    }

    /**
     * Handle createCustomer mutation. The customer gets a random password and sets their own with a password reset.
     */
    public function handleCreateCustomer(array $variables): array
    {
        AdminAcl::checkResource(Customer::class);
        $input = $variables['input'] ?? $variables;

        $email = $input['email'] ?? null;
        $firstName = $input['firstName'] ?? null;
        $lastName = $input['lastName'] ?? null;
        if (!$email || !$firstName || !$lastName) {
            throw ValidationException::requiredField('email, firstName, lastName');
        }

        $customer = \Mage::getModel('customer/customer')
            ->setEmail($email)
            ->setFirstname($firstName)
            ->setLastname($lastName);
        if (isset($input['groupId'])) {
            $customer->setGroupId((int) $input['groupId']);
        }
        if (!empty($input['telephone'])) {
            $customer->addAddress($this->fillAddress(\Mage::getModel('customer/address'), $customer, $input));
        }
        $this->customerService->create($customer, (int) \Mage::app()->getStore()->getId());

        return ['createCustomer' => [
            'customer' => $this->mapCustomer($customer),
            'success' => true,
        ]];
    }

    /**
     * Handle updateCustomerAddress mutation: change the email of the customer and its default billing address,
     * or create that address.
     */
    public function handleUpdateCustomerAddress(array $variables): array
    {
        AdminAcl::checkResource(Address::class);
        $customerId = (int) ($variables['customerId'] ?? 0);
        $input = $variables['input'] ?? $variables;
        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }

        $customer = $this->customerService->requireById($customerId);
        $this->customerProvider->assertCustomerWebsiteAllowed($customer);
        if (isset($input['email'])) {
            $this->customerService->save($customer->setEmail($input['email']));
        }

        $billingId = (int) $customer->getDefaultBilling();
        $address = $billingId
            ? \Mage::getService('customer/address')->getByCustomer($billingId, $customerId)
            : \Mage::getModel('customer/address')->setCustomerId($customerId)->setIsDefaultBilling(true)->setIsDefaultShipping(true);
        \Mage::getService('customer/address')->save($this->fillAddress($address, $customer, $input));

        return ['updateCustomerAddress' => [
            'success' => true,
            'customer' => $this->mapCustomer($this->customerService->requireById($customerId)),
        ]];
    }

    /**
     * Write the address fields of $input into $address. A new address gets the name of the customer, and a
     * placeholder for each field that a point of sale does not ask, so the address skips the validation.
     */
    private function fillAddress(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer, array $input): \Mage_Customer_Model_Address
    {
        if (!$address->getId()) {
            $address->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setStreet('TBC')
                ->setCity('TBC')
                ->setPostcode('0000')
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true);
        }
        foreach (['street', 'city', 'postcode', 'telephone'] as $field) {
            if (isset($input[$field])) {
                $address->setData($field, $input[$field]);
            }
        }
        $address->setCountryId($input['countryId'] ?? ($address->getCountryId() ?: \Maho\ApiPlatform\Service\StoreDefaults::getCountryId()));
        return $address->setShouldIgnoreValidation(true);
    }

    /**
     * Map customer model to array using the Provider DTO.
     * Fires api_customer_dto_build and includes extensions.
     */
    private function mapCustomer(\Mage_Customer_Model_Customer $customer): array
    {
        return $this->customerProvider->mapToDto($customer)->toArray();
    }
}
