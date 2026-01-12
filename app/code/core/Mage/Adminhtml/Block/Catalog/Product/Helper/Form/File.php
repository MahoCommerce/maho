<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_File extends \Maho\Data\Form\Element\File
{
    /**
     * Get element HTML
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '';

        if ((string) $this->getValue()) {
            $url = $this->_getUrl();
            $fileName = basename($this->getValue());

            $html .= '<div class="file-info">';
            $html .= '<a href="' . $url . '" target="_blank" class="link">';
            $html .= Mage::helper('core')->escapeHtml($fileName);
            $html .= '</a>';
            $html .= '</div>';
        }

        $this->setClass('input-file');
        $html .= parent::getElementHtml();
        $html .= $this->_getDeleteCheckbox();

        return $html;
    }

    /**
     * Get file URL
     *
     * @return string
     */
    protected function _getUrl()
    {
        if ($this->getValue()) {
            return Mage::getBaseUrl('media') . 'catalog/files/' . $this->getValue();
        }
        return '';
    }

    /**
     * Get delete checkbox HTML
     *
     * @return string
     */
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ($attribute = $this->getEntityAttribute()) {
            if (!$attribute->getIsRequired() && $this->getValue()) {
                $label = Mage::helper('catalog')->__('Delete File');
                $html .= '<span class="delete-file">';
                $html .= '<input type="checkbox"'
                    . ' name="' . parent::getName() . '[delete]" value="1" class="checkbox"'
                    . ' id="' . $this->getHtmlId() . '_delete"'
                    . ($this->getDisabled() ? ' disabled="disabled"' : '') . '/>';
                $html .= '<label for="' . $this->getHtmlId() . '_delete"'
                    . ($this->getDisabled() ? ' class="disabled"' : '') . '> ' . $label . '</label>';
                $html .= '<input type="hidden" name="' . parent::getName() . '[value]" value="' . $this->getValue() . '" />';
                $html .= '</span>';
            } elseif ($attribute->getIsRequired() && $this->getValue()) {
                $html .= '<input value="' . $this->getValue() . '" id="' . $this->getHtmlId() . '_hidden" type="hidden" class="required-entry" />';
                $html .= '<script type="text/javascript">
                    syncOnchangeValue(\'' . $this->getHtmlId() . '\', \'' . $this->getHtmlId() . '_hidden\');
                </script>';
            }
        } elseif ($this->getValue()) {
            $label = Mage::helper('catalog')->__('Delete File');
            $html .= '<span class="delete-file">';
            $html .= '<input type="checkbox"'
                . ' name="' . parent::getName() . '[delete]" value="1" class="checkbox"'
                . ' id="' . $this->getHtmlId() . '_delete"'
                . ($this->getDisabled() ? ' disabled="disabled"' : '') . '/>';
            $html .= '<label for="' . $this->getHtmlId() . '_delete"'
                . ($this->getDisabled() ? ' class="disabled"' : '') . '> ' . $label . '</label>';
            $html .= '<input type="hidden" name="' . parent::getName() . '[value]" value="' . $this->getValue() . '" />';
            $html .= '</span>';
        }

        return $html;
    }
}
