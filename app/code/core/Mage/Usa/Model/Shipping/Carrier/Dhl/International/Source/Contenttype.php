<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Dhl_International_Source_Contenttype
{
    public function toOptionArray(): array
    {
        return [
            ['label' => Mage::helper('usa')->__('Documents'),
                'value' => Mage_Usa_Model_Shipping_Carrier_Dhl_International::DHL_CONTENT_TYPE_DOC],
            ['label' => Mage::helper('usa')->__('Non documents'),
                'value' => Mage_Usa_Model_Shipping_Carrier_Dhl_International::DHL_CONTENT_TYPE_NON_DOC],
        ];
    }
}
