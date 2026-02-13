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

namespace Maho\ApiPlatform\Service\GraphQL;

use Maho\ApiPlatform\Exception\NotFoundException;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Service\CustomerService;

/**
 * Customer Query Handler
 *
 * Handles all customer-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class CustomerQueryHandler
{
    private CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
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

        // Check if customer already exists
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getWebsite()->getId())
            ->loadByEmail($email);

        if ($existingCustomer->getId()) {
            throw ValidationException::invalidValue('email', 'a customer with this email already exists');
        }

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
                    ->setCountryId($input['countryId'] ?? 'AU')
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
            $address->setCountryId('AU');
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
     * Handle getCategories query
     */
    public function handleGetCategories(array $variables, array $context): array
    {
        $storeId = $context['store_id'] ?? 1;
        \Mage::app()->setCurrentStore($storeId);

        $parentId = $variables['parentId'] ?? null;
        $maxDepth = $variables['maxDepth'] ?? 3;
        $includeInactive = $variables['includeInactive'] ?? false;

        // Get root category for the store if no parent specified
        if ($parentId === null) {
            $rootCategoryId = \Mage::app()->getStore($storeId)->getRootCategoryId();
            $parentId = $rootCategoryId;
        }

        $collection = \Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['name', 'is_active', 'position', 'level', 'children_count', 'image'])
            ->addFieldToFilter('path', ['like' => "%/{$parentId}/%"])
            ->addFieldToFilter('level', ['lteq' => $maxDepth + 1])
            ->setOrder('position', 'ASC');

        if (!$includeInactive) {
            $collection->addFieldToFilter('is_active', 1);
        }

        $categories = [];
        foreach ($collection as $category) {
            // Skip root category itself
            if ($category->getId() == $parentId) {
                continue;
            }

            $categories[] = [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'parentId' => (int) $category->getParentId(),
                'level' => (int) $category->getLevel(),
                'position' => (int) $category->getPosition(),
                'isActive' => (bool) $category->getIsActive(),
                'childrenCount' => (int) $category->getChildrenCount(),
                'path' => $category->getPath(),
                'image' => $category->getImageUrl() ?: null,
            ];
        }

        return ['categories' => $categories];
    }

    /**
     * Map customer to response array
     *
     * @param \Mage_Customer_Model_Customer $customer
     */
    public function mapCustomer($customer): array
    {
        $address = null;
        $telephone = null;
        $billingAddressId = $customer->getDefaultBilling();

        if ($billingAddressId) {
            $addressModel = \Mage::getModel('customer/address')->load($billingAddressId);
            if ($addressModel->getId()) {
                $telephone = $addressModel->getTelephone();
                $address = [
                    'id' => (int) $addressModel->getId(),
                    'street' => $addressModel->getStreet(),
                    'city' => $addressModel->getCity(),
                    'postcode' => $addressModel->getPostcode(),
                    'region' => $addressModel->getRegion(),
                    'regionId' => $addressModel->getRegionId() ? (int) $addressModel->getRegionId() : null,
                    'countryId' => $addressModel->getCountryId(),
                    'telephone' => $telephone,
                ];
            }
        }

        return [
            'id' => (int) $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'fullName' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'groupId' => (int) $customer->getGroupId(),
            'telephone' => $telephone,
            'defaultBillingAddress' => $address,
            'createdAt' => $customer->getCreatedAt(),
            'updatedAt' => $customer->getUpdatedAt(),
        ];
    }
}
