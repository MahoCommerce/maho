<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Oauth
 */

declare(strict_types=1);

class Mage_Oauth_InitiateController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, 1);
        $this->setFlag('', self::FLAG_NO_CHECK_INSTALLATION, 1);
        $this->setFlag('', self::FLAG_NO_PRE_DISPATCH, 1);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, 1);

        return parent::preDispatch();
    }

    /**
     * Index action. Receive initiate request and response OAuth token
     */
    #[Maho\Config\Route('/oauth/initiate', name: 'oauth.initiate', methods: ['POST'])]
    public function indexAction(): void
    {
        /** @var Mage_Oauth_Model_Server $server */
        $server = Mage::getModel('oauth/server');

        $server->initiateToken();
    }
}
