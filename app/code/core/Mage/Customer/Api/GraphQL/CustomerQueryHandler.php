<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Customer\Api\GraphQL;

use Mage\Customer\Api\CustomerProvider;
use Mage\Customer\Api\CustomerService;
use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;

/**
 * Customer Query Handler
 *
 * Handles all customer-related GraphQL operations for admin API.
 * Uses CustomerProvider::mapToDto() for model-based mapping to ensure
 * events (api_customer_dto_build) and extensions fire consistently.
 */
class CustomerQueryHandler
{
    private CustomerService $customerService;
    private CustomerProvider $customerProvider;

    public function __construct(CustomerService $customerService, CustomerProvider $customerProvider)
    {
        $this->customerService = $customerService;
        $this->customerProvider = $customerProvider;
    }

    /**
     * Handle searchCustomers query
     */
    public function handleSearchCustomers(array $variables): array
    {
        $telephone = $variables['telephone'] ?? null;
        $email = $variables['email'] ?? null;
        $search = $variables['search'] ?? $variables['query'] ?? '';
        $page = $variables['page'] ?? 1;
        $pageSize = $variables['pageSize'] ?? 20;

        // Pass telephone and email separately to service
        $result = $this->customerService->searchCustomers($search, $email, $telephone, $page, $pageSize);
        $customers = $result['customers'] ?? [];
        $edges = array_map(fn($c) => ['node' => $this->mapCustomer($c)], $customers);
        return ['customers' => [
            'edges' => $edges,
            'items' => array_map([$this, 'mapCustomer'], $customers),
            'total' => $result['total'] ?? 0,
        ]];
    }

    /**
     * Handle getCustomer query
     */
    public function handleGetCustomer(array $variables): array
    {
        $id = $variables['id'] ?? $variables['customerId'] ?? null;
        if (!$id) {
            throw ValidationException::requiredField('customerId');
        }
        $customer = $this->customerService->getCustomerById((int) $id);
        return ['customer' => $customer ? $this->mapCustomer($customer) : null];
    }

    /**
     * Handle createCustomer mutation
     */
    public function handleCreateCustomer(array $variables): array
    {
        $input = $variables['input'] ?? $variables;

        $email = $input['email'] ?? null;
        $firstName = $input['firstName'] ?? null;
        $lastName = $input['lastName'] ?? null;

        if (!$email || !$firstName || !$lastName) {
            throw ValidationException::requiredField('email, firstName, lastName');
        }

        $this->ensureEmailUnique($email);

        // Create new customer
        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId(\Mage::app()->getWebsite()->getId());
        $customer->setStore(\Mage::app()->getStore());
        $customer->setEmail($email);
        $customer->setFirstname($firstName);
        $customer->setLastname($lastName);
        $customer->setGroupId($input['groupId'] ?? 1);

        // Set optional telephone
        if (!empty($input['telephone'])) {
            // Store phone in billing address
            $customer->setData('telephone', $input['telephone']);
        }

        // Generate random password (customer can reset via email)
        $password = \Mage::helper('core')->getRandomString(12);
        $customer->setPassword($password);

        try {
            $customer->save();

            // If telephone provided, create a billing address
            if (!empty($input['telephone'])) {
                $address = \Mage::getModel('customer/address');
                $address->setCustomerId($customer->getId())
                    ->setFirstname($firstName)
                    ->setLastname($lastName)
                    ->setTelephone($input['telephone'])
                    ->setStreet($input['street'] ?? 'TBC')
                    ->setCity($input['city'] ?? 'TBC')
                    ->setPostcode($input['postcode'] ?? '0000')
                    ->setCountryId($input['countryId'] ?? \Maho\ApiPlatform\Service\StoreDefaults::getCountryId())
                    ->setIsDefaultBilling(true)
                    ->setIsDefaultShipping(true);
                $address->save();
            }

            return ['createCustomer' => [
                'customer' => $this->mapCustomer($customer),
                'success' => true,
            ]];
        } catch (\Exception $e) {
            throw ValidationException::invalidValue('customer', 'failed to create: ' . $e->getMessage());
        }
    }

    /**
     * Handle updateCustomerAddress mutation
     */
    public function handleUpdateCustomerAddress(array $variables): array
    {
        $customerId = $variables['customerId'] ?? null;
        $input = $variables['input'] ?? $variables;

        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw NotFoundException::customer();
        }

        // Get or create default billing address
        $billingAddressId = $customer->getDefaultBilling();
        if ($billingAddressId) {
            $address = \Mage::getModel('customer/address')->load($billingAddressId);
        } else {
            $address = \Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId());
            $address->setFirstname($customer->getFirstname());
            $address->setLastname($customer->getLastname());
            $address->setIsDefaultBilling(true);
            $address->setIsDefaultShipping(true);
        }

        // Update customer email if provided (email is on customer, not address)
        if (isset($input['email']) && $input['email'] !== $customer->getEmail()) {
            $customer->setEmail($input['email']);
            $customer->save();
        }

        // Update address fields
        if (isset($input['street'])) {
            $address->setStreet($input['street']);
        }
        if (isset($input['city'])) {
            $address->setCity($input['city']);
        }
        if (isset($input['postcode'])) {
            $address->setPostcode($input['postcode']);
        }
        if (isset($input['telephone'])) {
            $address->setTelephone($input['telephone']);
        }
        if (isset($input['countryId'])) {
            $address->setCountryId($input['countryId']);
        } elseif (!$address->getCountryId()) {
            $address->setCountryId(\Maho\ApiPlatform\Service\StoreDefaults::getCountryId());
        }

        try {
            $address->save();

            // Reload customer to get updated address
            $customer = \Mage::getModel('customer/customer')->load($customerId);

            return ['updateCustomerAddress' => [
                'success' => true,
                'customer' => $this->mapCustomer($customer),
            ]];
        } catch (\Exception $e) {
            throw ValidationException::invalidValue('address', 'failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Ensure no customer exists with the given email in the current website
     *
     * @throws ValidationException if a customer with this email already exists
     */
    private function ensureEmailUnique(#[\SensitiveParameter]
        string $email): void
    {
        $existing = $this->customerService->getCustomerByEmail($email);
        if ($existing) {
            throw ValidationException::invalidValue('email', 'a customer with this email already exists');
        }
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
