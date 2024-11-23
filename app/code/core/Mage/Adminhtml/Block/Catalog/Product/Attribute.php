<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml catalog product attributes block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Attribute extends Mage_Eav_Block_Adminhtml_Attribute
{
    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');

        $this->_blockGroup = 'adminhtml';
        $this->_controller = 'catalog_product_attribute';

        $this->_headerText = $this->__(
            'Manage %s Attributes',
            Mage::helper('eav')->formatTypeCode($this->entityType->getEntityTypeCode())
        );

        $this->_addButtonLabel = Mage::helper('eav')->__('Add New Attribute');

        Mage_Adminhtml_Block_Widget_Grid_Container::__construct();
    }
}
