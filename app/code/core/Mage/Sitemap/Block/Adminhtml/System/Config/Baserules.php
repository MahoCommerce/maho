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
        $id = $element->getHtmlId();
        $buttonId = $id . '_restore';
        // JSON_HEX_TAG: the value is inlined in a script element, where "</script>" would end it.
        $recommended = json_encode($this->getRecommendedRules(), JSON_THROW_ON_ERROR | JSON_HEX_TAG);
        $label = $this->escapeHtml($this->__('Restore recommended rules'));

        return parent::_getElementHtml($element) . <<<HTML
            <button type="button" id="{$buttonId}" class="scalable" style="margin-top:5px"><span>{$label}</span></button>
            <script>
            (() => {
                const button = document.getElementById('{$buttonId}');
                button.addEventListener('click', () => {
                    const inherit = document.getElementById('{$id}_inherit');
                    if (inherit?.checked) {
                        inherit.checked = false;
                        toggleValueElements(inherit, inherit.parentNode.previousElementSibling);
                    }
                    document.getElementById('{$id}').value = {$recommended};
                });
                // toggleValueElements disables every control in the value cell, this one included.
                document.addEventListener('DOMContentLoaded', () => {
                    document.getElementById('{$id}_inherit')?.addEventListener('click', () => {
                        button.disabled = false;
                        button.classList.remove('disabled');
                    });
                });
            })();
            </script>
            HTML;
    }

    public function getRecommendedRules(): string
    {
        return trim((string) Mage::getConfig()->getNode('default/' . Mage_Sitemap_Model_Robots::XML_PATH_BASE_RULES));
    }
}
