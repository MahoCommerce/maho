<?php

/**
 * The new email of a customer that waits for the confirmation link. A customer has one pending change at most.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Model_Resource_Email_Change extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customer/email_change', 'customer_id');
        $this->_isPkAutoIncrement = false;
    }

    /**
     * Save $email as the pending change of the customer $customerId. It replaces the pending change of the customer.
     * $expiresAt is a UTC date and time.
     */
    public function savePending(int $customerId, #[\SensitiveParameter]
        string $email, string $tokenHash, string $expiresAt): void
    {
        $this->_getWriteAdapter()->insertOnDuplicate($this->getMainTable(), [
            'customer_id' => $customerId,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ], ['email', 'token_hash', 'expires_at', 'created_at']);
    }

    /**
     * Return the pending change with the token hash $tokenHash, expired or not.
     *
     * @return array{customer_id: int, email: string, expires_at: string}|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $adapter = $this->_getReadAdapter();
        $row = $adapter->fetchRow(
            $adapter->select()
                ->from($this->getMainTable(), ['customer_id', 'email', 'expires_at'])
                ->where('token_hash = ?', $tokenHash),
        );
        if (!is_array($row)) {
            return null;
        }
        return [
            'customer_id' => (int) $row['customer_id'],
            'email' => (string) $row['email'],
            'expires_at' => (string) $row['expires_at'],
        ];
    }

    /**
     * Return the new email of the customer $customerId when its link did not expire.
     */
    public function getPendingEmail(int $customerId): ?string
    {
        $adapter = $this->_getReadAdapter();
        $email = $adapter->fetchOne(
            $adapter->select()
                ->from($this->getMainTable(), ['email'])
                ->where('customer_id = ?', $customerId)
                ->where('expires_at > ?', Mage_Core_Model_Locale::nowUtc()),
        );
        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * Delete the pending change of the customer $customerId when its token hash is still $tokenHash. Return false
     * when another request used the token first, so that a token works once.
     */
    public function consume(int $customerId, string $tokenHash): bool
    {
        return $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            ['customer_id = ?' => $customerId, 'token_hash = ?' => $tokenHash],
        ) === 1;
    }

    /**
     * Delete the pending change of the customer $customerId.
     */
    public function deleteForCustomer(int $customerId): void
    {
        $this->_getWriteAdapter()->delete($this->getMainTable(), ['customer_id = ?' => $customerId]);
    }
}
