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
    // No #[Route] here: /api/jsonrpc is owned by Maho_ApiPlatform_IndexController,
    // which gates the protocol behind apiplatform/protocols/jsonrpc and dispatches
    // to this controller only when enabled. A route here would shadow the gate.
    public function indexAction(): void
    {
        $this->_getServer()->init($this, 'jsonrpc')
            ->run();
    }
}
