<?php

/**
 * Warns that a robots.txt file on disk overrides everything configured here.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Block_Adminhtml_System_Config_Robots extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        $file = Mage::getBaseDir('public') . DS . 'robots.txt';
        if (is_file($file)) {
            $html .= '<p class="note"><span style="color:#eb5202">'
                . $this->escapeHtml($this->__('The file %s exists and is served by the web server, so these settings have no effect. Delete it to use the settings below.', $file))
                . '</span></p>';
        }

        return $html;
    }
}
