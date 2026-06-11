<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

class Mage_Api_XmlrpcController extends Mage_Api_Controller_Action
{
    #[Maho\Config\Route('/api/xmlrpc', name: 'api.xmlrpc', methods: ['POST'])]
    public function indexAction(): void
    {
        $this->_getServer()->init($this, 'xmlrpc')
            ->run();
    }
}
