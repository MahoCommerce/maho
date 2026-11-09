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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Customer State Processor - Handles customer mutations for API Platform.
 */
final class CustomerProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Customer
    {
        $operationName = $operation->getName();

        // Bridge REST request body into context args (GraphQL populates args natively).
        // The forgot/reset/create-from-order handlers read from $context['args']['input'];
        // for REST callers API Platform does not populate that key, so the JSON body is
        // injected here to keep those endpoints working over REST.
        $this->normalizeGraphQlInput($context);
        $args = $context['args']['input'] ?? [];

        return match (true) {
            $operationName === 'update_profile' => $this->updateProfile($data->firstname, $data->lastname, $data->email !== '' ? $data->email : null, $this->extractProfileFields($data), $data->customAttributes),
            $operation instanceof Put && isset($uriVariables['id']) => $this->updateCustomerAdmin((int) $uriVariables['id'], $data),
            $operationName === 'change_password' => $this->changePassword($data->currentPassword ?? '', $data->newPassword ?? ''),
            $operationName === 'forgot_password_rest', $operationName === 'forgotPassword' => $this->forgotPassword($args),
            $operationName === 'reset_password_rest', $operationName === 'resetPassword' => $this->resetPassword($args),
            $operationName === 'create_from_order' => $this->createAccountFromOrder($args),
            $operation instanceof Post => $this->createCustomer($data),
            $operationName === 'quickCreate' => $this->createCustomerQuick($context['args']['input'] ?? $context['args'] ?? []),
            $operationName === 'login' => $this->customerLogin($args),
            $operationName === 'update' => $this->updateProfile($args['firstName'] ?? null, $args['lastName'] ?? null, $args['email'] ?? null, customAttributes: $args['customAttributes'] ?? null),
            $operationName === 'changePassword' => $this->changePassword($args['currentPassword'] ?? '', $args['newPassword'] ?? ''),
            // A stateless API has no session: the client logs out when it drops its tokens
            $operationName === 'logout' => new Customer(),
            default => $data instanceof Customer ? $data : new Customer(),
        };
    }

    /**
     * Register a customer (POST /customers). An admin or a service token holding customers/create creates an
     * account that needs no confirmation, and may set the admin-only fields; the securityPostDenormalize gate of
     * those fields has already reset them for any other caller.
     */
    private function createCustomer(Customer $data): Customer
    {
        StoreContext::ensureStore();
        $isPrivileged = $this->canWriteAdminFields('customers/create');
        $storeId = StoreContext::getStoreId();
        $customer = \Mage::getModel('customer/customer');

        // websiteId is honored on create only: pick which website the account
        // lives on, defaulting the store to that website's default store.
        if ($data->websiteId !== null) {
            $website = $this->getWebsite($data->websiteId);
            $customer->setWebsiteId((int) $website->getId());
            $defaultStore = $website->getDefaultStore();
            if ($defaultStore) {
                $storeId = (int) $defaultStore->getId();
            }
            // websiteId only survives denormalization for a privileged caller,
            // so the authenticated ApiUser is guaranteed to exist.
            $this->assertWebsiteAllowed($website->getId(), $this->requireUser(), 'customer');
        }

        $customer->setEmail($data->email)
            ->setFirstname($data->firstname ?? '')
            ->setLastname($data->lastname ?? '')
            ->setPassword($data->password ?? '');
        if ($data->groupId !== null) {
            $customer->setGroupId($data->groupId);
        }
        foreach ($this->extractProfileFields($data) as $field => $value) {
            $customer->setData($field, $value);
        }
        $this->applyAdminOnlyFields($customer, $data);
        // A registration takes the attributes of the registration form, as the storefront does
        CustomAttributes::apply(
            $customer,
            $isPrivileged ? 'adminhtml_customer' : 'customer_account_create',
            $data->customAttributes ?? [],
        );
        if ($data->isActive === null) {
            $customer->setData('is_active', 1);
        }

        if ($isPrivileged) {
            $this->getService()->create($customer, $storeId, $storeId);
        } else {
            $this->getService()->register($customer, $storeId);
        }

        return Customer::fromModel($customer);
    }

    /**
     * Create a customer for a point of sale (GraphQL quickCreate). The customer gets a random password and sets
     * their own with a password reset. A telephone becomes an address that holds only the name and the telephone.
     */
    private function createCustomerQuick(array $args): Customer
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        $customer = \Mage::getModel('customer/customer')
            ->setEmail((string) ($args['email'] ?? ''))
            ->setFirstname((string) ($args['firstname'] ?? ''))
            ->setLastname((string) ($args['lastname'] ?? ''));
        if (!empty($args['telephone'])) {
            $customer->addAddress(\Mage::getModel('customer/address')
                ->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setTelephone((string) $args['telephone'])
                ->setStreet([''])
                ->setCity('')
                ->setPostcode('')
                ->setCountryId(\Mage::getStoreConfig('general/country/default', $storeId) ?: 'US')
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true)
                ->setShouldIgnoreValidation(true));
        }
        $this->getService()->create($customer, $storeId, $storeId);

        return Customer::fromModel($customer);
    }

    /**
     * Check the email and the password of a customer (GraphQL login). A wrong email, password or 2FA code is a 401.
     */
    private function customerLogin(array $args): Customer
    {
        $email = (string) ($args['email'] ?? '');
        $password = (string) ($args['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new \Mage_Core_Exception_Input()->addError('email', 'Email and password are required');
        }

        StoreContext::ensureStore();
        $websiteId = (int) \Mage::app()->getStore(StoreContext::getStoreId())->getWebsiteId();
        try {
            $customer = $this->getService()->authenticate($email, $password, $websiteId);
        } catch (\Mage_Core_Exception $e) {
            if (in_array($e->getCode(), [\Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD, \Mage_Customer_Model_Customer::EXCEPTION_2FA_INVALID], true)) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid email or password', $e, 0, ['X-Api-Error-Code' => 'invalid_credentials']);
            }
            throw $e;
        }

        return Customer::fromModel($customer);
    }

    /**
     * Save the changes of the customer of the token to their own account (REST PUT /customers/me and GraphQL update).
     * Admin-only fields (groupId, isActive, websiteId, taxvat, disableAutoGroupChange) are never read here,
     * so a customer's own token cannot touch them.
     *
     * @param array<string, mixed> $profile Optional name-part/gender/dob fields; a null
     *                                      value clears the attribute, an absent key is untouched
     * @param array<string, mixed>|null $customAttributes the values of the Customer Account Edit form
     */
    private function updateProfile(
        ?string $firstName,
        ?string $lastName,
        #[\SensitiveParameter]
        ?string $email,
        array $profile = [],
        ?array $customAttributes = null,
    ): Customer {
        $customer = $this->getService()->requireById($this->requireCustomerId());
        if ($firstName !== null) {
            $customer->setFirstname($firstName);
        }
        if ($lastName !== null) {
            $customer->setLastname($lastName);
        }
        if ($email !== null) {
            $customer->setEmail($email);
        }
        foreach ($profile as $field => $value) {
            $customer->setData($field, $value);
        }
        if ($customAttributes !== null) {
            CustomAttributes::apply($customer, 'customer_account_edit', $customAttributes);
        }

        return Customer::fromModel($this->getService()->updateAccount($customer));
    }

    /**
     * Update any customer as an admin or a service token holding customers/write.
     * The operation's security already excludes customer tokens.
     */
    private function updateCustomerAdmin(int $customerId, Customer $data): Customer
    {
        $customer = $this->getService()->requireById($customerId);
        $this->assertWebsiteAllowed($customer->getWebsiteId(), $this->requireUser(), 'customer');

        if ($data->websiteId !== null && $data->websiteId !== (int) $customer->getWebsiteId()) {
            throw new \Mage_Core_Exception_Input()->addError('website_id', 'websiteId can only be set when creating a customer');
        }
        if ($data->email !== '') {
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
            $customer->setGroupId($data->groupId);
        }
        $this->applyAdminOnlyFields($customer, $data);
        if ($data->customAttributes !== null) {
            CustomAttributes::apply($customer, 'adminhtml_customer', $data->customAttributes);
        }

        return Customer::fromModel($this->getService()->save($customer));
    }

    private function changePassword(
        #[\SensitiveParameter]
        string $currentPassword,
        #[\SensitiveParameter]
        string $newPassword,
    ): Customer {
        $customer = $this->getService()->requireById($this->requireCustomerId());
        $this->getService()->changePassword($customer, $currentPassword, $newPassword);

        return Customer::fromModel($customer);
    }

    /**
     * Email a password reset link. The answer is the same whether an account has the email or not.
     */
    private function forgotPassword(array $args): Customer
    {
        StoreContext::ensureStore();
        $email = (string) ($args['email'] ?? '');
        $this->getService()->sendPasswordResetLink($email, (int) \Mage::app()->getStore(StoreContext::getStoreId())->getWebsiteId());

        $dto = new Customer();
        $dto->email = $email;
        return $dto;
    }

    private function resetPassword(array $args): Customer
    {
        StoreContext::ensureStore();
        $email = (string) ($args['email'] ?? '');
        $resetToken = (string) ($args['resetToken'] ?? '');
        $newPassword = (string) ($args['newPassword'] ?? '');
        if ($email === '' || $resetToken === '' || $newPassword === '') {
            throw new \Mage_Core_Exception_Input()->addError('reset_token', 'Email, reset token, and new password are required');
        }

        $this->getService()->resetPassword($email, $resetToken, $newPassword);

        $dto = new Customer();
        $dto->email = $email;
        return $dto;
    }

    /**
     * Create a customer account from a placed guest order, with the HMAC-signed accountToken of the order.
     */
    private function createAccountFromOrder(array $args): Customer
    {
        $accountToken = (string) ($args['accountToken'] ?? '');
        $password = (string) ($args['password'] ?? '');
        if ($accountToken === '' || $password === '') {
            throw new \Mage_Core_Exception_Input()->addError('account_token', 'Account token and password are required.');
        }

        $tokenData = \Mage::helper('sales/accountToken')->verify($accountToken, 3600);
        if ($tokenData['action'] !== 'create_account') {
            throw new \Mage_Core_Exception('Invalid token action.');
        }
        $order = \Mage::getModel('sales/order')->load($tokenData['orderId']);
        if (!$order->getId() || $order->getCustomerEmail() !== $tokenData['email']) {
            throw new \Mage_Core_Exception('Order not found.');
        }

        // Virtual/incomplete orders may have no billing address
        $billingAddress = $order->getBillingAddress();
        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId((int) \Mage::app()->getStore($order->getStoreId())->getWebsiteId())
            ->setStoreId((int) $order->getStoreId())
            ->setEmail($tokenData['email'])
            ->setFirstname($billingAddress ? $billingAddress->getFirstname() : '')
            ->setLastname($billingAddress ? $billingAddress->getLastname() : '')
            ->setPassword($password);
        if ($billingAddress) {
            $address = \Mage::getModel('customer/address')->setIsDefaultBilling(true)->setIsDefaultShipping(true);
            foreach (['firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'region', 'postcode', 'country_id', 'telephone'] as $field) {
                $address->setData($field, $billingAddress->getData($field));
            }
            $customer->addAddress($address->setShouldIgnoreValidation(true));
        }
        $this->getService()->register($customer, (int) $order->getStoreId());

        $order->setCustomerId($customer->getId());
        $order->setCustomerIsGuest(false);
        $order->setCustomerFirstname($customer->getFirstname());
        $order->setCustomerLastname($customer->getLastname());
        $order->save();

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

    /**
     * @throws \Mage_Core_Exception_Input
     */
    private function getWebsite(int $websiteId): \Mage_Core_Model_Website
    {
        try {
            // PostgreSQL refuses a website ID above the range of its SMALLINT column
            $website = $websiteId >= 0 && $websiteId <= 32767 ? \Mage::app()->getWebsite($websiteId) : null;
        } catch (\Throwable) {
            $website = null;
        }
        return $website ?? throw new \Mage_Core_Exception_Input()->addError('website_id', "Website {$websiteId} does not exist");
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
        foreach (['prefix', 'middlename', 'suffix'] as $field) {
            if ($data->{$field} !== null) {
                $profile[$field] = $data->{$field};
            }
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

    /** Gender is a select attribute: only its own option ids are storable */
    private function validateGenderOption(int $genderId): void
    {
        $attribute = \Mage::getSingleton('eav/config')->getAttribute('customer', 'gender');
        $options = $attribute && $attribute->usesSource() ? $attribute->getSource()->getAllOptions() : [];

        // The source prepends an empty option; only positive ids are real values.
        $optionIds = array_values(array_filter(
            array_map(intval(...), array_column($options, 'value')),
            fn(int $id): bool => $id > 0,
        ));

        if (!in_array($genderId, $optionIds, true)) {
            throw new \Mage_Core_Exception_Input()->addError('gender', "Invalid gender {$genderId}; allowed: " . implode(', ', $optionIds) . ' (0 clears)');
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
            throw new \Mage_Core_Exception_Input()->addError('dob', 'Invalid date for dob; use Y-m-d format.');
        }
        if ($date > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new \Mage_Core_Exception_Input()->addError('dob', 'Date of birth cannot be in the future.');
        }

        return $date->format(\Mage_Core_Model_Locale::DATETIME_FORMAT);
    }

    private function getService(): \Mage_Customer_Service_Customer
    {
        return \Mage::getService('customer/customer');
    }
}
