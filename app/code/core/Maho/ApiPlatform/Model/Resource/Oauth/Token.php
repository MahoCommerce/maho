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
