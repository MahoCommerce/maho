<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Resource_Oauth_Client extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/oauth_client', 'entity_id');
    }

    /**
     * A single-column write, so a token exchange does not rewrite the whole row.
     */
    public function touchLastUsedAt(int $entityId, string $timestamp): void
    {
        if ($entityId <= 0) {
            return;
        }

        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['last_used_at' => $timestamp],
            ['entity_id = ?' => $entityId],
        );
    }

    /**
     * Delete the clients that hold no live consent, with all their tokens. Open
     * registration lets anyone add a client, so unused ones pile up.
     *
     * @param list<string> $clientIds
     * @return list<string> the deleted client IDs
     */
    public function deleteUnusedClients(array $clientIds): array
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $tokenResource */
        $tokenResource = Mage::getResourceSingleton('apiplatform/oauth_token');
        $approved = array_map(strval(...), array_keys($tokenResource->getApprovingAdmins($clientIds)));
        $unused = array_values(array_diff($clientIds, $approved));
        if ($unused === []) {
            return [];
        }

        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();
        try {
            $adapter->delete($this->getTable('apiplatform/oauth_token'), ['client_id IN (?)' => $unused]);
            $adapter->delete($this->getMainTable(), ['client_id IN (?)' => $unused]);
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $unused;
    }
}
