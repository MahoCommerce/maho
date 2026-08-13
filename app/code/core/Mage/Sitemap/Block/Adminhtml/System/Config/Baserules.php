<?php

/**
 * Base rules textarea with a button that restores the rules Maho ships.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Block_Adminhtml_System_Config_Baserules extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');

        $id = $element->getHtmlId();
        $buttonId = $id . '_restore';
        $recommended = $helper->jsonEncode($this->getRecommendedRules());
        $label = $this->escapeHtml($this->__('Restore recommended rules'));

        return parent::_getElementHtml($element) . <<<HTML
            <button type="button" id="{$buttonId}" class="scalable" style="margin-top:5px"><span>{$label}</span></button>
            <script>
            document.getElementById('{$buttonId}').addEventListener('click', () => {
                const inherit = document.getElementById('{$id}_inherit');
                if (inherit?.checked) {
                    inherit.checked = false;
                    toggleValueElements(inherit, inherit.parentNode.previousElementSibling);
                }
                document.getElementById('{$id}').value = {$recommended};
            });
            </script>
            HTML;
    }

    public function getRecommendedRules(): string
    {
        return trim((string) Mage::getConfig()->getNode('default/' . Mage_Sitemap_Model_Robots::XML_PATH_BASE_RULES));
    }
}
