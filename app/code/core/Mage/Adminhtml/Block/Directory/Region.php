<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Adminhtml_Block_Directory_Region extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'adminhtml';
        $this->_controller = 'directory_region';
        $this->_headerText = Mage::helper('adminhtml')->__('Manage Regions');
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add New Region');
        parent::__construct();
    }
}
