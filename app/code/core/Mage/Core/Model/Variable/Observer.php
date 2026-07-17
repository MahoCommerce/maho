<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Variable_Observer
{
    /**
     * Add variable wysiwyg plugin config
     *
     * @return $this
     */
    #[Maho\Config\Observer('cms_wysiwyg_config_prepare', area: 'adminhtml')]
    public function prepareWysiwygPluginConfig(\Maho\Event\Observer $observer)
    {
        $config = $observer->getEvent()->getConfig();

        if ($config->getData('add_variables')) {
            $settings = Mage::getModel('core/variable_config')->getWysiwygPluginSettings($config);
            $config->addData($settings);
        }
        return $this;
    }
}
