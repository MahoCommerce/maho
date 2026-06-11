<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Urlrewrite_Link extends Mage_Core_Block_Abstract
{
    /**
     * Render output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if ($this->getItem()) {
            return '<p>' . $this->getLabel() . ' <a href="' . $this->getItemUrl() . '">'
                . $this->escapeHtml($this->getItem()->getName()) . '</a></p>';
        }
        return '';
    }
}
