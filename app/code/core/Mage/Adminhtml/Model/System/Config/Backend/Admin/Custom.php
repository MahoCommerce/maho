<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Admin_Custom extends Mage_Core_Model_Config_Data
{
    public const CONFIG_SCOPE                      = 'stores';
    public const CONFIG_SCOPE_ID                   = 0;

    public const XML_PATH_UNSECURE_BASE_URL        = 'web/unsecure/base_url';
    public const XML_PATH_SECURE_BASE_URL          = 'web/secure/base_url';
    public const XML_PATH_UNSECURE_BASE_LINK_URL   = 'web/unsecure/base_link_url';
    public const XML_PATH_SECURE_BASE_LINK_URL     = 'web/secure/base_link_url';

    /**
     * Validate value before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();

        if (!empty($value) && !str_ends_with($value, '}}')) {
            $value = rtrim($value, '/') . '/';
        }

        $this->setValue($value);
        return $this;
    }

    /**
     * Change secure/unsecure base_url after use_custom_url was modified
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        $useCustomUrl = $this->getData('groups/url/fields/use_custom/value');
        $value = $this->getValue();

        if ($useCustomUrl == 1 && empty($value)) {
            return $this;
        }

        if ($useCustomUrl == 1) {
            Mage::getConfig()->saveConfig(
                self::XML_PATH_SECURE_BASE_URL,
                $value,
                self::CONFIG_SCOPE,
                self::CONFIG_SCOPE_ID,
            );
            Mage::getConfig()->saveConfig(
                self::XML_PATH_UNSECURE_BASE_URL,
                $value,
                self::CONFIG_SCOPE,
                self::CONFIG_SCOPE_ID,
            );
        }

        return $this;
    }
}
