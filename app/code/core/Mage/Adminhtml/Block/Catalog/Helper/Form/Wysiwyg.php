<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Helper_Form_Wysiwyg extends \Maho\Data\Form\Element\Textarea
{
    /**
     * Retrieve additional html and put it at the end of element html
     *
     * @return string
     */
    #[\Override]
    public function getAfterElementHtml()
    {
        $html = parent::getAfterElementHtml();
        if ($this->getIsWysiwygEnabled()) {
            $wysiwygUrl = Mage::helper('adminhtml')->getUrl('*/*/wysiwyg', [
                'store_id' => Mage::getSingleton('cms/wysiwyg_config')->getStoreId(),
            ]);
            $html .= Mage::getSingleton('core/layout')
                ->createBlock('adminhtml/widget_button', '', [
                    'label'    => Mage::helper('catalog')->__('WYSIWYG Editor'),
                    'type'     => 'button',
                    'disabled' => $this->getDisabled() || $this->getReadonly(),
                    'class'    => 'btn-wysiwyg',
                    'style'    => 'margin-right: 4px;',
                    'onclick'  => "catalogWysiwygEditor.open('$wysiwygUrl', '{$this->getHtmlId()}')",
                ])->toHtml();
        }
        if ($this->getEntityAttribute()->getIsWysiwygEnabled()) {
            $validateUrl = Mage::getSingleton('adminhtml/url')->getUrl('*/cms_wysiwyg/validateHtml');
            $html .= Mage::getSingleton('core/layout')
                ->createBlock('adminhtml/widget_button', '', [
                    'label'    => Mage::helper('cms')->__('Validate HTML'),
                    'type'     => 'button',
                    'class'    => 'validate-html',
                    'onclick'  => "validateHtmlContent('{$this->getHtmlId()}', '$validateUrl');",
                ])->toHtml();
        }
        return $html;
    }

    /**
     * Check whether wysiwyg enabled or not
     *
     * @return bool
     */
    public function getIsWysiwygEnabled()
    {
        if (Mage::helper('catalog')->isModuleEnabled('Mage_Cms')) {
            return (bool) (Mage::getSingleton('cms/wysiwyg_config')->isEnabled()
                && $this->getEntityAttribute()->getIsWysiwygEnabled());
        }

        return false;
    }
}
