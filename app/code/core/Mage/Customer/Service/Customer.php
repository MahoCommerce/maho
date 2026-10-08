<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\ApiPlatform\Trait\FilterValueTrait;

/**
 * Finds, searches and saves customers, and changes and resets their passwords.
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
     * Save a customer. Another customer of the same website must not use its email.
     */
    public function save(\Mage_Customer_Model_Customer $customer): \Mage_Customer_Model_Customer
    {
        $email = $customer->getEmail();
        if ($email !== null && $customer->dataHasChangedFor('email')) {
            $existing = $this->getByEmail($email);
            if ($existing && $existing->getId() !== $customer->getId()) {
                throw new \Mage_Core_Exception_Conflict('This email is already in use.');
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
            throw new \Mage_Core_Exception('Current password is incorrect.');
        }

        $customer->setPassword($newPassword);
        $customer->save();

        return true;
    }

    /**
     * Reset password using token
     */
    public function resetPassword(#[\SensitiveParameter]
        string $email, #[\SensitiveParameter]
        string $token, #[\SensitiveParameter]
        string $newPassword): bool
    {
        $customer = $this->getByEmail($email);

        if (!$customer) {
            throw new \Mage_Core_Exception('Invalid or expired reset token.');
        }

        // Validate reset token (use hash_equals to prevent timing attacks)
        $storedToken = $customer->getRpToken();
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            throw new \Mage_Core_Exception('Invalid or expired reset token.');
        }

        if ($customer->isResetPasswordLinkTokenExpired()) {
            // Use the same message as an invalid token so the response does not
            // confirm to a caller that a supplied token was correct-but-expired.
            throw new \Mage_Core_Exception('Invalid or expired reset token.');
        }

        $customer->setPassword($newPassword);
        $customer->clearMagicLinkToken();
        $customer->save();

        return true;
    }
}
