<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Customer_Forgotpassword
{
    public const FORGOTPASS_FLOW_DISABLED  = 0;
    public const FORGOTPASS_FLOW_IP_EMAIL  = 1;
    public const FORGOTPASS_FLOW_IP        = 2;
    public const FORGOTPASS_FLOW_EMAIL     = 3;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::FORGOTPASS_FLOW_DISABLED, 'label' => Mage::helper('adminhtml')->__('Disabled')],
            ['value' => self::FORGOTPASS_FLOW_IP_EMAIL, 'label' => Mage::helper('adminhtml')->__('By IP and Email')],
            ['value' => self::FORGOTPASS_FLOW_IP,       'label' => Mage::helper('adminhtml')->__('By IP')],
            ['value' => self::FORGOTPASS_FLOW_EMAIL,    'label' => Mage::helper('adminhtml')->__('By Email')],
        ];
    }
}
