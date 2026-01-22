<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Customer;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Customer State Processor - Handles customer mutations for API Platform
 *
 * @implements ProcessorInterface<Customer, Customer>
 */
final class CustomerProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Process customer mutations
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Customer
    {
        $operationName = $operation->getName();

        // Handle REST PUT /customers/me (update profile)
        if ($operationName === 'update_profile') {
            return $this->updateProfile($data);
        }

        // Handle REST POST /customers/me/password (change password)
        if ($operationName === 'change_password') {
            return $this->changePassword($data);
        }

        // Handle REST POST /customers (create customer / registration)
        if ($operation instanceof Post && $operationName !== 'change_password') {
            return $this->createCustomer($data, $context);
        }

        // Handle GraphQL mutations
        return match ($operationName) {
            'createCustomerQuick' => $this->createCustomerQuick($context),
            'customerLogin' => $this->customerLogin($context),
            'customerLogout' => $this->customerLogout($context),
            default => $data instanceof Customer ? $data : new Customer(),
        };
    }

    /**
     * Update current customer profile
     */
    private function updateProfile(Customer $data): Customer
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new AccessDeniedHttpException('Customer not found');
        }

        // Update allowed fields
        if ($data->firstName !== null) {
            $customer->setFirstname($data->firstName);
        }
        if ($data->lastName !== null) {
            $customer->setLastname($data->lastName);
        }
        if ($data->email !== null && $data->email !== $customer->getEmail()) {
            // Check if new email is already in use
            $existingCustomer = \Mage::getModel('customer/customer')
                ->setWebsiteId($customer->getWebsiteId())
                ->loadByEmail($data->email);
            if ($existingCustomer->getId() && $existingCustomer->getId() != $customerId) {
                throw new BadRequestHttpException('Email is already in use');
            }
            $customer->setEmail($data->email);
        }

        try {
            $customer->save();
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to update profile: ' . $e->getMessage());
        }

        return $this->mapToDto($customer);
    }

    /**
     * Change current customer password
     */
    private function changePassword(Customer $data): Customer
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($data->currentPassword ?? '')) {
            throw new BadRequestHttpException('Current password is required');
        }
        if (!$coreHelper->isValidNotBlank($data->newPassword ?? '')) {
            throw new BadRequestHttpException('New password is required');
        }

        $minPasswordLength = (int) \Mage::getStoreConfig('customer/password/minimum_password_length') ?: 8;
        if (!$coreHelper->isValidLength($data->newPassword, $minPasswordLength)) {
            throw new BadRequestHttpException("New password must be at least {$minPasswordLength} characters");
        }

        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new AccessDeniedHttpException('Customer not found');
        }

        // Verify current password
        if (!$customer->validatePassword($data->currentPassword)) {
            throw new BadRequestHttpException('Current password is incorrect');
        }

        // Set new password
        $customer->setPassword($data->newPassword);

        try {
            $customer->save();
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to change password: ' . $e->getMessage());
        }

        return $this->mapToDto($customer);
    }

    /**
     * Create a new customer (registration)
     */
    private function createCustomer(Customer $data, array $context): Customer
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $coreHelper = \Mage::helper('core');

        // Validate required fields using Maho validation helpers
        if (!$coreHelper->isValidNotBlank($data->email ?? '')) {
            throw new BadRequestHttpException('Email is required');
        }
        if (!$coreHelper->isValidEmail($data->email)) {
            throw new BadRequestHttpException('Invalid email address');
        }
        if (!$coreHelper->isValidNotBlank($data->password ?? '')) {
            throw new BadRequestHttpException('Password is required');
        }

        $minPasswordLength = (int) \Mage::getStoreConfig('customer/password/minimum_password_length') ?: 8;
        if (!$coreHelper->isValidLength($data->password, $minPasswordLength)) {
            throw new BadRequestHttpException("Password must be at least {$minPasswordLength} characters");
        }

        // Check if email already exists
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($data->email);

        if ($existingCustomer->getId()) {
            throw new ConflictHttpException('A customer with this email already exists');
        }

        // Create customer
        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($storeId);
        $customer->setEmail($data->email);
        $customer->setFirstname($data->firstName ?? '');
        $customer->setLastname($data->lastName ?? '');
        $customer->setPassword($data->password);
        $customer->setGroupId($data->groupId ?? 1);

        try {
            $customer->save();
        } catch (\Exception $e) {
            // Some admin observers may fail in API context - check if customer was actually saved
            if ($customer->getId()) {
                // Customer was saved successfully, observer error is non-critical
                \Mage::log('Non-critical observer error during customer save: ' . $e->getMessage(), \Mage::LOG_WARNING);
            } else {
                \Mage::logException($e);
                throw new \RuntimeException('Failed to create customer: ' . $e->getMessage());
            }
        }

        // Return the created customer (without password)
        return $this->mapToDto($customer);
    }

    /**
     * Quick customer creation for POS (GraphQL mutation)
     */
    private function createCustomerQuick(array $context): Customer
    {
        $args = $context['args']['input'] ?? [];

        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $email = $args['email'] ?? '';
        $firstName = $args['firstName'] ?? '';
        $lastName = $args['lastName'] ?? '';
        $telephone = $args['telephone'] ?? null;

        if (empty($email)) {
            throw new BadRequestHttpException('Email is required');
        }

        // Check if email already exists
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if ($existingCustomer->getId()) {
            throw new ConflictHttpException('A customer with this email already exists');
        }

        // Create customer with random password
        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($storeId);
        $customer->setEmail($email);
        $customer->setFirstname($firstName);
        $customer->setLastname($lastName);
        $customer->setPassword(\Mage::helper('core')->getRandomString(16));

        try {
            $customer->save();

            // Add address with telephone if provided
            if ($telephone) {
                $address = \Mage::getModel('customer/address');
                $address->setCustomerId($customer->getId());
                $address->setFirstname($firstName);
                $address->setLastname($lastName);
                $address->setTelephone($telephone);
                $address->setStreet(['']);
                $address->setCity('');
                $address->setPostcode('');
                $address->setCountryId('AU');
                $address->setIsDefaultBilling(true);
                $address->setIsDefaultShipping(true);
                $address->save();
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create customer: ' . $e->getMessage());
        }

        return $this->mapToDto($customer);
    }

    /**
     * Customer login (GraphQL mutation)
     */
    private function customerLogin(array $context): Customer
    {
        $args = $context['args']['input'] ?? [];
        $email = $args['email'] ?? '';
        $password = $args['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new BadRequestHttpException('Email and password are required');
        }

        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if (!$customer->getId()) {
            throw new BadRequestHttpException('Invalid email or password');
        }

        // Validate password
        if (!$customer->validatePassword($password)) {
            throw new BadRequestHttpException('Invalid email or password');
        }

        return $this->mapToDto($customer);
    }

    /**
     * Customer logout (GraphQL mutation)
     */
    private function customerLogout(array $context): Customer
    {
        // For stateless API, logout is handled client-side by clearing tokens
        // Return empty customer to indicate logged out state
        return new Customer();
    }

    /**
     * Map Maho customer model to Customer DTO
     */
    private function mapToDto(\Mage_Customer_Model_Customer $customer): Customer
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
        // Password is write-only, never returned
        $dto->password = null;

        return $dto;
    }
}
