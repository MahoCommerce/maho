<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * @deprecated since 27.1, use Mage_Customer_Model_Config_Forgotpassword
 */
class Mage_Adminhtml_Model_System_Config_Source_Customer_Forgotpassword extends Mage_Customer_Model_Config_Forgotpassword
{
    #[\Deprecated('Use Mage_Customer_Model_Config_Forgotpassword::FLOW_DISABLED.', since: '27.1')]
    public const FORGOTPASS_FLOW_DISABLED = self::FLOW_DISABLED;

    #[\Deprecated('Use Mage_Customer_Model_Config_Forgotpassword::FLOW_IP_EMAIL.', since: '27.1')]
    public const FORGOTPASS_FLOW_IP_EMAIL = self::FLOW_IP_EMAIL;

    #[\Deprecated('Use Mage_Customer_Model_Config_Forgotpassword::FLOW_IP.', since: '27.1')]
    public const FORGOTPASS_FLOW_IP = self::FLOW_IP;

    #[\Deprecated('Use Mage_Customer_Model_Config_Forgotpassword::FLOW_EMAIL.', since: '27.1')]
    public const FORGOTPASS_FLOW_EMAIL = self::FLOW_EMAIL;
}
