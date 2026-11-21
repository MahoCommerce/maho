<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

/**
 * @deprecated since 26.9 Use Maho_ApiPlatform instead.
 */
class Mage_Oauth_Model_Resource_Nonce extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * The `oauth_nonce` table has no auto‑increment primary key. The base
     * resource model assumes an identity column and attempts to retrieve the
     * last inserted ID, which fails on PostgreSQL and MySQL when the table
     * lacks a primary key. Setting this flag disables the identity lookup
     * after an insert, allowing the model to persist non‑PK rows without
     * raising `Doctrine\DBAL\Driver\Exception\NoIdentityValue`.
     */
    protected $_isPkAutoIncrement = false;
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('oauth/nonce', null);
    }

    /**
     * Delete old entries
     *
     * @param int $minutes Delete entries older than
     * @return int
     */
    public function deleteOldEntries($minutes)
    {
        if ($minutes > 0) {
            $adapter = $this->_getWriteAdapter();

            return $adapter->delete(
                $this->getMainTable(),
                $adapter->quoteInto('timestamp <= ?', time() - $minutes * 60),
            );
        }
        return 0;
    }
}
