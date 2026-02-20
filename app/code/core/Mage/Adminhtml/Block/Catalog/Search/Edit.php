<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Search_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'catalog_search';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('catalog')->__('Save Search'));
        $this->_updateButton('delete', 'label', Mage::helper('catalog')->__('Delete Search'));
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('current_catalog_search')->getId()) {
            return Mage::helper('catalog')->__("Edit Search '%s'", $this->escapeHtml(Mage::registry('current_catalog_search')->getQueryText()));
        }
        return Mage::helper('catalog')->__('New Search');
    }
}
