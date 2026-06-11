<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

class Mage_Widget_Model_Observer
{
    /**
     * Add additional settings to wysiwyg config for Widgets Insertion Plugin
     *
     * @return $this
     */
    #[Maho\Config\Observer('cms_wysiwyg_config_prepare', area: 'adminhtml')]
    public function prepareWidgetsPluginConfig(\Maho\Event\Observer $observer)
    {
        $config = $observer->getEvent()->getConfig();

        if ($config->getData('add_widgets')) {
            $settings = Mage::getModel('widget/widget_config')->getPluginSettings($config);
            $config->addData($settings);
        }
        return $this;
    }
}
