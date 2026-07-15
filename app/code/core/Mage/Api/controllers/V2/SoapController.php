<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

class Mage_Api_V2_SoapController extends Mage_Api_Controller_Action
{
    // No #[Route] here: /api/v2_soap is owned by Maho_ApiPlatform_IndexController,
    // which gates the protocol behind apiplatform/protocols/v2_soap and dispatches
    // to this controller only when enabled. A route here would shadow the gate.
    public function indexAction(): void
    {
        if (Mage::helper('api/data')->isComplianceWSI()) {
            $handlerName = 'soap_wsi';
        } else {
            $handlerName = 'soap_v2';
        }

        $this->_getServer()->init($this, $handlerName, $handlerName)->run();
    }
}
