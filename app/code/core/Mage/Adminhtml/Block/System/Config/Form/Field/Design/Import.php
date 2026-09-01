<?php

/**
 * Paste box that fills the theme settings from a daisyUI theme block.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Import extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Renders no input of its own, so nothing is posted and nothing is stored.
     */
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');

        $map = [];
        $colors = [];
        $node = Mage::getConfig()->getNode(Mage_Core_Model_Design_Tokens::CONFIG_NODE);
        foreach ($node ? $node->children() : [] as $name => $entry) {
            $field = 'groups[tokens][fields][' . $name . '][value]';
            foreach (array_map(trim(...), explode(',', (string) $entry->var)) as $variable) {
                if ($variable === '' || isset($map[$variable])) {
                    continue;
                }
                $map[$variable] = $field;
                if (str_starts_with($variable, '--color-')) {
                    $colors[] = $variable;
                }
            }
        }

        $id = $element->getHtmlId() . '_import';
        $config = $helper->jsonEncode([
            'id' => $id,
            'map' => $map,
            'colors' => $colors,
            'labels' => [
                'applied' => $this->__('Filled in %1 settings.'),
                'skipped' => $this->__('%1 more were ignored: Maho works them out itself, or does not use them.'),
                'nothing' => $this->__('Nothing recognizable was found. Paste the block the CSS button gives you.'),
            ],
        ]);

        $placeholder = $this->quoteEscape($this->__(':root { --color-primary: oklch(77% 0.2 61); ... }'));
        $button = $this->escapeHtml($this->__('Fill in the settings'));

        return <<<HTML
            <div class="token-import" id="{$id}">
                <textarea rows="4" placeholder="{$placeholder}"></textarea>
                <button type="button" class="scalable">{$button}</button>
                <p class="token-import-status" hidden></p>
            </div>
            <script>MahoDesignTokens.initImport({$config});</script>
            HTML;
    }
}
