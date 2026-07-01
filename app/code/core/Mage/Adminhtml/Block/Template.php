<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Template extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    #[\Override]
    protected function _getUrlModelClass()
    {
        return 'adminhtml/url';
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string
     */
    #[\Override]
    public function getFormKey()
    {
        return Mage::getSingleton('core/session')->getFormKey();
    }

    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_block_html_before', ['block' => $this]);
        return parent::_toHtml();
    }

    /**
     * Deleting script tags from string
     *
     * Masks {{...}} template directives before filtering and restores them afterwards, so the
     * malicious-code filter (HTMLPurifier) cannot mangle directives whose nested quotes (e.g.
     * {{media url="..."}} inside an attribute) are invalid HTML. Previews resolve the directives
     * afterwards via getProcessedTemplate().
     *
     * @param string $html
     * @return string
     */
    public function maliciousCodeFilter($html)
    {
        $directives = [];
        $masked = (string) preg_replace_callback('/\{\{.*?\}\}/s', function (array $match) use (&$directives): string {
            $token = 'mahodirective' . count($directives);
            $directives[$token] = $match[0];
            return $token;
        }, (string) $html);

        $filtered = Mage::getSingleton('core/input_filter_maliciousCode')->filter($masked);

        return $directives === [] ? $filtered : strtr($filtered, $directives);
    }
}
