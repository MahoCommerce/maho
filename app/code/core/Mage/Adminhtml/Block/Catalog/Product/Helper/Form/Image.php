<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Image extends \Maho\Data\Form\Element\Image
{
    #[\Override]
    protected function _getUrl()
    {
        $url = false;
        if ($this->getValue()) {
            $url = Mage::getBaseUrl('media') . 'catalog/product/' . $this->getValue();
        }
        return $url;
    }

    #[\Override]
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ($attribute = $this->getEntityAttribute()) {
            if (!$attribute->getIsRequired()) {
                $html .= parent::_getDeleteCheckbox();
            } else {
                $html .= '<input value="' . $this->getValue() . '" id="' . $this->getHtmlId() . '_hidden" type="hidden" class="required-entry" />';
                $html .= '<script type="text/javascript">
                    syncOnchangeValue(\'' . $this->getHtmlId() . '\', \'' . $this->getHtmlId() . '_hidden\');
                </script>';
            }
        } else {
            $html .= parent::_getDeleteCheckbox();
        }
        return $html;
    }
}
