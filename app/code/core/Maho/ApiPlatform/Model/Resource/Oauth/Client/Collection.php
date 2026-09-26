<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Model_Resource_Oauth_Client_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/oauth_client');
    }

    /**
     * Add the usernames of the admins with a live consent to each client, as `approved_by`.
     */
    public function addApprovingAdmins(): static
    {
        $this->setFlag('add_approving_admins', true);
        return $this;
    }

    #[\Override]
    protected function _afterLoad(): static
    {
        parent::_afterLoad();

        if ($this->getFlag('add_approving_admins')) {
            /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $tokenResource */
            $tokenResource = Mage::getResourceSingleton('apiplatform/oauth_token');
            $clientIds = array_map(fn(Maho_ApiPlatform_Model_Oauth_Client $client): string => (string) $client->getData('client_id'), array_values($this->getItems()));
            $admins = $tokenResource->getApprovingAdmins($clientIds);

            foreach ($this->getItems() as $client) {
                $client->setData('approved_by', implode(', ', $admins[(string) $client->getData('client_id')] ?? []));
            }
        }

        return $this;
    }
}
