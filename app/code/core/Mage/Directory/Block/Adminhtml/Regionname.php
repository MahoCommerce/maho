<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_regionname';
        $this->_headerText = Mage::helper('adminhtml')->__('Manage Region Names');
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add New Region Name');
        parent::__construct();
    }
}