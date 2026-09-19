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
     * Resolve the one image directive the editor preview may carry to a local file path.
     *
     * The preview only serves {{media}} and {{skin}}. Anything else is refused before any
     * directive runs, so the request cannot reach {{block}}, {{config}} or the other CMS directives.
     * The parent filter swallows exceptions, so the directive method is called directly and a
     * bad path reaches the caller as an exception.
     *
     * @param string $value
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function filter($value)
    {
        $value = trim((string) $value);
        if (!preg_match_all(self::CONSTRUCTION_PATTERN, $value, $constructions, PREG_SET_ORDER)
            || count($constructions) !== 1
            || $constructions[0][0] !== $value
        ) {
            Mage::throwException(Mage::helper('cms')->__('Invalid directive.'));
        }

        $construction = $constructions[0];
        $name = strtolower($construction[1]);
        if (!in_array($name, ['media', 'skin'], true)) {
            Mage::throwException(Mage::helper('cms')->__('Invalid directive.'));
        }

        return (string) $this->{$name . 'Directive'}($construction);
    }

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

        $path = \Maho\Io::containedPath(Mage::getBaseDir('media'), (string) $params['url']);
        if ($path === false) {
            Mage::throwException('Invalid url parameter for media directive.');
        }

        return $path;
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
