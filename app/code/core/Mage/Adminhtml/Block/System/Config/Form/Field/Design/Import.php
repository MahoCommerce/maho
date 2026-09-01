<?php

/**
 * Button that opens a dialog and fills the theme settings from a daisyUI theme block.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Import extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    private const GENERATOR_URL = 'https://daisyui.com/theme-generator/';

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
        $apply = $this->__('Fill in the settings');
        $config = $helper->jsonEncode([
            'id' => $id,
            'map' => $map,
            'colors' => $colors,
            'labels' => [
                'title' => $this->__('Import a daisyUI Theme'),
                'apply' => $apply,
                'applied' => $this->__('Filled in %1 settings.'),
                'skipped' => $this->__('%1 more were ignored: Maho works them out itself, or does not use them.'),
                'nothing' => $this->__('Nothing recognizable was found. Paste the block the CSS button gives you.'),
            ],
        ]);

        $open = $this->escapeHtml($this->__('Import a theme'));
        $headline = $this->escapeHtml($this->__('Start from a ready palette'));
        $lead = $this->escapeHtml($this->__('Paste a daisyUI theme and Maho fills in the settings below. Every value stays editable.'));
        $placeholder = $this->quoteEscape($this->__(':root { --color-primary: oklch(77% 0.2 61); ... }'));
        $link = '<a href="' . self::GENERATOR_URL . '" target="_blank" rel="noopener">'
            . $this->escapeHtml($this->__('daisyUI theme generator')) . '</a>';

        $steps = '';
        foreach ([
            $this->__('Open the %s and design a palette.', $link),
            $this->__('Press the %s button there to copy the theme.', '<strong>{} CSS</strong>'),
            $this->__('Paste it below, then press %s.', '<strong>' . $this->escapeHtml($apply) . '</strong>'),
        ] as $step) {
            $steps .= '<li>' . $step . '</li>';
        }
        $note = $this->escapeHtml($this->__('Maho takes the colors, fonts, radii, border and depth, and reports what it leaves out.'));

        return <<<HTML
            <div class="token-import" id="{$id}">
                <div class="token-import-text">
                    <strong>{$headline}</strong>
                    <span>{$lead}</span>
                    <span class="token-import-status" hidden></span>
                </div>
                <button type="button" class="scalable">{$open}</button>
                <template>
                    <div class="token-import-dialog">
                        <ol>{$steps}</ol>
                        <textarea rows="12" spellcheck="false" placeholder="{$placeholder}"></textarea>
                        <p class="token-import-note">{$note}</p>
                        <p class="token-import-error" hidden></p>
                    </div>
                </template>
            </div>
            <script>MahoDesignTokens.initImport({$config});</script>
            HTML;
    }
}
