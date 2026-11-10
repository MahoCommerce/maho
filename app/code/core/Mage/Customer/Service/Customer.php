<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\ApiPlatform\Trait\FilterValueTrait;
use Maho\Security\RateLimitScope;
use Mage_Customer_Model_Customer as Customer;

/**
 * Register, create, log in, find, save and delete customers, and change and reset their passwords,
 * for the storefront, the admin, the API and the CLI.
 */
class Mage_Customer_Service_Customer
{
    use FilterValueTrait;

    /**
     * Get customer by ID
     */
    public function getById(int $id): ?\Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')->load($id);

        return $customer->getId() ? $customer : null;
    }

    /**
     * Get customer by email
     *
     * @phpstan-impure Hits the DB; the result changes as customers are created,
     *                 so two calls with the same email can legitimately differ
     *                 (used to detect a concurrent registration race).
     */
    public function getByEmail(#[\SensitiveParameter]
        string $email): ?\Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($email);

        return $customer->getId() ? $customer : null;
    }

    public const MAX_PAGE_SIZE = 100;

    public const XML_PATH_REGISTER_RATE_LIMIT = 'system/rate_limit/customer_register';
    public const XML_PATH_LOGIN_IP_RATE_LIMIT = 'system/rate_limit/auth_token_ip';
    public const XML_PATH_LOGIN_RATE_LIMIT = 'system/rate_limit/customer_login';
    public const XML_PATH_FORGOT_PASSWORD_RATE_LIMIT = 'system/rate_limit/forgot_password';
    public const XML_PATH_MAGIC_LINK_EMAIL_RATE_LIMIT = 'system/rate_limit/magic_link_email';
    public const XML_PATH_MAGIC_LINK_IP_RATE_LIMIT = 'system/rate_limit/magic_link_ip';
    public const XML_PATH_CHANGE_PASSWORD_RATE_LIMIT = 'system/rate_limit/change_password';
    /** Counts only a rejected token: a password reset link, an account confirmation key or a magic login link */
    public const XML_PATH_TOKEN_RATE_LIMIT = 'system/rate_limit/reset_password';

    public const RATE_LIMIT_WINDOW = 3600;
    public const LOGIN_RATE_LIMIT_WINDOW = 60;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireById(int $id): Customer
    {
        return ($id > 0 ? $this->getById($id) : null)
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Customer not found'));
    }

    /**
     * Search customers like the admin customer grid, newest first.
     *
     * Every word of $search must appear in the email, the first name, the last name,
     * or the telephone of an address of the customer. The match ignores case.
     * Only the first MAX_SEARCH_WORDS words count.
     * $email is an exact match and $telephone matches the start of an address telephone.
     *
     * @param int[]|null $websiteIds Restrict matches to these websites; null means unrestricted
     * @return array{customers: list<\Mage_Customer_Model_Customer>, total: int}
     */
    public function search(
        string $search = '',
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $telephone = null,
        int $page = 1,
        int $pageSize = 20,
        ?array $websiteIds = null,
        ?int $groupId = null,
        ?int $websiteId = null,
        ?string $createdFrom = null,
        ?string $createdTo = null,
    ): array {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()->from(['c' => $resource->getTableName('customer/entity')], ['entity_id']);

        if ($websiteIds !== null) {
            $select->where('c.website_id IN (?)', $websiteIds === [] ? [-1] : array_map(intval(...), $websiteIds));
        }
        if ($websiteId !== null) {
            $select->where('c.website_id = ?', $websiteId);
        }
        if ($groupId !== null) {
            $select->where('c.group_id = ?', $groupId);
        }
        if ($createdFrom !== null) {
            $select->where('c.created_at >= ?', $createdFrom);
        }
        if ($createdTo !== null) {
            $select->where('c.created_at <= ?', $createdTo);
        }
        if ($email !== null && $email !== '') {
            $select->where('c.email = ?', $email);
        }
        if ($telephone !== null && $telephone !== '') {
            $select->where(
                'EXISTS (' . $this->addressTelephoneSelect(['like' => $telephone . '%']) . ')',
                null,
                \Maho\Db\Select::TYPE_CONDITION,
            );
        }

        $words = $this->searchWords($search);
        if ($words !== []) {
            $eavConfig = \Mage::getSingleton('eav/config');
            $varcharTable = $resource->getTableName('customer_entity_varchar');
            foreach (['firstname', 'lastname'] as $code) {
                $attributeId = (int) $eavConfig->getAttribute('customer', $code)->getId();
                $select->joinLeft(
                    [$code => $varcharTable],
                    "{$code}.entity_id = c.entity_id AND {$code}.attribute_id = {$attributeId}",
                    [],
                );
            }
            foreach ($words as $word) {
                $like = ['like' => '%' . $word . '%'];
                $select->where(implode(' OR ', [
                    $adapter->prepareSqlCondition('c.email', $like),
                    $adapter->prepareSqlCondition('firstname.value', $like),
                    $adapter->prepareSqlCondition('lastname.value', $like),
                    'EXISTS (' . $this->addressTelephoneSelect($like) . ')',
                ]), null, \Maho\Db\Select::TYPE_CONDITION);
            }
        }

        $countSelect = clone $select;
        $countSelect->reset(\Maho\Db\Select::COLUMNS)->columns(['total' => new \Maho\Db\Expr('COUNT(*)')]);
        $total = (int) $adapter->fetchOne($countSelect);

        $pageSize = max(1, min($pageSize, self::MAX_PAGE_SIZE));
        $select->order('c.entity_id DESC')->limitPage(max(1, $page), $pageSize);
        $ids = array_map(intval(...), $adapter->fetchCol($select));

        if ($ids === []) {
            return ['customers' => [], 'total' => $total];
        }

        $collection = \Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect([
                'firstname', 'lastname', 'email', 'default_billing', 'group_id',
                'prefix', 'middlename', 'suffix', 'gender', 'dob', 'taxvat',
                'created_in', 'confirmation',
            ])
            ->addFieldToFilter('entity_id', ['in' => $ids]);

        $customerMap = [];
        foreach ($collection as $customer) {
            $customerMap[(int) $customer->getId()] = $customer;
        }

        $customers = [];
        foreach ($ids as $id) {
            if (isset($customerMap[$id])) {
                $customers[] = $customerMap[$id];
            }
        }

        return ['customers' => $customers, 'total' => $total];
    }

    /**
     * Select the addresses of customer `c` whose telephone matches $condition.
     */
    private function addressTelephoneSelect(array $condition): \Maho\Db\Select
    {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $attributeId = (int) \Mage::getSingleton('eav/config')->getAttribute('customer_address', 'telephone')->getId();

        return $adapter->select()
            ->from(['a' => $resource->getTableName('customer/address_entity')], ['entity_id'])
            ->join(
                ['tel' => $resource->getTableName('customer_address_entity_varchar')],
                "tel.entity_id = a.entity_id AND tel.attribute_id = {$attributeId}",
                [],
            )
            ->where('a.parent_id = c.entity_id')
            ->where($adapter->prepareSqlCondition('tel.value', $condition), null, \Maho\Db\Select::TYPE_CONDITION);
    }

    /**
     * Register $customer, who signs up on the store $storeId. The transport puts the input into the customer and its
     * new addresses first, and sets the password and its confirmation. In the passwordless registration mode a
     * missing password becomes a random one. Send the confirmation link when the store requires a confirmation,
     * the magic login link in the passwordless mode, and the welcome email otherwise. $backUrl goes into the
     * confirmation link. $save saves the customer: a checkout gives the placement of its order, which saves the
     * customer with the order, so that a failed order leaves no account and sends no email.
     *
     * @param (Closure(): void)|null $save
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the registration limit
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer and of its new addresses
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function register(Customer $customer, int $storeId, string $backUrl = '', ?Closure $save = null): Customer
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_REGISTER_RATE_LIMIT, $storeId);
        $this->attempt(Mage::helper('core')->rateLimiter('create_customer', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip));

        $isPasswordless = $customer->getPassword() === '' && $this->isPasswordless();
        if ($isPasswordless) {
            $password = Mage::helper('core')->getRandomString(32);
            $customer->setPassword($password)->setPasswordConfirmation($password);
        }
        $this->saveNew($customer, $storeId, $save);
        Mage::dispatchEvent('customer_register_success', ['customer' => $customer]);

        try {
            if ($customer->isConfirmationRequired() && $customer->getConfirmation()) {
                $this->sendNewAccountEmail($customer, 'confirmation', $storeId, $backUrl);
            } elseif ($isPasswordless) {
                $customer->sendMagicLinkEmail();
            } else {
                $this->sendNewAccountEmail($customer, 'registered', $storeId);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $customer;
    }

    /**
     * Create $customer for an admin, the CLI or an API integration, on the store $storeId. The account needs no
     * confirmation. When the transport sets no password, the customer gets a random one and sets their own with
     * a password reset. With $welcomeEmailStoreId, send the welcome email from that store. $save saves the
     * customer: the admin order create gives the placement of its order, as register() describes.
     *
     * @param (Closure(): void)|null $save
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer and of its new addresses
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function create(Customer $customer, int $storeId, ?int $welcomeEmailStoreId = null, ?Closure $save = null): Customer
    {
        if ($customer->getPassword() === '') {
            $password = $customer->generatePassword(16);
            $customer->setPassword($password)->setPasswordConfirmation($password);
        }
        $customer->setForceConfirmed(true);
        $this->saveNew($customer, $storeId, $save);

        if ($welcomeEmailStoreId !== null) {
            try {
                $this->sendNewAccountEmail($customer, 'registered', $welcomeEmailStoreId);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $customer;
    }

    /**
     * Save a customer for an admin, the CLI or an API integration. Another customer of the same website must not
     * use its email: the resource model throws Mage_Core_Exception_Conflict.
     *
     * @throws Mage_Core_Exception_Input when the new email is not valid or the customer group does not exist
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function save(Customer $customer): Customer
    {
        $errors = new Mage_Core_Exception_Input();
        if ($customer->dataHasChangedFor('email') && !Mage::helper('core')->isValidEmail((string) $customer->getEmail())) {
            $errors->addError('email', Mage::helper('customer')->__('Invalid email address "%s".', $customer->getEmail()));
        }
        $this->checkGroup($customer, $errors);
        $errors->throwIfErrors();

        $customer->save();

        return $customer;
    }

    /**
     * Save the changes that the customer made to their own account. With $currentPassword, check it first, unless
     * the store registers customers without a password. A new email needs $currentPassword in that case too.
     * A new email or a new password, set with its confirmation,
     * ends the other sessions of the customer and invalidates the password reset link, and the customer gets an
     * email about the change at the old address.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the password change limit
     * @throws Mage_Core_Exception when $currentPassword is wrong
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer, or when a new email has
     *                                    no $currentPassword
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function updateAccount(
        Customer $customer,
        #[\SensitiveParameter]
        ?string $currentPassword = null,
    ): Customer {
        $oldEmail = (string) $customer->getOrigData('email');
        $isChangeEmail = $oldEmail !== '' && $oldEmail !== (string) $customer->getEmail();

        if (!$this->isPasswordless()) {
            if ($currentPassword !== null) {
                $this->verifyPassword($customer, $currentPassword);
            } elseif ($isChangeEmail) {
                throw new Mage_Core_Exception_Input()
                    ->addError('current_password', Mage::helper('customer')->__('Enter your current password to change your email.'));
            }
        }
        $isChangePassword = $customer->getPassword() !== '';

        $errors = new Mage_Core_Exception_Input();
        $this->addModelErrors($errors, 'customer', $customer->validate());
        $this->checkGroup($customer, $errors);
        $errors->throwIfErrors();

        if ($isChangeEmail || $isChangePassword) {
            $this->invalidateCredentials($customer);
        }
        $customer->cleanPasswordsValidationData();
        $customer->save();

        if ($isChangeEmail || $isChangePassword) {
            $customer->setOldEmail($oldEmail)
                ->setIsChangeEmail($isChangeEmail)
                ->setIsChangePassword($isChangePassword);
            try {
                $customer->sendChangedPasswordOrEmail();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $customer;
    }

    /**
     * Change the password of the customer, who gives the current one. $confirmation defaults to $newPassword.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the password change limit
     * @throws Mage_Core_Exception when $currentPassword is wrong
     * @throws Mage_Core_Exception_Input when the new password is not valid
     */
    public function changePassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $currentPassword,
        #[\SensitiveParameter]
        string $newPassword,
        #[\SensitiveParameter]
        ?string $confirmation = null,
    ): bool {
        $this->verifyPassword($customer, $currentPassword);
        $this->applyPassword($customer, $newPassword, $confirmation);
        $customer->save();

        $customer->setOldEmail($customer->getEmail())->setIsChangePassword(true);
        try {
            $customer->sendChangedPasswordOrEmail();
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return true;
    }

    /**
     * Set the password that an admin or the CLI chose for $customer. The other sessions of the customer end.
     *
     * @throws Mage_Core_Exception_Input when the password is not valid
     */
    public function setPassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
    ): Customer {
        $this->applyPassword($customer, $password, null);
        $customer->save();
        return $customer;
    }

    /**
     * Delete $customer. Its addresses, its newsletter subscription and its other data go with it.
     */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Check the email and the password of a customer of the website $websiteId, with the 2FA code when the customer
     * uses 2FA. A wrong password, an unknown email and a wrong 2FA code throw the Mage_Core_Exception of
     * Mage_Customer_Model_Customer::authenticate(), with its code.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception_Forbidden when the account is not confirmed, with the code EXCEPTION_EMAIL_NOT_CONFIRMED
     * @throws Mage_Core_Exception
     */
    public function authenticate(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $this->checkLoginLimits($email);
        return $this->authenticateInWebsite($email, $password, $websiteId, $twofaCode);
    }

    /**
     * Check the email and the password as authenticate() does, in the website $websiteId first and then in each other
     * website, for a client that does not name its store. A wrong 2FA code stops the search: the password was right.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception_Forbidden when no website accepts the login and an account is not confirmed
     * @throws Mage_Core_Exception
     */
    public function authenticateInAnyWebsite(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        $this->checkLoginLimits($email);
        $websiteIds = array_unique([$websiteId, ...array_map(intval(...), array_keys(Mage::app()->getWebsites()))]);
        $notConfirmed = $error = null;
        foreach ($websiteIds as $id) {
            try {
                return $this->authenticateInWebsite($email, $password, $id, $twofaCode);
            } catch (Mage_Core_Exception_Forbidden $e) {
                $notConfirmed ??= $e;
            } catch (Mage_Core_Exception $e) {
                if ($e->getCode() === Customer::EXCEPTION_2FA_INVALID) {
                    throw $e;
                }
                $error = $e;
            }
        }
        throw $notConfirmed ?? $error;
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     */
    private function checkLoginLimits(#[\SensitiveParameter] string $email): void
    {
        $core = Mage::helper('core');
        // The limit of the client comes first: a client that tries many addresses gets past the limit of one address
        $ipLimit = (int) Mage::getStoreConfig(self::XML_PATH_LOGIN_IP_RATE_LIMIT);
        $this->attempt($core->rateLimiter('customer_login', $ipLimit, self::LOGIN_RATE_LIMIT_WINDOW, RateLimitScope::Ip), self::LOGIN_RATE_LIMIT_WINDOW);
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_LOGIN_RATE_LIMIT);
        $this->attempt($core->rateLimiterBy('customer_login', 'customer_login:email:' . strtolower($email), $limit, self::LOGIN_RATE_LIMIT_WINDOW), self::LOGIN_RATE_LIMIT_WINDOW);
    }

    /**
     * @throws Mage_Core_Exception_Forbidden when the account is not confirmed, with the code EXCEPTION_EMAIL_NOT_CONFIRMED
     * @throws Mage_Core_Exception
     */
    private function authenticateInWebsite(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        int $websiteId,
        #[\SensitiveParameter]
        ?string $twofaCode,
    ): Customer {
        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId);
        try {
            $customer->authenticate($email, $password, $twofaCode);
        } catch (Mage_Core_Exception $e) {
            if ($e->getCode() === Customer::EXCEPTION_EMAIL_NOT_CONFIRMED) {
                throw new Mage_Core_Exception_Forbidden(
                    Mage::helper('customer')->__('This account is not confirmed. Please check your email for the confirmation link.'),
                    $e->getCode(),
                    $e,
                );
            }
            throw $e;
        }
        return $customer;
    }

    /**
     * Email a password reset link to the customer of the website $websiteId that has $email. Throw nothing when
     * no customer has the email, or when the email fails, so that the answer does not show which addresses have
     * an account.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendPasswordResetLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $helper = Mage::helper('customer');
        $core = Mage::helper('core');
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Please enter your email.'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address.'));
        }

        // The limit of the client comes first: a client that sends many addresses gets past the limit of one address
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_FORGOT_PASSWORD_RATE_LIMIT);
        $this->attempt($core->rateLimiter('forgot_password', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip));
        $this->attempt($core->rateLimiterBy('forgot_password', 'forgot_password:email:' . strtolower($email), $limit, self::RATE_LIMIT_WINDOW));

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId()) {
            try {
                $customer->sendPasswordResetLinkEmail();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Email a login link to the active customer of the website $websiteId that has $email. Throw nothing when
     * no active customer has the email, or when the email fails, so that the answer does not show which
     * addresses have an account.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the magic link login is disabled
     * @throws Mage_Core_Exception_Input when the email is empty or not valid
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendMagicLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
    ): void {
        $helper = Mage::helper('customer');
        $core = Mage::helper('core');
        if (!$helper->isMagicLinkEnabled()) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('This sign-in method is not available.'));
        }
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Please enter your email.'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address.'));
        }

        $this->attempt($core->rateLimiter('magic_link', (int) Mage::getStoreConfig(self::XML_PATH_MAGIC_LINK_IP_RATE_LIMIT), self::RATE_LIMIT_WINDOW, RateLimitScope::Ip));
        $this->attempt($core->rateLimiterBy(
            'magic_link',
            'magic_link:email:' . strtolower($email),
            (int) Mage::getStoreConfig(self::XML_PATH_MAGIC_LINK_EMAIL_RATE_LIMIT),
            self::RATE_LIMIT_WINDOW,
        ));

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId() && $customer->getIsActive()) {
            try {
                $customer->sendMagicLinkEmail();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * Return the customer $customerId when $token is its password reset link and the link has not expired.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the link is not valid
     */
    public function getByResetToken(
        int $customerId,
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        return $this->checkToken(function () use ($customerId, $token): Customer {
            $customer = $customerId > 0 ? $this->getById($customerId) : null;
            if ($customer === null || !$this->isResetTokenValid($customer, $token)) {
                throw new Mage_Core_Exception(Mage::helper('customer')->__('Your password reset link has expired.'));
            }
            return $customer;
        });
    }

    /**
     * Set the new password of the customer $customerId, who follows the password reset link $token.
     * $confirmation defaults to $newPassword.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the link is not valid
     * @throws Mage_Core_Exception_Input when the new password is not valid
     */
    public function resetPasswordById(
        int $customerId,
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        string $newPassword,
        #[\SensitiveParameter]
        ?string $confirmation = null,
    ): Customer {
        $customer = $this->getByResetToken($customerId, $token);
        $this->completeReset($customer, $newPassword, $confirmation);
        return $customer;
    }

    /**
     * Set the new password of the customer of the current website that has $email, who gives the token of the
     * password reset link. A wrong email, a wrong token and an expired token get the same error.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the token is not valid
     * @throws Mage_Core_Exception_Input when the new password is not valid
     */
    public function resetPassword(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        string $newPassword,
    ): bool {
        $customer = $this->checkToken(function () use ($email, $token): Customer {
            $customer = $this->getByEmail($email);
            if ($customer === null || !$this->isResetTokenValid($customer, $token)) {
                throw new Mage_Core_Exception(Mage::helper('customer')->__('Invalid or expired reset token.'));
            }
            return $customer;
        });
        $this->completeReset($customer, $newPassword, null);
        return true;
    }

    /**
     * Confirm the account $customerId with the key of the confirmation link. Return the customer, with
     * getIsJustConfirmed() true when the account was waiting for this confirmation.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the account or the key is wrong
     */
    public function confirm(
        int $customerId,
        #[\SensitiveParameter]
        string $key,
    ): Customer {
        $helper = Mage::helper('customer');
        $customer = $this->checkToken(function () use ($customerId, $key, $helper): Customer {
            $customer = $customerId > 0 ? $this->getById($customerId) : null;
            if ($customer === null) {
                throw new Mage_Core_Exception($helper->__('Wrong customer account specified.'));
            }
            if ($customer->getConfirmation() && !hash_equals((string) $customer->getConfirmation(), $key)) {
                throw new Mage_Core_Exception($helper->__('Wrong confirmation key.'));
            }
            return $customer;
        });

        if ($customer->getConfirmation()) {
            $customer->setConfirmation(null)->save();
            $customer->setIsJustConfirmed(true);
        }
        return $customer;
    }

    /**
     * Return the customer of the magic login link $token, and invalidate the link.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception when the link is not valid or the account is not active
     */
    public function getByMagicLinkToken(
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        $helper = Mage::helper('customer');
        $customer = $this->checkToken(function () use ($token, $helper): Customer {
            /** @var Customer $customer */
            $customer = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('rp_token', $token)
                ->getFirstItem();
            if (!$customer->getId()) {
                throw new Mage_Core_Exception($helper->__('Invalid or expired login link.'));
            }
            if (!$customer->validateMagicLinkToken($token) || $customer->isMagicLinkTokenExpired()) {
                throw new Mage_Core_Exception($helper->__('Your login link has expired. Please request a new one.'));
            }
            return $customer;
        });

        if (!$customer->getIsActive()) {
            throw new Mage_Core_Exception($helper->__('This account is not active.'));
        }
        $customer->clearMagicLinkToken()->save();
        return $customer;
    }

    protected function isPasswordless(): bool
    {
        return Mage::helper('customer')->getMagicLinkRegistrationMode() === Mage_Customer_Model_Config_Registrationmode::MODE_NO_PASSWORD;
    }

    /**
     * Send the new account email $type ('registered' or 'confirmation') to $customer from the store $storeId.
     */
    protected function sendNewAccountEmail(Customer $customer, string $type, int $storeId, string $backUrl = ''): void
    {
        $customer->sendNewAccountEmail($type, $backUrl, $storeId);
    }

    /**
     * Check a new customer and its new addresses, then save them, with $save when it is given.
     *
     * @param (Closure(): void)|null $save
     * @throws Mage_Core_Exception_Input
     * @throws Mage_Core_Exception_Conflict
     */
    private function saveNew(Customer $customer, int $storeId, ?Closure $save): void
    {
        $store = Mage::app()->getStore($storeId);
        if (!$customer->getWebsiteId()) {
            $customer->setWebsiteId((int) $store->getWebsiteId());
        }
        if ($customer->getStoreId() === null) {
            $customer->setStoreId($storeId);
        }
        if ($customer->getPasswordConfirmation() === null) {
            $customer->setPasswordConfirmation($customer->getPassword());
        }

        $errors = new Mage_Core_Exception_Input();
        $this->addModelErrors($errors, 'customer', $customer->validate());
        $this->checkGroup($customer, $errors);
        foreach ($customer->getAddresses() as $address) {
            $this->addModelErrors($errors, 'address', $address->validate());
        }
        $errors->throwIfErrors();

        $customer->setPasswordCreatedAt(time());
        $customer->cleanPasswordsValidationData();
        $save ? $save() : $customer->save();
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception
     */
    private function verifyPassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
    ): void {
        $core = Mage::helper('core');
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_CHANGE_PASSWORD_RATE_LIMIT);
        $this->attempt($core->rateLimiter('change_password', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip));
        $this->attempt($core->rateLimiterBy('change_password', 'change_password:customer:' . $customer->getId(), $limit, self::RATE_LIMIT_WINDOW));

        // The transport can set the new password on $customer before this check, so compare with the stored hash
        $hash = (string) ($customer->getId() ? $customer->getOrigData('password_hash') : $customer->getPasswordHash());
        if ($hash === '' || !Mage::helper('core')->validateHash($password, $hash)) {
            throw new Mage_Core_Exception(Mage::helper('customer')->__('Current password is incorrect.'));
        }
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function applyPassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        ?string $confirmation,
    ): void {
        $customer->setPassword($password)->setPasswordConfirmation($confirmation ?? $password);
        $errors = new Mage_Core_Exception_Input();
        $this->addModelErrors($errors, 'password', $customer->validateResetPassword());
        $errors->throwIfErrors();

        $this->invalidateCredentials($customer);
        $customer->cleanPasswordsValidationData();
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function completeReset(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        ?string $confirmation,
    ): void {
        $customer->setPassword($password)->setPasswordConfirmation($confirmation ?? $password);
        $errors = new Mage_Core_Exception_Input();
        $this->addModelErrors($errors, 'password', $customer->validateResetPassword());
        $errors->throwIfErrors();

        $customer->completePasswordReset()->save();
    }

    /**
     * End the other sessions of the customer and invalidate the password reset link.
     */
    private function invalidateCredentials(Customer $customer): void
    {
        $customer->setRpToken(null)
            ->setRpTokenCreatedAt(null)
            ->setRpCustomerId(null)
            ->setPasswordCreatedAt(time());
    }

    private function isResetTokenValid(
        Customer $customer,
        #[\SensitiveParameter]
        string $token,
    ): bool {
        $stored = $customer->getRpToken();
        return $token !== '' && $stored !== null && hash_equals($stored, $token) && !$customer->isResetPasswordLinkTokenExpired();
    }

    /**
     * Run $check, which throws a Mage_Core_Exception for a rejected token. Count each rejected token against the
     * client, so that a valid link never counts.
     *
     * @template T
     * @param callable(): T $check
     * @return T
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception
     */
    private function checkToken(callable $check): mixed
    {
        $limiter = Mage::helper('core')->rateLimiter(
            'customer_token',
            (int) Mage::getStoreConfig(self::XML_PATH_TOKEN_RATE_LIMIT),
            self::RATE_LIMIT_WINDOW,
        );
        if ($limiter->tooManyAttempts()) {
            throw new Mage_Core_Exception_TooManyRequests(Mage::helper('customer')->__('Too many attempts. Please try again later.'), self::RATE_LIMIT_WINDOW);
        }
        try {
            return $check();
        } catch (Mage_Core_Exception $e) {
            $limiter->hit();
            throw $e;
        }
    }

    private function checkGroup(Customer $customer, Mage_Core_Exception_Input $errors): void
    {
        if ($customer->getId() && !$customer->dataHasChangedFor('group_id')) {
            return;
        }
        $groupId = (int) $customer->getGroupId();
        $groups = Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->toOptionHash();
        if (!array_key_exists($groupId, $groups)) {
            $errors->addError('group_id', Mage::helper('customer')->__('Customer group not found with ID: %s', $groupId));
        }
    }

    /**
     * @param array<string>|bool $result the result of validate() of a model
     */
    private function addModelErrors(Mage_Core_Exception_Input $errors, string $field, array|bool $result): void
    {
        if (is_array($result)) {
            foreach (array_values(array_unique($result)) as $message) {
                $errors->addError($field, (string) $message);
            }
        }
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function attempt(\Maho\Security\RateLimiter $limiter, int $window = self::RATE_LIMIT_WINDOW): void
    {
        if (!$limiter->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(Mage::helper('customer')->__('Too many requests. Please try again later.'), $window);
        }
    }
}
