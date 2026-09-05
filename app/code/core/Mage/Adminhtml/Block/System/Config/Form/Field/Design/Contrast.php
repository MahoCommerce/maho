<?php

/**
 * Color field that reports its WCAG contrast ratio against a partner field.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Contrast extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        $against = (string) ($element->getData('original_data')['contrast_against'] ?? '');
        if ($against === '') {
            return $html;
        }

        // The sibling field names what the ratio compares against
        $fields = $element->getData('field_config')?->getParent();
        $againstLabel = trim((string) ($fields->{$against}->label ?? ''));
        $caption = $againstLabel === ''
            ? $this->__('Contrast')
            : $this->__('Contrast with %s', Mage::helper('core')->__($againstLabel));

        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');
        $config = $helper->jsonEncode([
            'fieldId' => $element->getHtmlId(),
            'partner' => preg_replace('/\[fields\]\[[^\]]+\]/', '[fields][' . $against . ']', (string) $element->getName()),
            'labels' => [
                'aaa' => $this->__('AAA'),
                'aa' => $this->__('AA'),
                'aaLarge' => $this->__('AA large text'),
                'fail' => $this->__('fails AA'),
            ],
        ]);

        $caption = $this->escapeHtml($caption);

        $caption = $this->escapeHtml($caption);
        $htmlId = $helper->jsonEncode($element->getHtmlId());

        return $html . <<<HTML
            <span class="contrast-check" data-for={$htmlId} hidden>
                <span class="contrast-caption">{$caption}</span>
                <span class="contrast-ratio"></span>
            </span>
            <script>MahoDesignTokens.initContrast({$config});</script>
            HTML;
    }
}
