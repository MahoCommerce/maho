<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Resource_Oauth_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/oauth_token', 'entity_id');
    }

    public function loadByHashAndType(Mage_Core_Model_Abstract $object, string $hash, string $type): void
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('token_hash = ?', $hash)
            ->where('type = ?', $type)
            ->limit(1);

        $row = $adapter->fetchRow($select);
        if ($row) {
            $object->setData($row);
        }

        $this->unserializeFields($object);
        $this->_afterLoad($object);
    }

    /**
     * The consent row and everything issued under it, in one statement.
     */
    public function revokeGrant(int $grantId): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['revoked' => 1],
            ['entity_id = ? OR parent_id = ?' => $grantId],
        );
    }

    /**
     * Revoke every grant this client holds, across all admins.
     */
    public function revokeClientGrants(string $clientId): int
    {
        return $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['revoked' => 1],
            ['client_id = ?' => $clientId, 'revoked = ?' => 0],
        );
    }

    /**
     * Revoke one consent of this admin, and everything issued under it. False when
     * the consent does not exist, belongs to another admin, or is already revoked.
     */
    public function revokeAdminConsent(int $consentId, int $adminId): bool
    {
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['entity_id'])
            ->where('entity_id = ?', $consentId)
            ->where('type = ?', Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT)
            ->where('admin_id = ?', $adminId)
            ->where('revoked = ?', 0);

        if (!$adapter->fetchOne($select)) {
            return false;
        }

        $this->revokeGrant($consentId);
        return true;
    }

    /**
     * The live consents of this admin, newest first.
     *
     * @return list<array{consent_id: int, client_name: string, created_at: string}>
     */
    public function getAdminConsents(int $adminId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['t' => $this->getMainTable()], ['entity_id', 'created_at'])
            ->joinLeft(['c' => $this->getTable('apiplatform/oauth_client')], 'c.client_id = t.client_id', ['client_name'])
            ->where('t.type = ?', Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT)
            ->where('t.admin_id = ?', $adminId)
            ->where('t.revoked = ?', 0)
            ->order('t.created_at DESC');

        return array_map(
            fn(array $row): array => [
                'consent_id' => (int) $row['entity_id'],
                'client_name' => (string) $row['client_name'],
                'created_at' => (string) $row['created_at'],
            ],
            $adapter->fetchAll($select),
        );
    }

    /**
     * The usernames of the admins with a live consent, by client ID.
     *
     * @param list<string> $clientIds
     * @return array<string, list<string>>
     */
    public function getApprovingAdmins(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['t' => $this->getMainTable()], ['client_id'])
            ->join(['u' => $this->getTable('admin/user')], 'u.user_id = t.admin_id', ['username'])
            ->where('t.type = ?', Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT)
            ->where('t.revoked = ?', 0)
            ->where('t.client_id IN (?)', $clientIds)
            ->order('u.username ASC');

        $admins = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $admins[(string) $row['client_id']][] = (string) $row['username'];
        }

        return array_map(fn(array $usernames): array => array_values(array_unique($usernames)), $admins);
    }

    /**
     * The live consent for this client and admin, or null. Used to decide
     * whether the approval screen can be skipped.
     */
    public function findConsentId(string $clientId, int $adminId, string $scope, string $resource): ?int
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['entity_id'])
            ->where('type = ?', Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT)
            ->where('client_id = ?', $clientId)
            ->where('admin_id = ?', $adminId)
            ->where('scope = ?', $scope)
            ->where('resource = ?', $resource)
            ->where('revoked = ?', 0)
            ->limit(1);

        $id = $adapter->fetchOne($select);

        return $id === false || $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * Codes and refresh tokens that are past expiry, plus grants revoked long
     * enough ago that nobody is looking at them. Consent rows never expire, so
     * they are only removed once revoked.
     */
    public function purgeExpired(int $revokedGraceSeconds = 2592000): int
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getMainTable();

        $deleted = $adapter->delete($table, [
            'type != ?' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT,
            'expires_at IS NOT NULL',
            'expires_at < ?' => time(),
        ]);

        $deleted += $adapter->delete($table, [
            'revoked = ?' => 1,
            'created_at < ?' => Mage::app()->getLocale()->formatDateForDb("-{$revokedGraceSeconds} seconds"),
        ]);

        return $deleted;
    }
}
