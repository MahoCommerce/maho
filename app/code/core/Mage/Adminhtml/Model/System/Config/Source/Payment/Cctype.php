<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Payment_Cctype
{
    public function toOptionArray(): array
    {
        $options =  [];

        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
            $options[] = [
                'value' => $code,
                'label' => $name,
            ];
        }

        return $options;
    }
}
