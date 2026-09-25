<?php

/**
 * The applications connected to the account of the admin who is logged in, on My Account.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Connections extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected $_template = 'apiplatform/oauth/connections.phtml';

    /**
     * @return list<array{consent_id: int, client_name: string, created_at: string}>
     */
    public function getConnections(): array
    {
        $adminId = (int) Mage::getSingleton('admin/session')->getUser()?->getId();
        if ($adminId === 0) {
            return [];
        }

        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
        $resource = Mage::getResourceSingleton('apiplatform/oauth_token');
        return $resource->getAdminConsents($adminId);
    }

    public function getDisconnectUrl(): string
    {
        return $this->getUrl('adminhtml/apiplatform_oauth/disconnect');
    }
}
