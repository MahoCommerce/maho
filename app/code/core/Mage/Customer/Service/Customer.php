<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;
use Mage_Customer_Model_Customer as Customer;

/**
 * Find, register, create, save and delete customers, change their passwords and send the emails of the account,
 * for the storefront, the admin, the API and the CLI. Mage_Customer_Service_Authentication proves who a customer is.
 */
class Mage_Customer_Service_Customer
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Customer
    {
        return $this->findById($id)
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Customer not found'));
    }

    /**
     * Return the customer $id, or null when there is none, for a caller that has its own answer for that case.
     */
    public function findById(int $id): ?Customer
    {
        $customer = Mage::getModel('customer/customer')->load($id);

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
    public const XML_PATH_CHANGE_PASSWORD_RATE_LIMIT = 'system/rate_limit/change_password';

    public const XML_PATH_ACCOUNTS_ENABLED = 'customer/account/enabled_in_frontend';

    public const RATE_LIMIT_WINDOW = 3600;

    /**
     * Return the customer $id for a client that already has a login, such as a token to refresh.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     * @throws Mage_Core_Exception_Forbidden when the account is not active
     */
    public function requireActiveById(int $id): Customer
    {
        $customer = $this->getById($id);
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
     * Register $customer, who signs up on the store $storeId through a form or the API. The transport puts the
     * input into the customer and its new addresses first, and sets the password and its confirmation. In the
     * passwordless registration mode a missing password becomes a random one. Send the confirmation link when the
     * store requires a confirmation, the magic login link in the passwordless mode, and the welcome email otherwise.
     * $backUrl goes into the confirmation link.
     * $captchaFields is what the client sent, the form POST or the API body: the CAPTCHA module finds its token in
     * it.
     * The registration counts in the registration limit when its input is valid, before the save.
     * The event customer_register_success gets the customer and the entries of $eventData.
     *
     * @param array<array-key, mixed> $captchaFields
     * @param array<string, mixed> $eventData
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
        array $captchaFields,
        string $backUrl = '',
        array $eventData = [],
    ): Customer {
        $this->assertRegistrationAllowed($storeId);
        Mage::helper('core')->assertCaptcha($captchaFields);

        return $this->saveRegistration($customer, $storeId, $backUrl, null, $eventData);
    }

    /**
     * Register $customer, who places an order in the checkout of the store $storeId. $placeOrder places the order,
     * which saves the customer with it, so that a failed order leaves no account and sends no email. The checkout
     * asks its CAPTCHA at the billing step, where the customer gives the account data, so this entry asks none.
     * The rules, the limit, the event and the emails are those of register().
     *
     * @param Closure(): void $placeOrder
     * @throws Mage_Core_Exception_Forbidden|Mage_Core_Exception_TooManyRequests|Mage_Core_Exception_Input|Mage_Core_Exception_Conflict
     *         as register() does
     */
    public function registerAtCheckout(Customer $customer, int $storeId, Closure $placeOrder): Customer
    {
        $this->assertRegistrationAllowed($storeId);

        return $this->saveRegistration($customer, $storeId, '', $placeOrder, []);
    }

    /**
     * Register $customer, whom a sign-in provider of the store $storeId identified. The provider proves the person,
     * so this entry asks no CAPTCHA. The rules, the limit, the event and the emails are those of register().
     *
     * @throws Mage_Core_Exception_Forbidden|Mage_Core_Exception_TooManyRequests|Mage_Core_Exception_Input|Mage_Core_Exception_Conflict
     *         as register() does
     */
    public function registerFromProvider(Customer $customer, int $storeId): Customer
    {
        $this->assertRegistrationAllowed($storeId);

        return $this->saveRegistration($customer, $storeId, '', null, []);
    }

    /**
     * @throws Mage_Core_Exception_Forbidden
     */
    protected function assertRegistrationAllowed(int $storeId): void
    {
        $this->assertAccountsEnabled($storeId);
        if (!Mage::helper('customer')->isRegistrationAllowed()) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('customer')->__('The registration of new customers is not allowed.'));
        }
    }

    /**
     * Do the registration that register() describes, after the gates of the entry. $save saves the customer, when
     * the entry does not want a plain save.
     *
     * @param (Closure(): void)|null $save
     * @param array<string, mixed> $eventData
     */
    protected function saveRegistration(Customer $customer, int $storeId, string $backUrl, ?Closure $save, array $eventData): Customer
    {
        $limiter = Mage::helper('core')->rateLimiter(
            'create_customer',
            (int) Mage::getStoreConfig(self::XML_PATH_REGISTER_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
            RateLimitScope::Ip,
        );
        $limiter->throwIfTooManyAttempts();

        $isPasswordless = $customer->getPassword() === '' && $this->isPasswordless();
        if ($isPasswordless) {
            $password = Mage::helper('core')->getRandomString(32);
            $customer->setPassword($password)->setPasswordConfirmation($password);
        }
        $this->validateNew($customer, $storeId);
        $limiter->hit();
        $save ? $save() : $customer->save();
        Mage::dispatchEvent('customer_register_success', ['customer' => $customer] + $eventData);

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
     * Register the customer of a guest order, who gives the account token of the order and a password. The customer
     * gets the name and the billing address of the order, and the order becomes an order of the customer. The token
     * proves that the client placed the order, so this entry asks no CAPTCHA.
     *
     * @throws Mage_Core_Exception when the token or its order is not valid
     * @throws Mage_Core_Exception_Forbidden|Mage_Core_Exception_TooManyRequests|Mage_Core_Exception_Input|Mage_Core_Exception_Conflict
     *         as register() does
     */
    public function registerFromOrder(
        #[\SensitiveParameter]
        string $accountToken,
        #[\SensitiveParameter]
        string $password,
    ): Customer {
        $tokenData = Mage::helper('sales/accountToken')->verify($accountToken, 3600);
        if ($tokenData['action'] !== 'create_account') {
            throw new Mage_Core_Exception(Mage::helper('customer')->__('Invalid token action.'));
        }
        /** @var Mage_Sales_Service_Order $orders */
        $orders = Mage::getService('sales/order');
        $order = $orders->findById((int) $tokenData['orderId']);
        if (!$order || $order->getCustomerEmail() !== $tokenData['email']) {
            throw new Mage_Core_Exception(Mage::helper('customer')->__('Order not found.'));
        }

        $storeId = (int) $order->getStoreId();
        // A virtual order can have no billing address
        $billingAddress = $order->getBillingAddress();
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId((int) Mage::app()->getStore($storeId)->getWebsiteId())
            ->setStoreId($storeId)
            ->setEmail($tokenData['email'])
            ->setFirstname($billingAddress ? (string) $billingAddress->getFirstname() : '')
            ->setLastname($billingAddress ? (string) $billingAddress->getLastname() : '')
            ->setPassword($password);
        if ($billingAddress) {
            $address = Mage::getModel('customer/address')->setIsDefaultBilling(true)->setIsDefaultShipping(true);
            foreach (['firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'region', 'postcode', 'country_id', 'telephone'] as $field) {
                $address->setData($field, $billingAddress->getData($field));
            }
            $customer->addAddress($address->setShouldIgnoreValidation(true));
        }
        $this->assertRegistrationAllowed($storeId);
        $this->saveRegistration($customer, $storeId, '', null, []);
        $orders->assignToCustomer($order, $customer);
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
        $this->validateNew($customer, $storeId);
        $save ? $save() : $customer->save();

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
     * Create or save $customer for an admin who edits the customer form, with the emails that the admin asks for.
     * A new customer follows create(): without a password, the customer gets a random one and the email with the
     * link to set a password, from the store $emailStoreId. With $sendWelcomeEmail, a new customer with a password
     * gets the welcome email, and a customer that this save confirms gets the "confirmed" email. A customer of the
     * admin website gets no welcome email.
     * Then $sendPasswordLink emails a link to set a password. Otherwise $newPassword becomes the password, and the
     * customer gets the email about the change.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer, of its new addresses or of
     *                                    the new password
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function saveByStaff(
        Customer $customer,
        int $emailStoreId,
        bool $sendWelcomeEmail = false,
        #[\SensitiveParameter]
        string $newPassword = '',
        bool $sendPasswordLink = false,
    ): Customer {
        $isNew = !$customer->getId();
        $welcomeEmailStoreId = $sendWelcomeEmail && $customer->getWebsiteId() ? $emailStoreId : null;
        if ($isNew) {
            $this->create($customer, Mage_Core_Model_App::ADMIN_STORE_ID, $customer->getPassword() === '' ? $emailStoreId : $welcomeEmailStoreId);
        } else {
            $this->save($customer, $welcomeEmailStoreId);
        }

        if ($sendPasswordLink) {
            $this->sendPasswordLink($customer, $isNew);
        } elseif ($newPassword !== '') {
            $this->setPassword($customer, $newPassword, notify: true);
        }
        return $customer;
    }

    /**
     * Refuse the email of $customer when another customer of the same website uses it. A save does the same check:
     * call this method to tell the client before the save, as the admin form does.
     *
     * @throws Mage_Core_Exception_Conflict
     */
    public function assertEmailNotInUse(Customer $customer): void
    {
        $customer->getResource()->assertEmailNotInUse($customer);
    }

    /**
     * Save a customer for an admin, the CLI or an API integration. The customer must pass the checks of
     * validate() and validateFields() of Mage_Customer_Model_Customer: the names, the email, the customer group
     * and the profile fields. The website of a saved customer cannot change. Another customer of the same website
     * must not use its email: the resource model throws Mage_Core_Exception_Conflict.
     * A caller that changes only the group or the newsletter subscription of many customers gives $validateProfile
     * false: such a change must not fail on a customer whose stored profile has no value for a field that the
     * store made required later. The email and the customer group are checked in each case.
     * With $welcomeEmailStoreId, send the "confirmed" welcome email from that store when this save confirms
     * an account that waited for its confirmation.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem of the customer, or when the customer
     *                                    group does not exist
     * @throws Mage_Core_Exception_Conflict when another customer has the email
     */
    public function save(Customer $customer, ?int $welcomeEmailStoreId = null, bool $validateProfile = true): Customer
    {
        $this->assertValid($customer, $validateProfile);

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

        $this->assertValid($customer, true);

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
     * Delete $customer. Its addresses, its newsletter subscription and its other data go with it.
     */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Check that $password is the stored password of $customer. Each check counts in the password change limit.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client or the customer is over the password change limit
     * @throws Mage_Core_Exception when the password is wrong
     */
    public function verifyPassword(
        Customer $customer,
        #[\SensitiveParameter]
        string $password,
    ): void {
        $core = Mage::helper('core');
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_CHANGE_PASSWORD_RATE_LIMIT);
        $core->rateLimiter('change_password', $limit, self::RATE_LIMIT_WINDOW, RateLimitScope::Ip)->attemptOrThrow();
        $core->rateLimiterBy('change_password', 'change_password:customer:' . $customer->getId(), $limit, self::RATE_LIMIT_WINDOW)->attemptOrThrow();

        // The transport can set the new password on $customer before this check, so compare with the stored hash
        $hash = (string) ($customer->getId() ? $customer->getOrigData('password_hash') : $customer->getPasswordHash());
        if ($hash === '' || !$core->validateHash($password, $hash)) {
            throw new Mage_Core_Exception(Mage::helper('customer')->__('Current password is incorrect.'));
        }
    }

    /**
     * Send the email with the confirmation link to $customer from the store $storeId.
     */
    public function sendConfirmationEmail(Customer $customer, int $storeId): void
    {
        $this->sendNewAccountEmail($customer, 'confirmation', $storeId);
    }

    /**
     * Send the "confirmed" welcome email to $customer from the store $storeId. A failed email does not undo
     * the confirmation.
     */
    public function sendConfirmedEmail(Customer $customer, int $storeId): void
    {
        try {
            $this->sendNewAccountEmail($customer, 'confirmed', $storeId);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Send the new account email $type ('registered', 'confirmation' or 'confirmed') to $customer from the store
     * $storeId.
     */
    protected function sendNewAccountEmail(Customer $customer, string $type, int $storeId, string $backUrl = ''): void
    {
        $customer->sendNewAccountEmail($type, $backUrl, $storeId);
    }

    protected function isPasswordless(): bool
    {
        return Mage::helper('customer')->getMagicLinkRegistrationMode() === Mage_Customer_Model_Config_Registrationmode::MODE_NO_PASSWORD;
    }

    /**
     * Tell $customer by email that staff changed the password.
     */
    protected function sendPasswordChangedEmail(Customer $customer): void
    {
        $customer->sendPasswordReminderEmail();
    }

    /**
     * Send the welcome email with the link to set a password to $customer, from the store $storeId.
     */
    protected function sendPasswordLinkEmail(Customer $customer, int $storeId): void
    {
        $customer->sendPasswordLinkEmail(true, $storeId);
    }

    /**
     * Give a new customer its website, its store and its active state, then check it and its new addresses.
     *
     * @throws Mage_Core_Exception_Input
     */
    private function validateNew(Customer $customer, int $storeId): void
    {
        if (!$customer->getWebsiteId()) {
            $customer->setWebsiteId((int) Mage::app()->getStore($storeId)->getWebsiteId());
        }
        if ($customer->getStoreId() === null) {
            $customer->setStoreId($storeId);
        }
        if ($customer->getData('is_active') === null) {
            $customer->setData('is_active', 1);
        }
        if ($customer->getPasswordConfirmation() === null) {
            $customer->setPasswordConfirmation($customer->getPassword());
        }

        $errors = new Mage_Core_Exception_Input();
        $this->addCustomerErrors($errors, $customer, true);
        foreach ($customer->getAddresses() as $address) {
            $errors->addValidationResult($address->validate(), 'address');
        }
        $errors->throwIfErrors();

        $customer->setPasswordCreatedAt(time());
        $customer->cleanPasswordsValidationData();
    }

    /**
     * Check a saved customer: the whole profile with $validateProfile, or only the email and the customer group.
     *
     * @throws Mage_Core_Exception_Input
     */
    private function assertValid(Customer $customer, bool $validateProfile): void
    {
        $errors = new Mage_Core_Exception_Input();
        $this->addCustomerErrors($errors, $customer, $validateProfile);
        if ($customer->getId() && $customer->getOrigData('website_id') !== null
            && (int) $customer->getOrigData('website_id') !== (int) $customer->getWebsiteId()
        ) {
            $errors->addError('website_id', Mage::helper('customer')->__('The website of a customer cannot change.'));
        }
        $errors->throwIfErrors();
    }

    /**
     * Add the problems of $customer to $errors. The model gives the problems of the email, the customer group, the
     * gender and the date of birth by field. With $validateProfile, add the other problems of validate().
     */
    private function addCustomerErrors(Mage_Core_Exception_Input $errors, Customer $customer, bool $validateProfile): void
    {
        $fieldErrors = $customer->validateFields();
        $errors->addValidationResult($fieldErrors, 'customer');
        $result = $validateProfile ? $customer->validate() : true;
        if (is_array($result)) {
            // validate() repeats the problems that validateFields() gave
            $errors->addValidationResult(array_values(array_diff($result, is_array($fieldErrors) ? $fieldErrors : [])), 'customer');
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
        Mage_Core_Exception_Input::assertValid($customer->validateResetPassword(), 'password');

        $this->invalidateCredentials($customer);
        $customer->cleanPasswordsValidationData();
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
}
