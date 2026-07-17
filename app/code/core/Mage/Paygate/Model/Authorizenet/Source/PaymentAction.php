<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Paygate
 */

class Mage_Paygate_Model_Authorizenet_Source_PaymentAction
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE,
                'label' => Mage::helper('paygate')->__('Authorize Only'),
            ],
            [
                'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('paygate')->__('Authorize and Capture'),
            ],
        ];
    }
}
