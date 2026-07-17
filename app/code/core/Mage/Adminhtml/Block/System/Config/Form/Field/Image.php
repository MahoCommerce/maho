<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Image extends \Maho\Data\Form\Element\Image
{
    /**
     * Get image preview url
     * @return string
     */
    #[\Override]
    protected function _getUrl()
    {
        $url = parent::_getUrl();

        $config = $this->getFieldConfig();
        /** @var \Maho\Simplexml\Element $config */
        if (!empty($config->base_url)) {
            $el = $config->descend('base_url');
            $urlType = empty($el['type']) ? 'link' : (string) $el['type'];
            $url = Mage::getBaseUrl($urlType) . (string) $config->base_url . '/' . $url;
        }

        return $url;
    }
}
