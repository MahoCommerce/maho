<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

class Mage_Payment_Model_Source_Invoice
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('core')->__('Yes'),
            ],
            [
                'value' => '',
                'label' => Mage::helper('core')->__('No'),
            ],
        ];
    }
}
