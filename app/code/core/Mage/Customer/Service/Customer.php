<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;
use Mage_Customer_Model_Channel as Channel;
use Mage_Customer_Model_Customer as Customer;

/**
 * Register, create, log in, find, save and delete customers, and change and reset their passwords,
 * for the storefront, the admin, the API and the CLI.
 */
class Mage_Customer_Service_Customer
{
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
    public const XML_PATH_CONFIRMATION_LINK_RATE_LIMIT = 'system/rate_limit/confirmation_link';

    public const XML_PATH_ACCOUNTS_ENABLED = 'customer/account/enabled_in_frontend';
    public const XML_PATH_ALLOW_2FA = 'customer/password/allow_2fa';
    public const XML_PATH_REQUIRE_2FA = 'customer/password/require_2fa';

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
     * Return the customer $id for a client that already has a login, such as a token to refresh.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     * @throws Mage_Core_Exception_Forbidden when the account is not active
     */
    public function requireActiveById(int $id): Customer
    {
        $customer = $this->requireById($id);
        $this->assertActive($customer);
        return $customer;
    }

    /**
     * Refuse a customer that an admin set to not active. Each login calls this: the password, a magic link,
     * a social sign-in and a token refresh.
     *
     * @throws Mage_Core_Exception_Forbidden
     */
    public function assertActive(Customer $customer): void
    {
        if (!$customer->getIsActive()) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('customer')->__('This account is not active.'));
        }
    }

    /**
     * Refuse a customer flow (register, log in) on a store that has the customer accounts turned off.
     * $storeId defaults to the current store. A flow of the staff does not call this.
     *
     * @throws Mage_Core_Exception_Forbidden
     */
    public function assertAccountsEnabled(?int $storeId = null): void
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_ACCOUNTS_ENABLED, $storeId)) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('customer')->__('Customer accounts are not available in this store.'));
        }
    }

    /**
     * Search customers like the admin customer grid, newest first.
     *
     * Every word of $search must appear in the email, the first name, the last name,
     * or the telephone of an address of the customer. The match ignores case.
     * Only the first Mage_Core_Helper_String::MAX_SEARCH_WORDS words count.
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

        $words = \Mage::helper('core/string')->splitSearchWords($search);
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
     * The API channel sends the solved CAPTCHA in $captchaToken. Maho_Captcha checks the storefront form before the
     * controller.
     *
     * @param (Closure(): void)|null $save
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when an observer
     *                                        of customer_registration_is_allowed refuses the registration
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the registration limit
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer and of its new addresses,
     *                                    or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function register(
        Customer $customer,
        int $storeId,
        string $backUrl = '',
        ?Closure $save = null,
        Channel $channel = Channel::Web,
        ?string $captchaToken = null,
    ): Customer {
        $this->assertAccountsEnabled($storeId);
        if (!Mage::helper('customer')->isRegistrationAllowed()) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('customer')->__('The registration of new customers is not allowed.'));
        }
        if ($channel === Channel::Api) {
            Mage::helper('apiplatform')->assertCaptcha(['captchaToken' => $captchaToken]);
        }
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
     * confirmation. When the transport sets no password, the customer gets a random one. With $welcomeEmailStoreId,
     * send the welcome email from that store. When the password is random, the welcome email has the link to set
     * a password. $save saves the customer: the admin order create gives the placement of its order, as register()
     * describes.
     *
     * @param (Closure(): void)|null $save
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer and of its new addresses
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function create(Customer $customer, int $storeId, ?int $welcomeEmailStoreId = null, ?Closure $save = null): Customer
    {
        $isPasswordRandom = $customer->getPassword() === '';
        if ($isPasswordRandom) {
            $password = $customer->generatePassword(16);
            $customer->setPassword($password)->setPasswordConfirmation($password);
        }
        $customer->setForceConfirmed(true);
        $this->saveNew($customer, $storeId, $save);

        if ($welcomeEmailStoreId !== null) {
            try {
                if ($isPasswordRandom) {
                    $this->sendPasswordLinkEmail($customer, $welcomeEmailStoreId);
                } else {
                    $this->sendNewAccountEmail($customer, 'registered', $welcomeEmailStoreId);
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $customer;
    }

    /**
     * Create $customer for a point of sale, on the store $storeId, with a random password and the welcome email
     * with the link to set a password. $address gets the name of the customer and becomes the default billing and
     * shipping address. It skips the validation, because a point of sale asks only some of the fields, such as the
     * telephone. Without a country, it gets the default country of the store.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function quickCreate(Customer $customer, int $storeId, ?Mage_Customer_Model_Address $address = null): Customer
    {
        if ($address !== null) {
            $address->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true)
                ->setShouldIgnoreValidation(true);
            if (!$address->getCountryId()) {
                $address->setCountryId((string) Mage::helper('core')->getDefaultCountry($storeId));
            }
            $customer->addAddress($address);
        }
        return $this->create($customer->setPassword(''), $storeId, $storeId);
    }

    /**
     * Save a customer for an admin, the CLI or an API integration. The customer must pass the checks of
     * Mage_Customer_Model_Customer::validate(): the names, the email and the required profile fields. Another
     * customer of the same website must not use its email: the resource model throws Mage_Core_Exception_Conflict.
     * A caller that changes only the group or the newsletter subscription of many customers gives $validateProfile
     * false: such a change must not fail on a customer whose stored profile has no value for a field that the
     * store made required later.
     * With $welcomeEmailStoreId, send the "confirmed" welcome email from that store when this save confirms
     * an account that waited for its confirmation.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer, or when the customer
     *                                    group does not exist
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function save(Customer $customer, ?int $welcomeEmailStoreId = null, bool $validateProfile = true): Customer
    {
        $errors = new Mage_Core_Exception_Input();
        $emailError = Mage::helper('customer')->__('Invalid email address "%s".', $customer->getEmail());
        if (!Mage::helper('core')->isValidEmail((string) $customer->getEmail())) {
            $errors->addError('email', $emailError);
        }
        $result = $validateProfile ? $customer->validate() : true;
        if (is_array($result)) {
            $this->addModelErrors($errors, 'customer', array_diff($result, [$emailError]));
        }
        $this->checkGroup($customer, $errors);
        $errors->throwIfErrors();

        $isConfirmation = $customer->getId() && $customer->getOrigData('confirmation') && !$customer->getConfirmation();
        $customer->save();

        if ($isConfirmation && $welcomeEmailStoreId !== null) {
            $this->sendConfirmedEmail($customer, $welcomeEmailStoreId);
        }
        return $customer;
    }

    /**
     * Save the changes that the customer made to their own account. Check $currentPassword first when
     * $requirePassword is true, when the customer changes the email, or when the transport gives a password. The
     * storefront form requires the password for each change. The API requires it for a new email only. A store that
     * registers customers without a password does not check it.
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
        bool $requirePassword = false,
    ): Customer {
        $oldEmail = (string) $customer->getOrigData('email');
        $isChangeEmail = $oldEmail !== '' && $oldEmail !== (string) $customer->getEmail();

        if (!$this->isPasswordless()) {
            if ($requirePassword || $currentPassword !== null) {
                $this->verifyPassword($customer, (string) $currentPassword);
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
     * With $notify, email the customer that the password changed.
     *
     * @throws Mage_Core_Exception_Input when the password is not valid
     */
    public function setPassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
        bool $notify = false,
    ): Customer {
        $this->applyPassword($customer, $password, null);
        $customer->save();
        if ($notify) {
            try {
                $this->sendPasswordChangedEmail($customer);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $customer;
    }

    /**
     * Email $customer a link to set a password, for an admin who does not choose the password. $isNew selects the
     * welcome email of a new account.
     */
    public function sendPasswordLink(Customer $customer, bool $isNew = false): void
    {
        $customer->sendPasswordLinkEmail($isNew);
    }

    /**
     * Return the secret of $customer for an authenticator app. Create and save it when the customer has none.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store does not allow 2FA
     * @throws Mage_Core_Exception_Conflict when the customer already uses 2FA
     */
    public function getSecondFactorSecret(Customer $customer): string
    {
        $this->assertSecondFactorAllowed();
        if ($customer->getTwofaEnabled()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('customer')->__('Two-factor authentication is already enabled.'));
        }
        $secret = $customer->getTwofaSecret();
        if ($secret === null) {
            $secret = Mage::helper('core/security')->generateTotpSecret();
            $customer->setTwofaSecret($secret)->save();
        }
        return $secret;
    }

    /**
     * Turn 2FA on or off for $customer, who gives the current password. To turn it on, the customer also gives
     * the $code of the authenticator app, which proves that the app has the secret.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store does not allow 2FA
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the password change limit
     * @throws Mage_Core_Exception_Forbidden when the store requires 2FA and $enable is false
     * @throws Mage_Core_Exception when $currentPassword is wrong, or with the code EXCEPTION_2FA_INVALID when $code
     *                             is wrong
     */
    public function setSecondFactor(
        Customer $customer,
        #[\SensitiveParameter]
        string $currentPassword,
        bool $enable,
        #[\SensitiveParameter]
        ?string $code = null,
    ): Customer {
        $helper = Mage::helper('customer');
        $this->assertSecondFactorAllowed();
        $this->verifyPassword($customer, $currentPassword);

        if (!$enable) {
            if (Mage::getStoreConfigFlag(self::XML_PATH_REQUIRE_2FA)) {
                throw new Mage_Core_Exception_Forbidden($helper->__('Two-factor authentication is required and cannot be disabled.'));
            }
            $customer->setTwofaEnabled(false)->setTwofaSecret(null)->save();
            return $customer;
        }
        if ($customer->getTwofaSecret() === null || !$customer->verifyTwofaCode((string) $code)) {
            throw new Mage_Core_Exception($helper->__('Invalid 2FA verification code'), Customer::EXCEPTION_2FA_INVALID);
        }
        $customer->setTwofaEnabled()->save();
        return $customer;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function assertSecondFactorAllowed(): void
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_ALLOW_2FA)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Two-factor authentication is not available.'));
        }
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
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, when the account is
     *                                        not active, or when it is not confirmed (code EXCEPTION_EMAIL_NOT_CONFIRMED)
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
        $this->assertAccountsEnabled();
        $this->checkLoginLimits($email);
        return $this->authenticateInWebsite($email, $password, $websiteId, $twofaCode);
    }

    /**
     * Check the email and the password as authenticate() does, in the website $websiteId first and then in each other
     * website, for a client that does not name its store. A wrong 2FA code stops the search: the password was right.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when no website
     *                                        accepts the login and an account is not confirmed or not active
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
        $this->assertAccountsEnabled();
        $this->checkLoginLimits($email);
        $websiteIds = array_unique([$websiteId, ...array_map(intval(...), array_keys(Mage::app()->getWebsites()))]);
        $forbidden = $error = null;
        foreach ($websiteIds as $id) {
            try {
                return $this->authenticateInWebsite($email, $password, $id, $twofaCode);
            } catch (Mage_Core_Exception_Forbidden $e) {
                $forbidden ??= $e;
            } catch (Mage_Core_Exception $e) {
                if ($e->getCode() === Customer::EXCEPTION_2FA_INVALID) {
                    throw $e;
                }
                $error = $e;
            }
        }
        throw $forbidden ?? $error;
    }

    /**
     * Check the 2FA code of $customer, whose identity another step proved: a magic link, a confirmation link or a
     * social login. A customer who does not use 2FA needs no code. Each check counts in the login limits, as a
     * login with the password does.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over the login limit
     * @throws Mage_Core_Exception with the code EXCEPTION_2FA_INVALID when the code is empty or wrong
     */
    public function verifySecondFactor(
        Customer $customer,
        #[\SensitiveParameter]
        string $code,
    ): void {
        if (!$customer->isTwofaRequired()) {
            return;
        }
        $this->checkLoginLimits((string) $customer->getEmail());
        if (!$customer->verifyTwofaCode($code)) {
            throw new Mage_Core_Exception(Mage::helper('customer')->__('2FA verification code is invalid.'), Customer::EXCEPTION_2FA_INVALID);
        }
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
     * @throws Mage_Core_Exception_Forbidden when the account is not confirmed, with the code
     *                                        EXCEPTION_EMAIL_NOT_CONFIRMED, or when it is not active
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
        // After the password: only the owner of the account learns that it is not active
        $this->assertActive($customer);
        return $customer;
    }

    /**
     * Email a password reset link to the customer of the website $websiteId that has $email. Throw nothing when
     * no customer has the email, or when the email fails, so that the answer does not show which addresses have
     * an account. The API channel sends the solved CAPTCHA in $captchaToken. Maho_Captcha checks the storefront
     * form before the controller, and the admin channel asks no CAPTCHA.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendPasswordResetLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
        Channel $channel = Channel::Web,
        ?string $captchaToken = null,
    ): void {
        $helper = Mage::helper('customer');
        $core = Mage::helper('core');
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Please enter your email.'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address.'));
        }
        if ($channel === Channel::Api) {
            Mage::helper('apiplatform')->assertCaptcha(['captchaToken' => $captchaToken]);
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
     * Email the confirmation link again to the customer of the website $websiteId that has $email and waits for
     * a confirmation. Throw nothing when no such customer has the email, or when the email fails, so that the
     * answer does not show which addresses have an account. The API channel sends the solved CAPTCHA in
     * $captchaToken, and the admin channel asks no CAPTCHA.
     *
     * @throws Mage_Core_Exception_Input when the email is empty or not valid, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email is over its limit
     */
    public function sendConfirmationLink(
        #[\SensitiveParameter]
        string $email,
        int $websiteId,
        Channel $channel = Channel::Web,
        ?string $captchaToken = null,
    ): void {
        $helper = Mage::helper('customer');
        $core = Mage::helper('core');
        if (!$core->isValidNotBlank($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Please enter your email.'));
        }
        if (!$core->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError('email', $helper->__('Invalid email address.'));
        }
        if ($channel === Channel::Api) {
            Mage::helper('apiplatform')->assertCaptcha(['captchaToken' => $captchaToken]);
        }

        // The limit of the client comes first: a client that sends many addresses gets past the limit of one address
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_CONFIRMATION_LINK_RATE_LIMIT);
        $this->attempt($core->rateLimiter('confirmation_link', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip));
        $this->attempt($core->rateLimiterBy('confirmation_link', 'confirmation_link:email:' . strtolower($email), $limit, self::RATE_LIMIT_WINDOW));

        $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($customer->getId() && $customer->getConfirmation()) {
            try {
                $this->sendNewAccountEmail($customer, 'confirmation', (int) $customer->getStoreId());
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
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off
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
        $this->assertAccountsEnabled();
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
     * getIsJustConfirmed() true when the account was waiting for this confirmation. The customer then gets the
     * "confirmed" welcome email.
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

        return $this->confirmVerified($customer);
    }

    /**
     * Confirm the account of $customer, whose email another step proved: the key of the confirmation link, or
     * a social sign-in with this email. An account that waited for the confirmation gets getIsJustConfirmed() true
     * and the "confirmed" welcome email.
     */
    public function confirmVerified(Customer $customer): Customer
    {
        if ($customer->getConfirmation()) {
            $customer->setConfirmation(null)->save();
            $customer->setIsJustConfirmed(true);
            $this->sendConfirmedEmail($customer, (int) $customer->getStoreId());
        }
        return $customer;
    }

    /**
     * Return the customer of the magic login link $token, and invalidate the link.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when the
     *                                        account is not active
     * @throws Mage_Core_Exception when the link is not valid
     */
    public function getByMagicLinkToken(
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        $customer = $this->findByMagicLinkToken($token);
        $customer->clearMagicLinkToken()->save();
        return $customer;
    }

    /**
     * Log in the customer of the magic login link $token, for a client that has no session to keep the customer
     * between two requests. Check the 2FA code before the link becomes invalid, so that the client can send the
     * same link again with the code.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the magic link login is disabled
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit of rejected tokens or of logins
     * @throws Mage_Core_Exception_Forbidden when the store has the customer accounts turned off, or when the
     *                                        account is not active
     * @throws Mage_Core_Exception when the link is not valid, or with the code EXCEPTION_2FA_INVALID when the
     *                             customer uses 2FA and $twofaCode is empty or wrong
     */
    public function authenticateByMagicLink(
        #[\SensitiveParameter]
        string $token,
        #[\SensitiveParameter]
        ?string $twofaCode = null,
    ): Customer {
        if (!Mage::helper('customer')->isMagicLinkEnabled()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('This sign-in method is not available.'));
        }
        $customer = $this->findByMagicLinkToken($token);
        $this->verifySecondFactor($customer, (string) $twofaCode);
        $customer->clearMagicLinkToken()->save();
        return $customer;
    }

    /**
     * @throws Mage_Core_Exception_TooManyRequests
     * @throws Mage_Core_Exception_Forbidden
     * @throws Mage_Core_Exception
     */
    private function findByMagicLinkToken(
        #[\SensitiveParameter]
        string $token,
    ): Customer {
        $this->assertAccountsEnabled();
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

        $this->assertActive($customer);
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
     * Tell $customer by email that staff changed the password.
     */
    protected function sendPasswordChangedEmail(Customer $customer): void
    {
        $customer->sendPasswordReminderEmail();
    }

    /**
     * Send the "confirmed" welcome email to $customer from the store $storeId. A failed email does not undo
     * the confirmation.
     */
    private function sendConfirmedEmail(Customer $customer, int $storeId): void
    {
        try {
            $this->sendNewAccountEmail($customer, 'confirmed', $storeId);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Send the welcome email with the link to set a password to $customer, from the store $storeId.
     */
    protected function sendPasswordLinkEmail(Customer $customer, int $storeId): void
    {
        $customer->sendPasswordLinkEmail(true, $storeId);
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
