<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_country';
        $this->_headerText = Mage::helper('directory')->__('Manage Countries');
        $this->_addButtonLabel = Mage::helper('directory')->__('Add New Country');
        parent::__construct();
    }
}
