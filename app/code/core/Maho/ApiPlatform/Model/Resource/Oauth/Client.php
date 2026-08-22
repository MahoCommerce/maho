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
}
