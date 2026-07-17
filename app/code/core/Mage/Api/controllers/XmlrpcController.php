<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_XmlrpcController extends Mage_Api_Controller_Action
{
    // No #[Route] here: /api/xmlrpc is owned by Maho_ApiPlatform_IndexController,
    // which gates the protocol behind apiplatform/protocols/xmlrpc and dispatches
    // to this controller only when enabled. A route here would shadow the gate.
    public function indexAction(): void
    {
        $this->_getServer()->init($this, 'xmlrpc')
            ->run();
    }
}
