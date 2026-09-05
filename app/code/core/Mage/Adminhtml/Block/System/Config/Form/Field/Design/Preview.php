<?php

/**
 * Live storefront preview that repaints as the theme settings are typed.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Preview extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Renders no input of its own, so nothing is posted and nothing is stored.
     */
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $store = $this->_previewStore();
        if ($store === null) {
            return '';
        }

        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');

        $tokens = [];
        $node = Mage::getConfig()->getNode(Mage_Core_Model_Design_Tokens::CONFIG_NODE);
        foreach ($node ? $node->children() : [] as $name => $entry) {
            $vars = array_values(array_filter(array_map(trim(...), explode(',', (string) $entry->var))));
            if ($vars) {
                $tokens[] = [
                    'name' => 'groups[tokens][fields][' . $name . '][value]',
                    'vars' => $vars,
                    'derive' => trim((string) $entry->derive),
                ];
            }
        }

        $base = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        $id = $element->getHtmlId() . '_preview';
        $config = $helper->jsonEncode([
            'id' => $id,
            'base' => $base,
            'store' => $store->getCode(),
            'tokens' => $tokens,
            'fontUrl' => 'groups[tokens][fields][font_stylesheet][value]',
        ]);

        // Real device widths, so the storefront picks the layout it would use there
        $devices = '';
        foreach ([390 => $this->__('Mobile'), 820 => $this->__('Tablet'), 1280 => $this->__('Desktop')] as $width => $label) {
            $devices .= '<button type="button" data-width="' . $width . '">' . $this->escapeHtml($label) . '</button>';
        }

        // Store views can share a base URL, so name the one the preview must render
        $url = $this->escapeHtml($base . (str_contains($base, '?') ? '&' : '?')
            . '___store=' . rawurlencode($store->getCode()));
        $title = $this->escapeHtml($this->__('Live preview of %s', $store->getName()));

        return <<<HTML
            <div class="token-preview">
                <div class="token-preview-devices">{$devices}</div>
                <div class="token-preview-frame">
                    <iframe id="{$id}" src="{$url}" title="{$title}" loading="lazy"></iframe>
                </div>
            </div>
            <script>MahoDesignTokens.initPreview({$config});</script>
            HTML;
    }

    /**
     * The store the settings apply to. A wider scope previews its default store view.
     */
    private function _previewStore(): ?Mage_Core_Model_Store
    {
        $code = $this->getRequest()->getParam('store');
        if ($code) {
            return Mage::app()->getStore($code);
        }

        $website = $this->getRequest()->getParam('website');
        $store = $website
            ? Mage::app()->getWebsite($website)->getDefaultStore()
            : Mage::app()->getDefaultStoreView();

        return $store ?: null;
    }
}
