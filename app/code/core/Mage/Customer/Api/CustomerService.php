<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

/**
 * Customer Service - Business logic for customer operations.
 */
class CustomerService
{
    /**
     * Authenticate customer with email and password
     *
     * Delegates to Mage_Customer_Model_Customer::authenticate() which handles
     * loadByEmail, validatePassword, confirmation check, and fires the
     * customer_customer_authenticated event.
     *
     * @throws \Mage_Core_Exception on invalid credentials or unconfirmed account
     */
    public function authenticate(#[\SensitiveParameter]
        string $email, #[\SensitiveParameter]
        string $password): \Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId());

        $customer->authenticate($email, $password);

        return $customer;
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $id): ?\Mage_Customer_Model_Customer
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
    public function getCustomerByEmail(#[\SensitiveParameter]
        string $email): ?\Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($email);

        return $customer->getId() ? $customer : null;
    }

    public const MAX_PAGE_SIZE = 100;

    /**
     * Search customers like the admin customer grid, newest first.
     *
     * Every word of $search must appear in the email, the first name, the last name,
     * or the telephone of an address of the customer. The match ignores case.
     * $email is an exact match and $telephone matches the start of an address telephone.
     *
     * @param int[]|null $websiteIds Restrict matches to these websites; null means unrestricted
     * @return array{customers: list<\Mage_Customer_Model_Customer>, total: int}
     */
    public function searchCustomers(
        string $search = '',
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $telephone = null,
        int $page = 1,
        int $pageSize = 20,
        ?array $websiteIds = null,
        ?int $groupId = null,
        ?int $websiteId = null,
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

        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
     * Create customer with minimal information (for POS quick checkout)
     */
    public function createCustomerQuick(
        string $firstName,
        string $lastName,
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $telephone = null,
        ?int $groupId = null,
    ): \Mage_Customer_Model_Customer {
        $customer = \Mage::getModel('customer/customer');

        // If no email provided, generate a temporary one
        if (empty($email)) {
            $email = 'guest_' . bin2hex(random_bytes(8)) . '@pos.local';
        }

        $customer->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->setStore(\Mage::app()->getStore())
            ->setFirstname($firstName)
            ->setLastname($lastName)
            ->setEmail($email);

        if ($groupId !== null) {
            $customer->setGroupId($groupId);
        }

        // Save customer
        $customer->save();

        // Add default address if telephone provided
        if ($telephone) {
            $address = \Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId())
                ->setFirstname($firstName)
                ->setLastname($lastName)
                ->setTelephone($telephone)
                ->setCountryId(\Maho\ApiPlatform\Service\StoreDefaults::getCountryId()) // Default to Australia
                ->setIsDefaultBilling()
                ->setIsDefaultShipping();

            try {
                $address->save();
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        return $customer;
    }

    /**
     * Register new customer with full information
     */
    public function registerCustomer(
        string $firstName,
        string $lastName,
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        bool $isSubscribed = false,
    ): \Mage_Customer_Model_Customer {
        // Check if email already exists
        if ($this->getCustomerByEmail($email)) {
            throw new \Exception('A customer with this email already exists.');
        }

        $customer = \Mage::getModel('customer/customer');

        $customer->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->setStore(\Mage::app()->getStore())
            ->setFirstname($firstName)
            ->setLastname($lastName)
            ->setEmail($email)
            ->setPassword($password)
            ->setIsSubscribed($isSubscribed);

        try {
            $customer->save();
        } catch (\Throwable $e) {
            // The pre-check above is a TOCTOU: a concurrent registration with the
            // same email can slip in between it and save(), tripping the unique
            // constraint. Re-check and surface the same clean error rather than a
            // raw DB exception (500).
            if ($this->getCustomerByEmail($email)) {
                throw new \Exception('A customer with this email already exists.');
            }
            throw $e;
        }

        return $customer;
    }

    /**
     * Update customer information
     */
    public function updateCustomer(
        \Mage_Customer_Model_Customer $customer,
        array $data,
    ): \Mage_Customer_Model_Customer {
        if (isset($data['firstName'])) {
            $customer->setFirstname($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $customer->setLastname($data['lastName']);
        }

        if (isset($data['email'])) {
            // Check if email is already used by another customer
            $existing = $this->getCustomerByEmail($data['email']);
            if ($existing && $existing->getId() !== $customer->getId()) {
                throw new \Exception('This email is already in use.');
            }
            $customer->setEmail($data['email']);
        }

        if (isset($data['isSubscribed'])) {
            $customer->setIsSubscribed((bool) $data['isSubscribed']);
        }

        // Optional profile attributes; a present-but-null value clears the attribute
        foreach (['prefix', 'middlename', 'suffix', 'gender', 'dob'] as $field) {
            if (array_key_exists($field, $data)) {
                $customer->setData($field, $data[$field]);
            }
        }

        $customer->save();

        return $customer;
    }

    /**
     * Change customer password
     */
    public function changePassword(
        \Mage_Customer_Model_Customer $customer,
        string $currentPassword,
        #[\SensitiveParameter]
        string $newPassword,
    ): bool {
        // Validate current password
        if (!$customer->validatePassword($currentPassword)) {
            throw new \Exception('Current password is incorrect.');
        }

        $customer->setPassword($newPassword);
        $customer->save();

        return true;
    }

    /**
     * Request password reset token
     */
    public function requestPasswordReset(#[\SensitiveParameter]
        string $email): bool
    {
        $customer = $this->getCustomerByEmail($email);

        if (!$customer) {
            // Don't reveal if email exists or not (security)
            return true;
        }

        try {
            $customer->sendPasswordResetConfirmationEmail();
            return true;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new \Exception('Unable to send password reset email.');
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword(#[\SensitiveParameter]
        string $email, #[\SensitiveParameter]
        string $token, #[\SensitiveParameter]
        string $newPassword): bool
    {
        $customer = $this->getCustomerByEmail($email);

        if (!$customer) {
            throw new \Exception('Invalid email or token.');
        }

        // Validate reset token (use hash_equals to prevent timing attacks)
        $storedToken = $customer->getRpToken();
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            throw new \Exception('Invalid or expired reset token.');
        }

        if ($customer->isResetPasswordLinkTokenExpired()) {
            // Use the same message as an invalid token so the response does not
            // confirm to a caller that a supplied token was correct-but-expired.
            throw new \Exception('Invalid or expired reset token.');
        }

        $customer->setPassword($newPassword);
        $customer->clearMagicLinkToken();
        $customer->save();

        return true;
    }
}
