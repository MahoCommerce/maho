<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Resource_Identity extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('sociallogin/identity', 'identity_id');
    }

    public function loadByProviderIdentity(
        Maho_SocialLogin_Model_Identity $identity,
        string $provider,
        string $providerId,
        ?int $websiteId,
    ): void {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('provider = ?', $provider)
            ->where('provider_id = ?', $providerId)
            ->limit(1);
        if ($websiteId !== null) {
            $select->where('website_id = ?', $websiteId);
        }

        $data = $adapter->fetchRow($select);
        if ($data) {
            $identity->setData($data);
        }
        $this->_afterLoad($identity);
    }
}
