<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2023-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

class Mage_Api_JsonrpcController extends Mage_Api_Controller_Action
{
    #[Maho\Config\Route('/api/jsonrpc', name: 'api.jsonrpc', methods: ['POST'])]
    public function indexAction(): void
    {
        $this->_getServer()->init($this, 'jsonrpc')
            ->run();
    }
}
