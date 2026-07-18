<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Adminhtml_Template_Filter extends Mage_Cms_Model_Template_Filter
{
    /**
     * Retrieve media file local path instead of URL, so it can be read by Intervention Image
     *
     * @param array $construction
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function mediaDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for media directive.');
        }

        return Mage::getBaseDir('media') . DS . $params['url'];
    }

    /**
     * Retrieve skin file local path instead of URL, so it can be read by Intervention Image
     *
     * @param array $construction
     * @return string
     */
    #[\Override]
    public function skinDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for skin directive.');
        }

        $file = $params['url'];
        unset($params['url']);
        $params['_type'] = 'skin';

        return Mage::getDesign()->getFilename($file, $params);
    }
}
