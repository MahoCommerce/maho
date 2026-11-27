<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Resource_Request extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('privacycenter/request', 'request_id');
    }

    /**
     * Clear the token of the request $requestId when its hash is still $tokenHash. Return false when another
     * request used the token first, so that a token works once.
     */
    public function consumeToken(int $requestId, string $tokenHash): bool
    {
        return $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['token_hash' => null, 'token_expires_at' => null],
            ['request_id = ?' => $requestId, 'token_hash = ?' => $tokenHash],
        ) === 1;
    }
}
