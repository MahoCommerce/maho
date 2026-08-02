<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Mage\Sales\Api\AccountTokenService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer State Processor - Handles customer mutations for API Platform.
 */
final class CustomerProcessor extends \Maho\ApiPlatform\Processor
{
    /**
     * Upper bound for ids stored in a SMALLINT key column (customer_group.customer_group_id,
     * core_website.website_id). PostgreSQL has no unsigned integers, so comparing such a column
     * against a larger value is a query error rather than a non-match: out-of-range ids must be
     * rejected before they reach SQL.
     */
    private const MAX_SMALLINT = 32767;

    private readonly CustomerService $customerService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
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

    /**
     * Process customer mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Customer
    {
        $operationName = $operation->getName();

        // Bridge REST request body into context args (GraphQL populates args natively).
        // The forgot/reset/create-from-order handlers read from $context['args']['input'];
        // for REST callers API Platform does not populate that key, so the JSON body is
        // injected here to keep those endpoints working over REST.
        $this->normalizeGraphQlInput($context);

        // Handle REST PUT /customers/me (update profile)
        if ($operationName === 'update_profile') {
            return $this->updateProfile($data);
        }

        // Handle REST PUT /customers/{id} (admin / service-token update)
        if ($operation instanceof Put && isset($uriVariables['id'])) {
            return $this->updateCustomerAdmin((int) $uriVariables['id'], $data);
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
            'quickCreate' => $this->createCustomerQuick($context),
            'login' => $this->customerLogin($context),
            'logout' => $this->customerLogout($context),
            'update' => $this->updateCustomerGraphQl($context),
            'changePassword' => $this->changePasswordGraphQl($context),
            'forgotPassword' => $this->forgotPassword($context),
            'resetPassword' => $this->resetPassword($context),
            default => $data instanceof Customer ? $data : new Customer(),
        };
    }

    /**
     * Update current customer profile (REST entry point).
     *
     * Admin-only fields (groupId, isActive, websiteId, taxvat,
     * disableAutoGroupChange) are deliberately never read here, so a
     * customer's own token cannot touch them.
     */
    private function updateProfile(Customer $data): Customer
    {
        return $this->doUpdateProfile(
            firstName: $data->firstname,
            lastName: $data->lastname,
            email: $data->email !== '' ? $data->email : null,
            profile: $this->extractProfileFields($data),
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

        // POST /customers is public (registration). Admin-only fields are accepted
        // only from an admin or a service token holding customers/create; anyone
        // else supplying them is rejected before the rate limiter records a hit.
        $isPrivileged = $this->canWriteAdminFields('customers/create');
        if (!$isPrivileged) {
            $this->assertNoAdminOnlyFields($data);
            $this->checkRateLimitByIp('create_customer', 'customer_register', 3600);
        }

        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        // websiteId is honored on create only: pick which website the account
        // lives on, defaulting the store to that website's default store.
        if ($data->websiteId !== null) {
            if ($data->websiteId < 0 || $data->websiteId > self::MAX_SMALLINT) {
                throw new BadRequestHttpException("Website {$data->websiteId} does not exist");
            }
            try {
                $website = \Mage::app()->getWebsite($data->websiteId);
            } catch (\Throwable) {
                throw new BadRequestHttpException("Website {$data->websiteId} does not exist");
            }
            $websiteId = (int) $website->getId();
            $defaultStore = $website->getDefaultStore();
            if ($defaultStore) {
                $storeId = (int) $defaultStore->getId();
            }
            // Only privileged callers reach here (assertNoAdminOnlyFields above),
            // so the authenticated ApiUser is guaranteed to exist.
            $this->assertWebsiteAllowed($websiteId, $this->getAuthorizedUser(), 'customer');
        }

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
        $customer->setFirstname($data->firstname ?? '');
        $customer->setLastname($data->lastname ?? '');
        $customer->setPassword($data->password);

        $groupId = (int) (\Mage::getStoreConfig('customer/create_account/default_group') ?: 1);
        if ($data->groupId !== null) {
            $this->validateGroupExists($data->groupId);
            $groupId = $data->groupId;
        }
        $customer->setGroupId($groupId);

        foreach ($this->extractProfileFields($data) as $field => $value) {
            $customer->setData($field, $value);
        }
        $this->applyAdminOnlyFields($customer, $data);
        if ($data->isActive === null) {
            $customer->setData('is_active', 1);
        }

        // Email confirmation protects self-registration; accounts created by an
        // admin or service token are trusted, mirroring the admin backend.
        if ($isPrivileged) {
            $customer->setForceConfirmed(true);
        }

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

        // Send the registration email, mirroring Mage_Customer_AccountController: the confirmation
        // link when confirmation is required, otherwise the welcome ("registered") email — without
        // it a confirmation-required store leaves the account unconfirmed and unable to log in. The
        // password is intentionally not passed: the new-account templates don't render it. The
        // account is already persisted, so a mail failure must not fail an otherwise-successful
        // creation — log it and continue. A force-confirmed (privileged) create has no pending
        // confirmation key, so it gets the welcome email even on confirmation-required stores.
        try {
            $emailType = $customer->isConfirmationRequired() && $customer->getConfirmation() ? 'confirmation' : 'registered';
            $customer->sendNewAccountEmail($emailType, '', $storeId);
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

        // Return the created customer (without password)
        return Customer::fromModel($customer);
    }

    /**
     * Quick customer creation for POS (GraphQL mutation)
     */
    private function createCustomerQuick(array $context): Customer
    {
        // With explicit args, values come directly in args (not nested under input)
        $args = $context['args']['input'] ?? $context['args'] ?? [];

        StoreContext::ensureStore();
        $this->checkRateLimitByIp('create_customer', 'customer_register', 3600);
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $email = $args['email'] ?? '';
        $firstName = $args['firstname'] ?? '';
        $lastName = $args['lastname'] ?? '';
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
                $address->setCountryId(\Mage::getStoreConfig('general/country/default', $storeId) ?: 'US');
                $address->setIsDefaultBilling(true);
                $address->setIsDefaultShipping(true);
                $address->save();
            }
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new \RuntimeException('Failed to create customer');
        }

        // Same registration email as createCustomer(): confirmation link when confirmation is
        // required, otherwise the welcome ("registered") email — without it a confirmation-required
        // store leaves the POS-created account stuck and unable to log in. The random POS password
        // is never emailed (the templates don't render it anyway); the customer sets their own via
        // password reset. A mail failure must not fail the already-created account.
        try {
            $emailType = $customer->isConfirmationRequired() ? 'confirmation' : 'registered';
            $customer->sendNewAccountEmail($emailType, '', $storeId);
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

        return Customer::fromModel($customer);
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

        // Per-IP cap first (matches the REST /auth/token endpoint), so an
        // attacker rotating email addresses can't spray the login mutation
        // beyond the IP allowance; then the tighter per-email cap.
        $this->checkRateLimitByIp('graphql_login', 'auth_token_ip', 60);
        $this->checkRateLimit('graphql_login:email:' . strtolower($email), 'customer_login', 60);

        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();
        $websiteId = \Mage::app()->getStore($storeId)->getWebsiteId();

        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId);

        try {
            $customer->authenticate($email, $password);
        } catch (\Mage_Core_Exception $e) {
            // Surface unconfirmed-email distinctly (matches the REST /auth/token
            // path) so the customer knows to confirm rather than seeing a
            // misleading "wrong password" error.
            if ($e->getCode() === \Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED) {
                throw new HttpException(403, 'This account is not confirmed. Please check your email for the confirmation link.');
            }
            throw new BadRequestHttpException('Invalid email or password');
        }

        return Customer::fromModel($customer);
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
     *
     * @param array<string, mixed> $profile Optional name-part/gender/dob fields; a null
     *                                      value clears the attribute, an absent key is untouched
     */
    private function doUpdateProfile(?string $firstName, ?string $lastName, #[\SensitiveParameter]
        ?string $email, array $profile = []): Customer
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
        ], fn($v) => $v !== null) + $profile;

        try {
            $customer = $this->customerService->updateCustomer($customer, $data);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return Customer::fromModel($customer);
    }

    /**
     * Update any customer as an admin or a service token holding customers/write.
     * The route security already excludes customer tokens; this re-checks in depth.
     */
    private function updateCustomerAdmin(int $customerId, Customer $data): Customer
    {
        if ($this->isApiUser()) {
            $this->requireApiPermission('customers/write');
        } else {
            $this->requireAdmin();
        }

        $customer = $this->customerService->getCustomerById($customerId);
        if (!$customer) {
            throw new NotFoundHttpException('Customer not found');
        }

        $this->assertWebsiteAllowed($customer->getWebsiteId(), $this->getAuthorizedUser(), 'customer');

        if ($data->websiteId !== null && $data->websiteId !== (int) $customer->getWebsiteId()) {
            throw new BadRequestHttpException('websiteId can only be set when creating a customer');
        }

        if ($data->email !== '' && $data->email !== $customer->getEmail()) {
            if (!\Mage::helper('core')->isValidEmail($data->email)) {
                throw new BadRequestHttpException('Invalid email address');
            }
            $this->ensureEmailUnique($data->email, (int) $customer->getWebsiteId());
            $customer->setEmail($data->email);
        }

        if ($data->firstname !== null) {
            $customer->setFirstname($data->firstname);
        }
        if ($data->lastname !== null) {
            $customer->setLastname($data->lastname);
        }
        foreach ($this->extractProfileFields($data) as $field => $value) {
            $customer->setData($field, $value);
        }
        if ($data->groupId !== null) {
            $this->validateGroupExists($data->groupId);
            $customer->setGroupId($data->groupId);
        }
        $this->applyAdminOnlyFields($customer, $data);

        try {
            $customer->save();
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return Customer::fromModel($customer);
    }

    /**
     * Whether the caller may write admin-only customer fields: an admin token,
     * or a service (API-user) token holding the given permission.
     */
    private function canWriteAdminFields(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $user = $this->security?->getUser();
        return $user instanceof ApiUser && $user->isApiUser() && $user->hasPermission($permission);
    }

    /** @throws AccessDeniedHttpException when a non-privileged caller supplies an admin-only field */
    private function assertNoAdminOnlyFields(Customer $data): void
    {
        $adminOnly = [
            'groupId' => $data->groupId,
            'isActive' => $data->isActive,
            'websiteId' => $data->websiteId,
            'taxvat' => $data->taxvat,
            'disableAutoGroupChange' => $data->disableAutoGroupChange,
        ];
        foreach ($adminOnly as $field => $value) {
            if ($value !== null) {
                throw new AccessDeniedHttpException("Field {$field} requires an admin or service token");
            }
        }
    }

    /**
     * Self-service-safe profile fields with a non-null value on the DTO,
     * normalized and keyed by model attribute; a null value clears the attribute.
     *
     * @return array<string, mixed>
     */
    private function extractProfileFields(Customer $data): array
    {
        $profile = [];
        if ($data->prefix !== null) {
            $profile['prefix'] = $data->prefix;
        }
        if ($data->middlename !== null) {
            $profile['middlename'] = $data->middlename;
        }
        if ($data->suffix !== null) {
            $profile['suffix'] = $data->suffix;
        }
        if ($data->gender !== null) {
            if ($data->gender !== 0) {
                $this->validateGenderOption($data->gender);
            }
            $profile['gender'] = $data->gender ?: null;
        }
        if ($data->dob !== null) {
            $profile['dob'] = $this->normalizeDobInput($data->dob);
        }
        return $profile;
    }

    /** Apply admin-only scalar fields; callers have already verified privilege */
    private function applyAdminOnlyFields(\Mage_Customer_Model_Customer $customer, Customer $data): void
    {
        if ($data->taxvat !== null) {
            $customer->setData('taxvat', $data->taxvat === '' ? null : $data->taxvat);
        }
        if ($data->isActive !== null) {
            $customer->setData('is_active', (int) $data->isActive);
        }
        if ($data->disableAutoGroupChange !== null) {
            $customer->setData('disable_auto_group_change', (int) $data->disableAutoGroupChange);
        }
    }

    private function validateGroupExists(int $groupId): void
    {
        if ($groupId < 0 || $groupId > self::MAX_SMALLINT || !\Mage::getModel('customer/group')->load($groupId)->getId()) {
            throw new BadRequestHttpException("Customer group {$groupId} does not exist");
        }
    }

    /** Gender is a select attribute: only its own option ids are storable */
    private function validateGenderOption(int $genderId): void
    {
        $attribute = \Mage::getSingleton('eav/config')->getAttribute('customer', 'gender');
        $options = $attribute && $attribute->usesSource() ? $attribute->getSource()->getAllOptions() : [];

        // The source prepends an empty option; only positive ids are real values.
        $optionIds = array_values(array_filter(
            array_map('intval', array_column($options, 'value')),
            fn(int $id): bool => $id > 0,
        ));

        if (!in_array($genderId, $optionIds, true)) {
            throw new BadRequestHttpException(
                "Invalid gender {$genderId}; allowed: " . implode(', ', $optionIds) . ' (0 clears)',
            );
        }
    }

    /**
     * Normalize a dob input to the midnight 'Y-m-d H:i:s' form the admin
     * stores; empty string clears the value (returns null).
     *
     * formatDateForDb() reads a numeric string as a unix timestamp and accepts
     * relative expressions like 'tomorrow', so the calendar-date shape is
     * enforced here to match the documented Y-m-d contract.
     */
    private function normalizeDobInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            throw new BadRequestHttpException('Invalid date for dob; use Y-m-d format.');
        }
        if ($date > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new BadRequestHttpException('Date of birth cannot be in the future.');
        }

        return $date->format(\Mage_Core_Model_Locale::DATETIME_FORMAT);
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

        // Per-IP cap first (matches the login flow), then a tighter per-customer
        // cap so a stolen session token can't brute-force the current password
        // to escalate the account takeover.
        $this->checkRateLimitByIp('change_password', 'change_password', 3600);
        $this->checkRateLimit('change_password:customer:' . $customerId, 'change_password', 3600);

        $customer = $this->customerService->getCustomerById($customerId);
        if (!$customer) {
            throw new AccessDeniedHttpException('Customer not found');
        }

        try {
            $this->customerService->changePassword($customer, $currentPassword, $newPassword);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return Customer::fromModel($customer);
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

        // Per-IP cap first so an attacker can't bypass the per-email bucket by
        // rotating addresses to enumerate accounts or flood the mail relay.
        $this->checkRateLimitByIp('forgot_password', 'forgot_password', 3600);
        $this->checkRateLimit('forgot_password:email:' . strtolower($email), 'forgot_password', 3600);

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

        $this->checkRateLimitByIp('reset_password', 'reset_password', 3600);

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
        $args = $context['args']['input'] ?? [];

        $accountToken = $args['accountToken'] ?? '';
        $password = $args['password'] ?? '';

        if (!$accountToken || !$password) {
            throw new BadRequestHttpException('Account token and password are required.');
        }

        $minPasswordLength = \Mage::getModel('customer/customer')->getMinPasswordLength();
        if (!\Mage::helper('core')->isValidLength($password, $minPasswordLength)) {
            throw new BadRequestHttpException("Password must be at least {$minPasswordLength} characters");
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

        // Virtual/incomplete orders may have no billing address (getBillingAddress() returns false)
        $billingAddress = $order->getBillingAddress() ?: null;

        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($order->getStoreId());
        $customer->setEmail($email);
        $customer->setFirstname($billingAddress ? $billingAddress->getFirstname() : '');
        $customer->setLastname($billingAddress ? $billingAddress->getLastname() : '');
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

        return Customer::fromModel($customer);
    }
}
