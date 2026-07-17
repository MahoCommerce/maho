<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Variable_Config
{
    /**
     * Prepare variable wysiwyg config
     *
     * @param \Maho\DataObject $config
     * @return array
     */
    public function getWysiwygPluginSettings($config)
    {
        $variableConfig = [];

        // Add variable URL for WYSIWYG editor
        $variableConfig['variable_window_url'] = $this->getVariablesWysiwygActionUrl();

        // Add plugin for plain text editor
        $pluginConfig = [
            'name' => 'variables',
            'options' => [
                'title' => Mage::helper('adminhtml')->__('Insert Variable...'),
                'onclick' => [
                    'search' => ['html_id'],
                    'subject' => "Variables.openDialog('{$this->getVariablesWysiwygActionUrl()}', { target_id: '{{html_id}}' });",
                ],
                'class'   => 'add-variable plugin',
            ],
        ];

        $variableConfig['plugins'] = array_merge($config->getData('plugins'), [$pluginConfig]);

        return $variableConfig;
    }

    /**
     * Return url of action to get variables
     *
     * @return string
     */
    public function getVariablesWysiwygActionUrl()
    {
        return Mage::getSingleton('adminhtml/url')->getUrl('*/system_variable/wysiwygPlugin');
    }
}
