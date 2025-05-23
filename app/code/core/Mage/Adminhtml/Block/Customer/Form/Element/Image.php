<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Customer_Form_Element_Image extends Mage_Adminhtml_Block_Customer_Form_Element_File
{
    /**
     * Return Delete CheckBox Label
     * @return string
     */
    #[\Override]
    protected function _getDeleteCheckboxLabel()
    {
        return Mage::helper('adminhtml')->__('Delete Image');
    }

    /**
     * Return Delete CheckBox SPAN Class name
     * @return string
     */
    #[\Override]
    protected function _getDeleteCheckboxSpanClass()
    {
        return 'delete-image';
    }

    /**
     * Return File preview link HTML
     * @return string
     */
    #[\Override]
    protected function _getPreviewHtml()
    {
        $html = '';
        if ($this->getValue() && !is_array($this->getValue())) {
            $url = $this->_getPreviewUrl();
            $imageId = sprintf('%s_image', $this->getHtmlId());
            $image   = [
                'alt'    => Mage::helper('adminhtml')->__('View Full Size'),
                'title'  => Mage::helper('adminhtml')->__('View Full Size'),
                'src'    => $url,
                'class'  => 'small-image-preview v-middle',
                'height' => 22,
                'width'  => 22,
                'id'     => $imageId,
            ];
            $link    = [
                'href'      => $url,
                'onclick'   => "imagePreview('{$imageId}'); return false;",
            ];

            $html = sprintf(
                '%s%s</a> ',
                $this->_drawElementHtml('a', $link, false),
                $this->_drawElementHtml('img', $image),
            );
        }
        return $html;
    }

    /**
     * Return Image URL
     * @return string|false
     */
    #[\Override]
    protected function _getPreviewUrl()
    {
        if (is_array($this->getValue())) {
            return false;
        }
        return Mage::helper('adminhtml')->getUrl('adminhtml/customer/viewfile', [
            'image'      => Mage::helper('core')->urlEncode($this->getValue()),
        ]);
    }
}
