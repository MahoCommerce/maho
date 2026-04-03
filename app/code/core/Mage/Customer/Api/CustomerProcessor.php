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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Mage\Sales\Api\AccountTokenService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer State Processor - Handles customer mutations for API Platform
 */
final class CustomerProcessor extends \Maho\ApiPlatform\Processor
{
    private readonly CustomerMapper $customerMapper;
    private readonly CustomerService $customerService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->customerMapper = new CustomerMapper();
        $this->customerService = new CustomerService();
    }

    /**
     * Ensure no customer exists with the given email in the specified website
     *
     * @throws ConflictHttpException if a customer with this email already exists
     */
    private function ensureEmailUnique(#[\SensitiveParameter]
        string $email, int $websiteId): void
    {
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if ($existingCustomer->getId()) {
            throw new ConflictHttpException('A customer with this email already exists');
        }
    }

    private function checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): void
    {
        if (\Mage::helper('core')->isRateLimitExceeded(false, true, $key, $maxAttempts, $windowSeconds)) {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(
                (string) $windowSeconds,
                'Too many requests. Please try again later.',
            );
        }
    }

    /**
     * Process customer mutations
     */
    #[\Override]
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

        // Handle REST POST aliases for auth routes
        if ($operationName === 'forgot_password_rest') {
            return $this->forgotPassword($context);
        }
        if ($operationName === 'reset_password_rest') {
            return $this->resetPassword($context);
        }
        if ($operationName === 'create_from_order') {
            return $this->createAccountFromOrder($context);
        }

        // Handle REST POST /customers (create customer / registration)
        if ($operation instanceof Post && !in_array($operationName, ['change_password', 'forgot_password_rest', 'reset_password_rest', 'create_from_order'])) {
            return $this->createCustomer($data, $context);
        }

        // Handle GraphQL mutations
        return match ($operationName) {
            'createCustomerQuick' => $this->createCustomerQuick($context),
            'customerLogin' => $this->customerLogin($context),
            'customerLogout' => $this->customerLogout($context),
            'updateCustomer' => $this->updateCustomerGraphQl($context),
            'changePassword' => $this->changePasswordGraphQl($context),
            'forgotPassword' => $this->forgotPassword($context),
            'resetPassword' => $this->resetPassword($context),
            default => $data instanceof Customer ? $data : new Customer(),
        };
    }

    /**
     * Update current customer profile (REST entry point)
     */
    private function updateProfile(Customer $data): Customer
    {
        return $this->doUpdateProfile(
            firstName: $data->firstName,
            lastName: $data->lastName,
            email: $data->email !== '' ? $data->email : null,
        );
    }

    /**
     * Change current customer password (REST entry point)
     */
    private function changePassword(Customer $data): Customer
    {
        return $this->doChangePassword(
            currentPassword: $data->currentPassword ?? '',
            newPassword: $data->newPassword ?? '',
        );
    }

    /**
     * Create a new customer (registration)
     */
    private function createCustomer(Customer $data, array $context): Customer
    {
        StoreContext::ensureStore();
        $this->checkRateLimit('create_customer:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 3600);
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $coreHelper = \Mage::helper('core');

        // Validate required fields using Maho validation helpers
        if (!$coreHelper->isValidNotBlank($data->email)) {
            throw new BadRequestHttpException('Email is required');
        }
        if (!$coreHelper->isValidEmail($data->email)) {
            throw new BadRequestHttpException('Invalid email address');
        }
        if (!$coreHelper->isValidNotBlank($data->password ?? '')) {
            throw new BadRequestHttpException('Password is required');
        }

        $minPasswordLength = \Mage::getModel('customer/customer')->getMinPasswordLength();
        if (!$coreHelper->isValidLength($data->password, $minPasswordLength)) {
            throw new BadRequestHttpException("Password must be at least {$minPasswordLength} characters");
        }

        $this->ensureEmailUnique($data->email, $websiteId);

        // Create customer
        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($storeId);
        $customer->setEmail($data->email);
        $customer->setFirstname($data->firstName ?? '');
        $customer->setLastname($data->lastName ?? '');
        $customer->setPassword($data->password);
        $customer->setGroupId((int) (\Mage::getStoreConfig('customer/create_account/default_group') ?: 1));

        try {
            $customer->save();
        } catch (\Exception $e) {
            // Some admin observers may fail in API context - check if customer was actually saved
            if ($customer->getId()) {
                // Customer was saved successfully, observer error is non-critical
                \Mage::log('Non-critical observer error during customer save: ' . $e->getMessage(), \Mage::LOG_WARNING);
            } else {
                \Mage::logException($e);
                throw new \RuntimeException('Failed to create customer');
            }
        }

        // Return the created customer (without password)
        return $this->customerMapper->mapToDto($customer);
    }

    /**
     * Quick customer creation for POS (GraphQL mutation)
     */
    private function createCustomerQuick(array $context): Customer
    {
        $args = $context['args']['input'] ?? [];

        StoreContext::ensureStore();
        $this->checkRateLimit('create_customer:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 3600);
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $email = $args['email'] ?? '';
        $firstName = $args['firstName'] ?? '';
        $lastName = $args['lastName'] ?? '';
        $telephone = $args['telephone'] ?? null;

        if (empty($email)) {
            throw new BadRequestHttpException('Email is required');
        }

        if (!\Mage::helper('core')->isValidEmail($email)) {
            throw new BadRequestHttpException('A valid email address is required');
        }

        $this->ensureEmailUnique($email, $websiteId);

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
            \Mage::logException($e);
            throw new \RuntimeException('Failed to create customer');
        }

        return $this->customerMapper->mapToDto($customer);
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

        $this->checkRateLimit('graphql_login:email:' . strtolower($email), 5, 60);

        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId);

        try {
            $customer->authenticate($email, $password);
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException('Invalid email or password');
        }

        return $this->customerMapper->mapToDto($customer);
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
     * Update customer profile via GraphQL mutation
     */
    private function updateCustomerGraphQl(array $context): Customer
    {
        $args = $context['args']['input'] ?? [];

        return $this->doUpdateProfile(
            firstName: $args['firstName'] ?? null,
            lastName: $args['lastName'] ?? null,
            email: $args['email'] ?? null,
        );
    }

    /**
     * Change password via GraphQL mutation
     */
    private function changePasswordGraphQl(array $context): Customer
    {
        $args = $context['args']['input'] ?? [];

        return $this->doChangePassword(
            currentPassword: $args['currentPassword'] ?? '',
            newPassword: $args['newPassword'] ?? '',
        );
    }

    /**
     * Shared logic for updating customer profile (used by both REST and GraphQL)
     */
    private function doUpdateProfile(?string $firstName, ?string $lastName, #[\SensitiveParameter]
        ?string $email): Customer
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $customer = $this->customerService->getCustomerById($customerId);
        if (!$customer) {
            throw new AccessDeniedHttpException('Customer not found');
        }

        $data = array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
        ], fn($v) => $v !== null);

        try {
            $customer = $this->customerService->updateCustomer($customer, $data);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->customerMapper->mapToDto($customer);
    }

    /**
     * Shared logic for changing customer password (used by both REST and GraphQL)
     */
    private function doChangePassword(string $currentPassword, string $newPassword): Customer
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($currentPassword)) {
            throw new BadRequestHttpException('Current password is required');
        }
        if (!$coreHelper->isValidNotBlank($newPassword)) {
            throw new BadRequestHttpException('New password is required');
        }

        $minPasswordLength = \Mage::getModel('customer/customer')->getMinPasswordLength();
        if (!$coreHelper->isValidLength($newPassword, $minPasswordLength)) {
            throw new BadRequestHttpException("New password must be at least {$minPasswordLength} characters");
        }

        $customer = $this->customerService->getCustomerById($customerId);
        if (!$customer) {
            throw new AccessDeniedHttpException('Customer not found');
        }

        try {
            $this->customerService->changePassword($customer, $currentPassword, $newPassword);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->customerMapper->mapToDto($customer);
    }

    /**
     * Send forgot password email
     */
    private function forgotPassword(array $context): Customer
    {
        StoreContext::ensureStore();
        $args = $context['args']['input'] ?? [];
        $email = $args['email'] ?? '';

        if (empty($email)) {
            throw new BadRequestHttpException('Email is required');
        }

        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        // Always return success to prevent email enumeration
        if ($customer->getId()) {
            try {
                $customer->sendPasswordResetConfirmationEmail();
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        // Return empty customer DTO (no customer data leaked)
        $dto = new Customer();
        $dto->email = $email;
        return $dto;
    }

    /**
     * Reset password with token
     */
    private function resetPassword(array $context): Customer
    {
        StoreContext::ensureStore();
        $args = $context['args']['input'] ?? [];
        $email = $args['email'] ?? '';
        $resetToken = $args['resetToken'] ?? '';
        $newPassword = $args['newPassword'] ?? '';

        if (empty($email) || empty($resetToken) || empty($newPassword)) {
            throw new BadRequestHttpException('Email, reset token, and new password are required');
        }

        $minPasswordLength = \Mage::getModel('customer/customer')->getMinPasswordLength();
        if (!\Mage::helper('core')->isValidLength($newPassword, $minPasswordLength)) {
            throw new BadRequestHttpException("New password must be at least {$minPasswordLength} characters");
        }

        try {
            $this->customerService->resetPassword($email, $resetToken, $newPassword);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $dto = new Customer();
        $dto->email = $email;
        return $dto;
    }

    /**
     * Create customer account from a placed guest order using HMAC-signed accountToken
     */
    private function createAccountFromOrder(array $context): Customer
    {
        $request = $context['request'] ?? null;
        $body = $request ? (json_decode($request->getContent(), true) ?? []) : [];
        $args = $context['args']['input'] ?? $body;

        $accountToken = $args['accountToken'] ?? '';
        $password = $args['password'] ?? '';

        if (!$accountToken || !$password) {
            throw new BadRequestHttpException('Account token and password are required.');
        }

        if (strlen($password) < 6) {
            throw new BadRequestHttpException('Password must be at least 6 characters.');
        }

        try {
            $tokenData = AccountTokenService::verify($accountToken, 3600);
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($tokenData['action'] !== 'create_account') {
            throw new BadRequestHttpException('Invalid token action.');
        }

        $orderId = $tokenData['orderId'];
        $email = $tokenData['email'];

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId() || $order->getCustomerEmail() !== $email) {
            throw new NotFoundHttpException('Order not found.');
        }

        $websiteId = (int) \Mage::app()->getStore($order->getStoreId())->getWebsiteId();
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if ($existingCustomer->getId()) {
            throw new HttpException(409, 'An account with this email already exists. Please log in.');
        }

        $billingAddress = $order->getBillingAddress();

        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($order->getStoreId());
        $customer->setEmail($email);
        $customer->setFirstname($billingAddress->getFirstname());
        $customer->setLastname($billingAddress->getLastname());
        $customer->setPassword($password);
        $customer->save();

        if ($billingAddress) {
            $customerAddress = \Mage::getModel('customer/address');
            $customerAddress->setCustomerId($customer->getId());
            $customerAddress->setFirstname($billingAddress->getFirstname());
            $customerAddress->setLastname($billingAddress->getLastname());
            $customerAddress->setCompany($billingAddress->getCompany());
            $customerAddress->setStreet($billingAddress->getStreet());
            $customerAddress->setCity($billingAddress->getCity());
            $customerAddress->setRegionId($billingAddress->getRegionId());
            $customerAddress->setRegion($billingAddress->getRegion());
            $customerAddress->setPostcode($billingAddress->getPostcode());
            $customerAddress->setCountryId($billingAddress->getCountryId());
            $customerAddress->setTelephone($billingAddress->getTelephone());
            $customerAddress->setIsDefaultBilling(true);
            $customerAddress->setIsDefaultShipping(true);
            $customerAddress->save();
        }

        $order->setCustomerId($customer->getId());
        $order->setCustomerIsGuest(0);
        $order->setCustomerFirstname($customer->getFirstname());
        $order->setCustomerLastname($customer->getLastname());
        $order->save();

        return (new CustomerMapper())->mapToDto($customer);
    }
}
